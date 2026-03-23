<?php
/**
 * LicenceFlow — Core WooCommerce integration
 *
 * Handles license delivery, email injection, customer display, and cron tasks.
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_Core {

    /** @var self|null */
    private static $instance = null;

    private function __construct() {
        // Delivery hooks — priority 1 ensures delivery runs BEFORE WooCommerce sends emails (priority 10)
        add_action( 'woocommerce_order_status_completed',  array( $this, 'maybe_deliver_on_completed' ), 1, 1 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_deliver_on_processing' ), 1, 1 );

        // Admin bar notification
        add_action( 'admin_bar_menu', array( $this, 'admin_bar_node' ), 100 );

        // Customer display hooks
        add_action( 'woocommerce_email_after_order_table',             array( $this, 'inject_email_licenses' ), 10, 4 );
        add_action( 'woocommerce_thankyou',                            array( $this, 'inject_thankyou_licenses' ), 10, 1 );
        add_action( 'woocommerce_order_details_after_order_table',     array( $this, 'inject_order_history_licenses' ), 10, 1 );

        // WooCommerce PDF Invoices & Packing Slips integration
        // Register on both hooks for version compatibility (deduplicated internally)
        add_action( 'wpo_wcpdf_after_order_details', array( $this, 'inject_pdf_licenses' ), 10, 2 );
        add_action( 'wpo_wcpdf_after_totals',        array( $this, 'inject_pdf_licenses' ), 10, 2 );

        // Cart validation (optional)
        add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_stock' ) );

        // Product deletion cleanup
        add_action( 'before_delete_post', array( $this, 'handle_product_deletion' ) );

        // Cron
        add_action( 'lflow_daily_cron', array( $this, 'run_daily_cron' ) );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Delivery ──────────────────────────────────────────────────────────────

    public function maybe_deliver_on_completed( int $order_id ): void {
        if ( LicenceFlow_Settings::is_on( 'lflow_send_when_completed' ) ) {
            $this->deliver_licenses_for_order( $order_id );
        }
    }

    public function maybe_deliver_on_processing( int $order_id ): void {
        if ( LicenceFlow_Settings::is_on( 'lflow_send_when_processing' ) ) {
            $this->deliver_licenses_for_order( $order_id );
        }
    }

    /**
     * Core delivery engine.
     * Assigns available licenses to an order and marks them as sold.
     */
    public function deliver_licenses_for_order( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Prevent double delivery
        if ( $order->get_meta( '_lflow_delivered' ) === '1' ) return;

        $fifo       = LicenceFlow_Settings::get( 'lflow_key_delivery' ) !== 'lifo';
        $stock_sync = LicenceFlow_Settings::is_on( 'lflow_stock_sync' );
        $all_ids    = array();
        $delivery_map = array(); // item_key => [ license_ids ]

        foreach ( $order->get_items() as $item_key => $item ) {
            $product_id   = (int) $item->get_product_id();
            $variation_id = (int) $item->get_variation_id();
            $item_qty     = (int) $item->get_quantity();

            if ( ! LicenceFlow_Product_Config::is_active( $product_id, $variation_id ) ) {
                continue;
            }

            $delivery_qty = LicenceFlow_Product_Config::get_delivery_qty( $product_id, $variation_id );
            $total_qty    = $delivery_qty * $item_qty;

            $licenses = LicenceFlow_License_DB::fetch_available( $product_id, $variation_id, $total_qty, $fifo );

            if ( empty( $licenses ) ) continue;

            // Group by license_id to handle delivre_x_times
            // (fetch_available may return the same license multiple times)
            $license_usage    = array(); // license_id => ['row' => $row, 'count' => N]
            $item_license_ids = array();

            foreach ( $licenses as $license ) {
                $lid = (int) $license['license_id'];
                if ( ! isset( $license_usage[ $lid ] ) ) {
                    $license_usage[ $lid ] = array( 'row' => $license, 'count' => 0 );
                }
                $license_usage[ $lid ]['count']++;
                $item_license_ids[] = $lid;
                $all_ids[]          = $lid;
            }

            foreach ( $license_usage as $lid => $entry ) {
                $row           = $entry['row'];
                $usage         = $entry['count'];
                $new_remaining = max( 0, (int) $row['remaining_delivre_x_times'] - $usage );

                $update_data = array(
                    'remaining_delivre_x_times' => $new_remaining,
                    'sold_date'                 => current_time( 'Y-m-d' ),
                    'activation_date'           => current_time( 'Y-m-d' ),
                    'owner_first_name'          => $order->get_billing_first_name(),
                    'owner_last_name'           => $order->get_billing_last_name(),
                    'owner_email_address'       => $order->get_billing_email(),
                    'order_id'                  => $order_id,
                );

                // Only mark 'sold' when all delivery slots are exhausted
                if ( $new_remaining <= 0 ) {
                    $update_data['license_status'] = 'sold';
                }

                LicenceFlow_License_DB::update( $lid, $update_data );
            }

            $delivery_map[ $item_key ] = array_values( array_unique( $item_license_ids ) );

            if ( $stock_sync ) {
                $this->sync_product_stock( $product_id, $variation_id );
            }
        }

        if ( empty( $all_ids ) ) return;

        // Store delivered license IDs on the order
        $order->update_meta_data( '_lflow_licenses', $all_ids );
        $order->update_meta_data( '_lflow_delivery_map', $delivery_map );
        $order->update_meta_data( '_lflow_delivered', '1' );
        $order->save();

        do_action( 'lflow_licenses_delivered', $order_id, $all_ids );
    }

    // ── Stock sync ────────────────────────────────────────────────────────────

    /**
     * Sync WooCommerce stock to available license count.
     *
     * Only syncs if:
     * - lflow_stock_sync option is on
     * - The product already has stock management enabled (_manage_stock = yes)
     *   (we never force-enable it — the admin controls this in WooCommerce)
     *
     * Respects WooCommerce backorder setting:
     * - If backorders are allowed and stock = 0 → set status to 'onbackorder'
     * - If backorders are not allowed and stock = 0 → set status to 'outofstock'
     */
    public function sync_product_stock( int $product_id, int $variation_id = 0 ): void {
        if ( ! LicenceFlow_Settings::is_on( 'lflow_stock_sync' ) ) return;

        $target_id = $variation_id > 0 ? $variation_id : $product_id;

        // Only sync if WooCommerce stock management is already enabled on this product
        $manage_stock = get_post_meta( $target_id, '_manage_stock', true );
        if ( $manage_stock !== 'yes' ) return;

        // Use SUM(remaining_delivre_x_times) to count total delivery capacity
        global $wpdb;
        if ( $variation_id > 0 ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(remaining_delivre_x_times), 0)
                 FROM {$wpdb->prefix}lflow_licenses
                 WHERE product_id = %d AND variation_id = %d AND license_status = 'available'",
                $product_id, $variation_id
            ) );
        } else {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(remaining_delivre_x_times), 0)
                 FROM {$wpdb->prefix}lflow_licenses
                 WHERE product_id = %d AND license_status = 'available'",
                $product_id
            ) );
        }

        if ( $count > 0 ) {
            update_post_meta( $target_id, '_stock', $count );
            update_post_meta( $target_id, '_stock_status', 'instock' );
            wp_remove_object_terms( $target_id, 'outofstock', 'product_visibility' );
        } else {
            $backorders = get_post_meta( $target_id, '_backorders', true );
            if ( in_array( $backorders, array( 'yes', 'notify' ), true ) ) {
                update_post_meta( $target_id, '_stock_status', 'onbackorder' );
                wp_remove_object_terms( $target_id, 'outofstock', 'product_visibility' );
            } else {
                update_post_meta( $target_id, '_stock', 0 );
                update_post_meta( $target_id, '_stock_status', 'outofstock' );
                wp_set_post_terms( $target_id, 'outofstock', 'product_visibility', true );
            }
        }

        // Clear WooCommerce product cache
        wc_delete_product_transients( $product_id );
    }

    // ── Cart validation ───────────────────────────────────────────────────────

    public function validate_cart_stock(): void {
        if ( ! LicenceFlow_Settings::is_on( 'lflow_enable_cart_validation' ) ) return;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_id   = (int) $cart_item['product_id'];
            $variation_id = (int) ( $cart_item['variation_id'] ?? 0 );
            $qty          = (int) $cart_item['quantity'];

            if ( ! LicenceFlow_Product_Config::is_active( $product_id, $variation_id ) ) continue;

            $delivery_qty = LicenceFlow_Product_Config::get_delivery_qty( $product_id, $variation_id );
            $needed       = $delivery_qty * $qty;
            $available    = LicenceFlow_License_DB::count_available( $product_id, $variation_id );

            if ( $available < $needed ) {
                $product = wc_get_product( $product_id );
                wc_add_notice( sprintf(
                    /* translators: %s: product name */
                    __( 'Stock de licences insuffisant pour "%s". Veuillez réduire la quantité ou réessayer plus tard.', 'licenceflow' ),
                    $product ? $product->get_name() : '#' . $product_id
                ), 'error' );
            }
        }
    }

    // ── Product deletion ──────────────────────────────────────────────────────

    public function handle_product_deletion( int $post_id ): void {
        if ( get_post_type( $post_id ) !== 'product' ) return;
        LicenceFlow_Product_Config::delete_product( $post_id );
    }

    // ── Email injection ───────────────────────────────────────────────────────

    /**
     * Inject the licenses block into WooCommerce order emails.
     *
     * @param WC_Order $order
     * @param bool     $sent_to_admin
     * @param bool     $plain_text
     * @param WC_Email $email
     */
    public function inject_email_licenses( WC_Order $order, bool $sent_to_admin, bool $plain_text, WC_Email $email ): void {
        // Force a fresh DB read — the order object passed by WooCommerce may be stale
        // (meta was added during delivery in the same request but the cached object is unaware)
        $fresh = wc_get_order( $order->get_id() );
        if ( ! $fresh ) return;

        $channel  = 'email';
        $licenses = $this->get_licenses_for_display( $fresh, $channel );
        if ( empty( $licenses ) ) return;

        lflow_include_template( 'email-licenses.php', array(
            'licenses'       => $licenses,
            'order'          => $fresh,
            'sent_to_admin'  => $sent_to_admin,
        ) );
    }

    /**
     * Inject licenses on the thank-you page.
     */
    public function inject_thankyou_licenses( int $order_id ): void {
        // Fresh fetch — avoid stale cache after same-request delivery
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Guest access check
        if ( ! LicenceFlow_Settings::is_on( 'lflow_guest_customer' ) && ! $order->get_user_id() ) return;

        $licenses = $this->get_licenses_for_display( $order, 'website' );
        if ( empty( $licenses ) ) return;

        lflow_include_template( 'thank-you-licenses.php', array( 'licenses' => $licenses, 'order' => $order ) );
    }

    /**
     * Inject licenses on the order history / account page.
     */
    public function inject_order_history_licenses( WC_Order $order ): void {
        if ( LicenceFlow_Settings::is_on( 'lflow_hide_keys_on_site' ) ) return;

        // Fresh fetch for consistent meta reading
        $fresh = wc_get_order( $order->get_id() );
        if ( ! $fresh ) return;

        $licenses = $this->get_licenses_for_display( $fresh, 'website' );
        if ( empty( $licenses ) ) return;

        lflow_include_template( 'order-history-licenses.php', array( 'licenses' => $licenses, 'order' => $fresh ) );
    }

    // ── PDF invoice (WooCommerce PDF Invoices & Packing Slips) ────────────────

    /**
     * Inject licenses into the PDF invoice after the totals table.
     *
     * Hook: wpo_wcpdf_after_totals ($document_type, $document)
     * Compatible with WooCommerce PDF Invoices & Packing Slips by Ewout Fernhout.
     *
     * @param string $document_type  'invoice', 'packing-slip', etc.
     * @param object $document       WPO_WCPDF_Document instance
     */
    public function inject_pdf_licenses( $document_type, $document ): void {
        // Skip packing slips — only inject on invoice-type documents
        // Accept 'invoice' and any type that isn't explicitly a packing slip
        if ( in_array( $document_type, array( 'packing-slip', 'credit-note' ), true ) ) return;

        // Retrieve the WC_Order from the document object (API varies by plugin version)
        if ( method_exists( $document, 'get_order' ) ) {
            $order = $document->get_order();
        } elseif ( isset( $document->order ) ) {
            $order = $document->order;
        } else {
            return;
        }

        if ( ! $order instanceof WC_Order ) return;

        $order_id = $order->get_id();

        // Deduplication: both hooks may fire for the same document — only output once per order
        static $rendered = array();
        $dedup_key = $document_type . '_' . $order_id;
        if ( isset( $rendered[ $dedup_key ] ) ) return;
        $rendered[ $dedup_key ] = true;

        // Fresh fetch to avoid stale cache
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $licenses = $this->get_licenses_for_display( $order, 'email' );
        if ( empty( $licenses ) ) return;

        lflow_include_template( 'pdf-licenses.php', array( 'licenses' => $licenses, 'order' => $order ) );
    }

    // ── Cron ──────────────────────────────────────────────────────────────────

    public function run_daily_cron(): void {
        global $wpdb;

        // Auto-expire
        if ( LicenceFlow_Settings::is_on( 'lflow_auto_expire' ) ) {
            $wpdb->query(
                "UPDATE {$wpdb->prefix}lflow_licenses
                 SET license_status = 'expired'
                 WHERE expiration_date IS NOT NULL
                   AND expiration_date != '0000-00-00'
                   AND expiration_date < CURDATE()
                   AND license_status NOT IN ('expired', 'returned', 'available')"
            );
        }

        // Expiry alerts
        $days_before = (int) LicenceFlow_Settings::get( 'lflow_alert_days_before', 7 );
        $alert_email = LicenceFlow_Settings::get( 'lflow_alert_email', get_option( 'admin_email' ) );

        $expiring = LicenceFlow_License_DB::get_expiring_soon( $days_before );
        if ( ! empty( $expiring ) ) {
            $this->send_expiry_alert_email( $expiring, $alert_email );
        }

        // Low stock alerts (admin bar badge — refresh transient)
        $low_stock = LicenceFlow_License_DB::get_low_stock_products( 5 );
        set_transient( 'lflow_low_stock_count', count( $low_stock ), DAY_IN_SECONDS );
    }

    /**
     * Send an expiry alert email to the admin.
     */
    private function send_expiry_alert_email( array $licenses, string $to ): void {
        $count   = count( $licenses );
        $subject = sprintf(
            /* translators: %d: number of licenses */
            __( '[LicenceFlow] %d licence(s) expire bientôt', 'licenceflow' ),
            $count
        );

        $body  = '<p>' . sprintf(
            /* translators: %d: number of licenses */
            __( 'Les licences suivantes (%d) arrivent à expiration prochainement :', 'licenceflow' ),
            $count
        ) . '</p>';
        $body .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">';
        $body .= '<tr><th>ID</th><th>Produit</th><th>Email client</th><th>Expiration (admin)</th></tr>';

        foreach ( $licenses as $l ) {
            $product = wc_get_product( $l['product_id'] );
            $pname   = $product ? $product->get_name() : '#' . $l['product_id'];
            $body   .= '<tr>';
            $body   .= '<td><a href="' . admin_url( 'admin.php?page=lflow-licenses&action=edit&license_id=' . absint( $l['license_id'] ) ) . '">#' . absint( $l['license_id'] ) . '</a></td>';
            $body   .= '<td>' . esc_html( $pname ) . '</td>';
            $body   .= '<td>' . esc_html( $l['owner_email_address'] ?: '—' ) . '</td>';
            $body   .= '<td>' . esc_html( lflow_format_date( $l['expiration_date'], true ) ) . '</td>';
            $body   .= '</tr>';
        }

        $body .= '</table>';
        $body .= '<p><a href="' . admin_url( 'admin.php?page=lflow-licenses' ) . '">' . esc_html__( 'Gérer les licences', 'licenceflow' ) . '</a></p>';

        wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
    }

    // ── Admin bar ─────────────────────────────────────────────────────────────

    /**
     * Add a LicenceFlow node to the WP admin bar with alert counts.
     */
    public function admin_bar_node( WP_Admin_Bar $wp_admin_bar ): void {
        if ( ! LicenceFlow_Settings::is_on( 'lflow_show_adminbar_notifs' ) ) return;
        if ( ! lflow_current_user_can() ) return;
        if ( ! is_admin() ) return;

        $low_stock_count = (int) get_transient( 'lflow_low_stock_count' );

        $title = __( 'LicenceFlow', 'licenceflow' );
        if ( $low_stock_count > 0 ) {
            $title .= ' <span style="background:#d63638; color:#fff; border-radius:8px; padding:1px 6px; font-size:.75em; margin-left:4px;">'
                . (int) $low_stock_count . '</span>';
        }

        $wp_admin_bar->add_node( array(
            'id'    => 'lflow-adminbar',
            'title' => $title,
            'href'  => admin_url( 'admin.php?page=lflow-statistics' ),
        ) );

        $wp_admin_bar->add_node( array(
            'parent' => 'lflow-adminbar',
            'id'     => 'lflow-adminbar-licenses',
            'title'  => __( 'Licences', 'licenceflow' ),
            'href'   => admin_url( 'admin.php?page=lflow-licenses' ),
        ) );

        $wp_admin_bar->add_node( array(
            'parent' => 'lflow-adminbar',
            'id'     => 'lflow-adminbar-add',
            'title'  => __( 'Ajouter une licence', 'licenceflow' ),
            'href'   => admin_url( 'admin.php?page=lflow-add-license' ),
        ) );

        $wp_admin_bar->add_node( array(
            'parent' => 'lflow-adminbar',
            'id'     => 'lflow-adminbar-stats',
            'title'  => __( 'Statistiques', 'licenceflow' ),
            'href'   => admin_url( 'admin.php?page=lflow-statistics' ),
        ) );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Get decrypted licenses for display, filtered by show_in channel.
     * NEVER includes expiration_date — only customer-visible data.
     *
     * @param WC_Order $order
     * @param string   $channel  'email' or 'website'
     * @return array
     */
    public function get_licenses_for_display( WC_Order $order, string $channel ): array {
        $license_ids = $order->get_meta( '_lflow_licenses' );
        if ( empty( $license_ids ) || ! is_array( $license_ids ) ) return array();

        $result = array();

        // Count how many times each license_id appears (delivre_x_times scenario)
        $id_counts = array_count_values( array_map( 'intval', $license_ids ) );

        // Process each unique license_id once
        foreach ( $id_counts as $license_id => $times ) {
            $license = LicenceFlow_License_DB::get( $license_id );
            if ( ! $license ) continue;

            // Channel filter
            $show_in = LicenceFlow_Product_Config::get_show_in( (int) $license['product_id'], (int) $license['variation_id'] );
            if ( $show_in !== 'both' && $show_in !== $channel ) continue;

            // Parse value for display
            $license['parsed_value'] = lflow_parse_license_value( $license['license_key'], $license['license_type'] ?? 'key' );

            // Customer expiry (calculated from sold_date + valid) — NEVER expose expiration_date
            $license['customer_expiry'] = lflow_customer_expiry_date( $license['sold_date'] ?? '', (int) ( $license['valid'] ?? 0 ) );

            // How many times this license was delivered for this order
            $license['times'] = $times;

            // Strip admin-only fields before passing to templates
            // license_note is intentionally kept — it is customer-visible
            unset( $license['expiration_date'], $license['admin_notes'], $license['license_key'] );

            $result[] = $license;
        }

        return $result;
    }
}

/**
 * Helper: include a LicenceFlow template file with variable scope.
 *
 * @param string $template  Filename inside /templates/
 * @param array  $vars      Variables to extract into template scope
 */
function lflow_include_template( string $template, array $vars = array() ): void {
    $path = LFLOW_PATH . 'templates/' . $template;
    if ( ! file_exists( $path ) ) return;
    extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
    include $path;
}
