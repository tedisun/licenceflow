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
        // Hook fires after the order details + totals table, passing ($type, WC_Order)
        add_action( 'wpo_wcpdf_after_order_details', array( $this, 'inject_pdf_licenses' ), 10, 2 );

        // Refund: restore licenses to available when an order is refunded
        add_action( 'woocommerce_order_refunded', array( $this, 'handle_refund' ), 10, 2 );

        // Cancellation / failure: restore licenses just like a refund
        add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_changed' ), 10, 3 );

        // Cart validation (optional)
        add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_stock' ) );

        // Product deletion cleanup
        add_action( 'before_delete_post', array( $this, 'handle_product_deletion' ) );

        // Cron
        add_action( 'lflow_daily_cron',       array( $this, 'run_daily_cron' ) );
        add_action( 'lflow_daily_audit_cron', array( $this, 'run_daily_audit' ) );
        add_action( 'wp_loaded',              array( $this, 'maybe_schedule_audit_cron' ) );

        // Action Scheduler
        add_action( 'lflow_check_single_key', array( $this, 'handle_single_key_audit' ), 10, 2 );
        add_action( 'lflow_recheck_inactive_key', array( $this, 'handle_recheck_inactive_key' ), 10, 1 );
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

        $delivery_mode = LicenceFlow_Settings::get( 'lflow_key_delivery', 'fifo' );
        $stock_sync    = LicenceFlow_Settings::is_on( 'lflow_stock_sync' );
        $all_ids       = array();
        $delivery_map  = array(); // item_key => [ license_ids ]

        foreach ( $order->get_items() as $item_key => $item ) {
            $product_id   = (int) $item->get_product_id();
            $variation_id = (int) $item->get_variation_id();
            $item_qty     = (int) $item->get_quantity();

            if ( ! LicenceFlow_Product_Config::is_active( $product_id, $variation_id ) ) {
                continue;
            }

            $delivery_qty = LicenceFlow_Product_Config::get_delivery_qty( $product_id, $variation_id );
            $total_qty    = $delivery_qty * $item_qty;

            if ( $delivery_mode === 'best_fit' ) {
                $licenses = LicenceFlow_License_DB::fetch_best_fit( $product_id, $variation_id, $total_qty );
            } else {
                $licenses = LicenceFlow_License_DB::fetch_available( $product_id, $variation_id, $total_qty, $delivery_mode !== 'lifo' );
            }

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

            // Let registered listeners (e.g. stock notifier) react to each delivered item
            do_action( 'lflow_stock_after_delivery', $product_id, $variation_id );
        }

        if ( empty( $all_ids ) ) return;

        // Store delivered license IDs on the order
        $order->update_meta_data( '_lflow_licenses', $all_ids );
        $order->update_meta_data( '_lflow_delivery_map', $delivery_map );
        $order->update_meta_data( '_lflow_delivered', '1' );
        $order->save();

        do_action( 'lflow_licenses_delivered', $order_id, $all_ids );
    }

    // ── Refund handling ───────────────────────────────────────────────────────

    /**
     * When an order is refunded, restore its licenses to 'available'.
     * Restores remaining_delivre_x_times by the count of deliveries for this order.
     * Clears owner info only if the license is fully restored (remaining == delivre_x_times).
     *
     * @param int $order_id
     * @param int $refund_id  (unused, required by hook signature)
     */
    public function handle_refund( int $order_id, int $refund_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $license_ids = $order->get_meta( '_lflow_licenses' );
        if ( empty( $license_ids ) || ! is_array( $license_ids ) ) return;

        // Count how many times each license was delivered for this order
        $id_counts = array_count_values( array_map( 'intval', $license_ids ) );

        foreach ( $id_counts as $lid => $times ) {
            $license = LicenceFlow_License_DB::get( $lid );
            if ( ! $license ) continue;

            $max_remaining = (int) $license['delivre_x_times'];
            $new_remaining = min( $max_remaining, (int) $license['remaining_delivre_x_times'] + $times );

            $update = array(
                'remaining_delivre_x_times' => $new_remaining,
                'license_status'            => 'available',
            );

            // Clear owner only if fully restored
            if ( $new_remaining === $max_remaining ) {
                $update['owner_email_address'] = '';
                $update['owner_first_name']    = '';
                $update['owner_last_name']     = '';
                $update['order_id']            = 0;
                $update['sold_date']           = null;
                $update['activation_date']     = null;
            }

            LicenceFlow_License_DB::update( $lid, $update );
            $this->sync_product_stock( (int) $license['product_id'], (int) $license['variation_id'] );
            // Notify listeners that stock was restored (e.g. stock notifier resets alert flag)
            do_action( 'lflow_stock_after_restore', (int) $license['product_id'], (int) $license['variation_id'] );
        }
    }

    // ── Order cancellation / failure ─────────────────────────────────────────

    /**
     * Restore licenses when an order is cancelled or failed.
     * Same logic as handle_refund — guard against double-processing is built in
     * (handle_refund returns early if _lflow_licenses meta is empty).
     */
    public function handle_order_status_changed( int $order_id, string $old_status, string $new_status ): void {
        if ( in_array( $new_status, array( 'cancelled', 'failed' ), true ) ) {
            $this->handle_refund( $order_id, 0 );
        }
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
        $product   = wc_get_product( $target_id );
        if ( ! $product ) return;

        // Force proper stock management settings based on product type
        if ( $product->is_type( 'variable' ) ) {
            // Variable parent: must NOT manage stock at the parent level
            if ( get_post_meta( $target_id, '_manage_stock', true ) !== 'no' ) {
                update_post_meta( $target_id, '_manage_stock', 'no' );
            }
        } elseif ( LicenceFlow_Product_Config::is_active( $product_id, $variation_id ) ) {
            // Simple product or Variation: must manage stock if LicenceFlow is active
            if ( get_post_meta( $target_id, '_manage_stock', true ) !== 'yes' ) {
                update_post_meta( $target_id, '_manage_stock', 'yes' );
            }
        }

        // Only sync if WooCommerce stock management is enabled on this product
        $manage_stock = get_post_meta( $target_id, '_manage_stock', true );
        if ( $manage_stock !== 'yes' ) return;

        // Use SUM(remaining_delivre_x_times) to count total delivery capacity
        global $wpdb;
        if ( $variation_id > 0 ) {
            $db_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(remaining_delivre_x_times), 0)
                 FROM {$wpdb->prefix}lflow_licenses
                 WHERE product_id = %d AND variation_id = %d AND license_status = 'available'",
                $product_id, $variation_id
            ) );
        } else {
            $db_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(remaining_delivre_x_times), 0)
                 FROM {$wpdb->prefix}lflow_licenses
                 WHERE product_id = %d AND license_status = 'available'",
                $product_id
            ) );
        }

        // Account for keys that are already allocated to processing orders
        // but not yet marked as 'sold' or delivered in LicenceFlow.
        // We do not reserve stock for unpaid orders (pending/on-hold) to avoid
        // locking stock on temporary/failed payment attempts.
        $pending_qty = 0;
        $uncompleted_orders = wc_get_orders( array(
            'status'     => array( 'processing' ),
            'limit'      => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => '_lflow_delivered',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_lflow_delivered',
                    'value'   => '1',
                    'compare' => '!=',
                ),
            ),
        ) );

        if ( ! empty( $uncompleted_orders ) ) {
            foreach ( $uncompleted_orders as $order ) {
                foreach ( $order->get_items() as $item ) {
                    $item_prod_id = (int) $item->get_product_id();
                    $item_var_id  = (int) $item->get_variation_id();
                    $item_target  = $item_var_id > 0 ? $item_var_id : $item_prod_id;
                    if ( $item_target === $target_id ) {
                        $pending_qty += (int) $item->get_quantity();
                    }
                }
            }
        }

        $count = max( 0, $db_count - $pending_qty );

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

        // For variable products: re-sync the parent's stock status from its variations.
        // Without this, the parent product keeps showing "out of stock" even when
        // a variation's _stock_status has been restored above, because WooCommerce
        // tracks the parent's status independently and does not observe the variation
        // meta we just wrote. WC_Product_Variable_Data_Store_CPT::sync_stock() scans
        // all child _stock_status values and updates the parent accordingly.
        if ( $variation_id > 0 ) {
            $parent = wc_get_product( $product_id );
            if ( $parent instanceof WC_Product_Variable ) {
                $data_store = WC_Data_Store::load( 'product-variable' );
                $data_store->sync_stock( $parent );
            }
        }

        // Clear WooCommerce product cache
        wc_delete_product_transients( $product_id );
    }

    // ── Full stock sync ───────────────────────────────────────────────────────

    /**
     * Sync WooCommerce stock for every actively-configured product/variation.
     * Loops through wp_lflow_licensed_products WHERE active = 1 and calls
     * sync_product_stock() on each pair. Used by the manual "Sync all" button
     * and after bulk status changes.
     *
     * @return int  Number of product/variation pairs processed
     */
    public function sync_all_products_stock(): int {
        if ( ! LicenceFlow_Settings::is_on( 'lflow_stock_sync' ) ) {
            return 0;
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT DISTINCT product_id, variation_id
             FROM {$wpdb->prefix}lflow_licensed_products
             WHERE active = 1",
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return 0;
        }

        $count = 0;
        foreach ( $rows as $row ) {
            $this->sync_product_stock( (int) $row['product_id'], (int) $row['variation_id'] );
            $count++;
        }
        return $count;
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

        // Retrieve the WC_Order from the second argument.
        // wpo_wcpdf_after_order_details passes $this->order (WC_Order) directly.
        // Some older/other hooks pass a document wrapper — handle both.
        if ( $document instanceof WC_Order ) {
            $order = $document;
        } elseif ( method_exists( $document, 'get_order' ) ) {
            $order = $document->get_order();
        } elseif ( isset( $document->order ) && $document->order instanceof WC_Order ) {
            $order = $document->order;
        } else {
            return;
        }

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

        // Low stock alerts (admin bar badge — COUNT only, no full row fetch)
        $low_stock_count = LicenceFlow_License_DB::count_low_stock_products( 5 );
        set_transient( 'lflow_low_stock_count', $low_stock_count, DAY_IN_SECONDS );
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

    /**
     * Run daily licence validation audit at 18:00 (now thrice daily, every 8 hours)
     * Triggers asynchronous key checks via Action Scheduler.
     */
    public function run_daily_audit(): void {
        $this->start_audit_scan( false );
    }

    /**
     * Start a new audit scan of all available Microsoft keys.
     * Schedules individual key checks in Action Scheduler.
     *
     * @param bool $manual True if triggered manually, false if triggered by cron.
     * @return int The number of keys scheduled for check.
     */
    public function start_audit_scan( bool $manual = false ): int {
        global $wpdb;

        // Check if there is already a running scan to avoid concurrent queueing
        $logs = get_option( 'lflow_audit_logs', array() );
        if ( is_array( $logs ) ) {
            foreach ( $logs as $log ) {
                if ( isset( $log['status'] ) && $log['status'] === 'running' ) {
                    $start_time = strtotime( $log['date'] ?? '' );
                    if ( $start_time && ( time() - $start_time ) < 3600 ) {
                        return 0; // Scan already running, skip starting a new one
                    }
                }
            }
        }

        $whitelisted_ids = LicenceFlow_Settings::get( 'lflow_auditable_product_ids', array() );
        if ( empty( $whitelisted_ids ) ) {
            $this->log_audit_result( array(
                'date'      => current_time( 'Y-m-d H:i:s' ),
                'status'    => 'skipped',
                'checked'   => 0,
                'blocked'   => 0,
                'message'   => __( 'Aucun produit ou variation n\'est configuré pour l\'audit en ligne dans les Réglages.', 'licenceflow' ),
                'anomalies' => array(),
            ) );
            return 0; 
        }
        $ids_in = implode( ',', array_map( 'intval', $whitelisted_ids ) );

        // Fetch all available keys for whitelisted products/variations
        $rows = $wpdb->get_results(
            "SELECT license_id, product_id, variation_id, license_key 
             FROM {$wpdb->prefix}lflow_licenses 
             WHERE license_status = 'available' 
               AND license_type = 'key' 
               AND remaining_delivre_x_times > 0
               AND (
                   (variation_id = 0 AND product_id IN ($ids_in))
                   OR
                   (variation_id > 0 AND variation_id IN ($ids_in))
               )",
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            $this->log_audit_result( array(
                'date'      => current_time( 'Y-m-d H:i:s' ),
                'status'    => 'completed',
                'checked'   => 0,
                'blocked'   => 0,
                'message'   => __( 'Aucune licence disponible en stock à vérifier.', 'licenceflow' ),
                'anomalies' => array(),
            ) );
            return 0;
        }

        $microsoft_keys = array(); // license_id => [ 'row' => $row, 'key' => $plain_key ]

        foreach ( $rows as $row ) {
            $plain_key = lflow_decrypt( $row['license_key'] );
            if ( preg_match( '/^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/i', $plain_key ) ) {
                $microsoft_keys[ $row['license_id'] ] = array(
                    'row' => $row,
                    'key' => $plain_key,
                );
            }
        }

        if ( empty( $microsoft_keys ) ) {
            $this->log_audit_result( array(
                'date'      => current_time( 'Y-m-d H:i:s' ),
                'status'    => 'completed',
                'checked'   => 0,
                'blocked'   => 0,
                'message'   => __( 'Aucune clé au format Microsoft 5x5 valide trouvée en stock parmi les produits configurés.', 'licenceflow' ),
                'anomalies' => array(),
            ) );
            return 0;
        }

        $scan_id = 'scan_' . time() . '_' . wp_generate_password( 4, false );
        $total   = count( $microsoft_keys );

        $start_message = $manual
            ? sprintf( __( 'Scan manuel démarré : %d clés en file d\'attente...', 'licenceflow' ), $total )
            : sprintf( __( 'Scan automatique démarré : %d clés en file d\'attente...', 'licenceflow' ), $total );

        $this->log_audit_result( array(
            'scan_id'   => $scan_id,
            'date'      => current_time( 'Y-m-d H:i:s' ),
            'status'    => 'running',
            'checked'   => 0,
            'total'     => $total,
            'blocked'   => 0,
            'message'   => $start_message,
            'anomalies' => array(),
        ) );

        $index = 0;
        foreach ( $microsoft_keys as $lid => $item ) {
            if ( function_exists( 'as_schedule_single_action' ) ) {
                as_schedule_single_action(
                    time() + ( $index * 30 ),
                    'lflow_check_single_key',
                    array(
                        'license_id' => $lid,
                        'scan_id'    => $scan_id,
                    ),
                    'licenceflow-audit'
                );
            }
            $index++;
        }

        return $total;
    }

    /**
     * Audit a single license key. Triggered via Action Scheduler.
     *
     * @param int    $license_id
     * @param string $scan_id
     */
    public function handle_single_key_audit( int $license_id, string $scan_id = '' ): void {
        global $wpdb;

        $license = LicenceFlow_License_DB::get( $license_id );
        if ( ! $license ) {
            if ( ! empty( $scan_id ) ) {
                $this->update_scan_progress( $scan_id, $license_id, null );
            }
            return;
        }

        if ( $license['license_status'] !== 'available' ) {
            if ( ! empty( $scan_id ) ) {
                $this->update_scan_progress( $scan_id, $license_id, null );
            }
            return;
        }

        $plain_key = lflow_decrypt( $license['license_key'] );
        if ( ! preg_match( '/^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/i', $plain_key ) ) {
            if ( ! empty( $scan_id ) ) {
                $this->update_scan_progress( $scan_id, $license_id, null );
            }
            return;
        }

        $url = 'https://licenceflow-checker.app.tedisun.com/v1/check';
        $api_key = 'sk_lf_7b58cde3e5e4fa6b51df2f6d2e8b6a382e71d3c05b8aef52968840c1d683a219';

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type'          => 'application/json',
                'X-LicenceFlow-Api-Key' => $api_key,
            ),
            'body'    => wp_json_encode( array( 'keys' => array( $plain_key ) ) ),
            'timeout' => 30,
        ) );

        $status = 'unknown';
        $remaining = null;
        $msg = '';
        $error_code = '';
        $product_name = '';

        if ( ! is_wp_error( $response ) ) {
            $body_res = wp_remote_retrieve_body( $response );
            $data = json_decode( $body_res, true );

            if ( ! empty( $data['success'] ) && ! empty( $data['results'] ) ) {
                $res = $data['results'][0];
                $status = $res['status'] ?? 'unknown';
                $remaining = $res['remaining_activations'] ?? null;
                $msg = $res['message'] ?? '';
                $error_code = $res['error_code'] ?? '';
                $product_name = $res['product_name'] ?? '';
            }
        } else {
            $status = 'error';
            $msg = $response->get_error_message();
        }

        if ( $status === 'error' ) {
            if ( ! empty( $scan_id ) ) {
                $this->update_scan_progress( $scan_id, $license_id, null );
            }
            return;
        }

        $is_mak = ( ! empty( $product_name ) && strpos( strtolower( $product_name ), 'mak' ) !== false );

        $is_office_2024_ltsc = ( (int) $license['product_id'] === 14335 );
        if ( ! $is_office_2024_ltsc ) {
            $product = wc_get_product( $license['product_id'] );
            if ( $product ) {
                $product_name_lower = strtolower( $product->get_name() );
                $product_slug_lower = strtolower( $product->get_slug() );
                if ( strpos( $product_slug_lower, 'office-2024-pro-plus-ltsc' ) !== false || 
                     strpos( $product_name_lower, 'office 2024 professionnel plus ltsc' ) !== false ) {
                    $is_office_2024_ltsc = true;
                }
            }
        }

        if ( ( $is_mak || $is_office_2024_ltsc ) && ( $remaining === null || $remaining === 'N/A' || ( is_numeric( $remaining ) && (int) $remaining <= 0 ) ) ) {
            $status = 'blocked';
            $msg = __( 'Le nombre d\'activations restantes est à zéro ou indisponible.', 'licenceflow' );
            $error_code = 'NO_ACTIVATIONS_LEFT';
        }

        // If the error code indicates the key has already been activated
        if ( ! empty( $error_code ) && strpos( strtolower( $error_code ), 'activated' ) !== false ) {
            $status = 'blocked';
            $msg = __( 'La clé est déjà activée (activated with a product key).', 'licenceflow' );
            $error_code = 'ALREADY_ACTIVATED';
        }

        $anomaly = null;

        if ( $status === 'blocked' || $status === 'phone_activation' ) {
            $msg_clean = $msg ? $msg : 'Bloquée par Microsoft.';
            if ( $status === 'phone_activation' ) {
                $msg_clean = __( 'Activation par téléphone uniquement (non autorisée pour la vente en ligne).', 'licenceflow' );
            }
            $date = current_time( 'Y-m-d H:i:s' );
            $admin_note = trim( $license['admin_notes'] ?? '' );
            $new_note = "[Audit $date] Retirée du stock : $msg_clean";
            $admin_note = $admin_note ? $admin_note . "\n" . $new_note : $new_note;

            LicenceFlow_License_DB::update( $license_id, array(
                'license_status' => 'inactive',
                'admin_notes'    => $admin_note,
            ) );

            $this->sync_product_stock( (int) $license['product_id'], (int) $license['variation_id'] );

            if ( function_exists( 'as_schedule_single_action' ) ) {
                as_schedule_single_action(
                    time() + 600,
                    'lflow_recheck_inactive_key',
                    array(
                        'license_id' => $license_id,
                    ),
                    'licenceflow-audit'
                );
            }

            $prod_name = '#' . $license['product_id'];
            $product = wc_get_product( $license['product_id'] );
            if ( $product ) {
                $prod_name = $product->get_name();
                if ( ! empty( $license['variation_id'] ) ) {
                    $variation = wc_get_product( $license['variation_id'] );
                    if ( $variation && $variation->is_type( 'variation' ) ) {
                        $prod_name .= ' — ' . wc_get_formatted_variation( $variation, true, false );
                    }
                }
            }

            $anomaly = array(
                'license_id'   => $license_id,
                'product_id'   => (int) $license['product_id'],
                'variation_id' => (int) $license['variation_id'],
                'product_name' => $prod_name,
                'key'          => $plain_key,
                'message'      => $msg_clean,
            );
        }

        if ( ! empty( $scan_id ) ) {
            $this->update_scan_progress( $scan_id, $license_id, $anomaly );
        }
    }

    /**
     * Recheck an inactive key 10 minutes after it failed the audit scan.
     * If it is actually online/valid, reactivate it and restore stock.
     *
     * @param int $license_id
     */
    public function handle_recheck_inactive_key( int $license_id ): void {
        global $wpdb;

        $license = LicenceFlow_License_DB::get( $license_id );
        if ( ! $license || $license['license_status'] !== 'inactive' ) {
            return;
        }

        $plain_key = lflow_decrypt( $license['license_key'] );
        if ( ! preg_match( '/^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/i', $plain_key ) ) {
            return;
        }

        $url = 'https://licenceflow-checker.app.tedisun.com/v1/check';
        $api_key = 'sk_lf_7b58cde3e5e4fa6b51df2f6d2e8b6a382e71d3c05b8aef52968840c1d683a219';

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type'          => 'application/json',
                'X-LicenceFlow-Api-Key' => $api_key,
            ),
            'body'    => wp_json_encode( array( 'keys' => array( $plain_key ) ) ),
            'timeout' => 30,
        ) );

        $status = 'unknown';
        $remaining = null;
        $msg = '';
        $error_code = '';
        $product_name = '';

        if ( ! is_wp_error( $response ) ) {
            $body_res = wp_remote_retrieve_body( $response );
            $data = json_decode( $body_res, true );

            if ( ! empty( $data['success'] ) && ! empty( $data['results'] ) ) {
                $res = $data['results'][0];
                $status = $res['status'] ?? 'unknown';
                $remaining = $res['remaining_activations'] ?? null;
                $msg = $res['message'] ?? '';
                $error_code = $res['error_code'] ?? '';
                $product_name = $res['product_name'] ?? '';
            }
        } else {
            $status = 'error';
            $msg = $response->get_error_message();
        }

        // If there was a network/technical error during the recheck, we do nothing.
        if ( $status === 'error' ) {
            return;
        }

        $is_mak = ( ! empty( $product_name ) && strpos( strtolower( $product_name ), 'mak' ) !== false );
        $is_office_2024_ltsc = ( (int) $license['product_id'] === 14335 );
        if ( ! $is_office_2024_ltsc ) {
            $product = wc_get_product( $license['product_id'] );
            if ( $product ) {
                $product_name_lower = strtolower( $product->get_name() );
                $product_slug_lower = strtolower( $product->get_slug() );
                if ( strpos( $product_slug_lower, 'office-2024-pro-plus-ltsc' ) !== false || 
                     strpos( $product_name_lower, 'office 2024 professionnel plus ltsc' ) !== false ) {
                    $is_office_2024_ltsc = true;
                }
            }
        }

        if ( ( $is_mak || $is_office_2024_ltsc ) && ( $remaining === null || $remaining === 'N/A' || ( is_numeric( $remaining ) && (int) $remaining <= 0 ) ) ) {
            $status = 'blocked';
            $msg = __( 'Le nombre d\'activations restantes est à zéro ou indisponible.', 'licenceflow' );
            $error_code = 'NO_ACTIVATIONS_LEFT';
        }

        // If the error code indicates the key has already been activated
        if ( ! empty( $error_code ) && strpos( strtolower( $error_code ), 'activated' ) !== false ) {
            $status = 'blocked';
            $msg = __( 'La clé est déjà activée (activated with a product key).', 'licenceflow' );
            $error_code = 'ALREADY_ACTIVATED';
        }

        $date = current_time( 'Y-m-d H:i:s' );
        $admin_note = trim( $license['admin_notes'] ?? '' );

        if ( $status === 'online_key' ) {
            // Success! Reactivate the key
            $new_note = "[Audit $date] Réactivée automatiquement après double vérification (10 min) : Clé en ligne active ($product_name, $remaining activations).";
            $admin_note = $admin_note ? $admin_note . "\n" . $new_note : $new_note;

            LicenceFlow_License_DB::update( $license_id, array(
                'license_status'            => 'available',
                'remaining_delivre_x_times' => $license['delivre_x_times'] ?? 1,
                'admin_notes'               => $admin_note,
            ) );

            $this->sync_product_stock( (int) $license['product_id'], (int) $license['variation_id'] );
        } else {
            // Confirmed deactivation
            $msg_clean = $msg ? $msg : 'Bloquée par Microsoft.';
            if ( $status === 'phone_activation' ) {
                $msg_clean = __( 'Activation par téléphone uniquement (non autorisée pour la vente en ligne).', 'licenceflow' );
            }
            $new_note = "[Audit $date] Double vérification (10 min) confirmée : La clé reste inactive ($msg_clean).";
            $admin_note = $admin_note ? $admin_note . "\n" . $new_note : $new_note;

            LicenceFlow_License_DB::update( $license_id, array(
                'admin_notes' => $admin_note,
            ) );
        }
    }

    /**
     * Update the progress of an active audit scan.
     *
     * @param string     $scan_id
     * @param int        $license_id
     * @param array|null $anomaly
     */
    public function update_scan_progress( string $scan_id, int $license_id, ?array $anomaly = null ): void {
        $logs = get_option( 'lflow_audit_logs', array() );
        if ( ! is_array( $logs ) ) {
            return;
        }

        $updated = false;
        foreach ( $logs as &$log ) {
            if ( isset( $log['scan_id'] ) && $log['scan_id'] === $scan_id ) {
                $log['checked']++;
                
                if ( $anomaly ) {
                    $log['blocked']++;
                    if ( ! isset( $log['anomalies'] ) ) {
                        $log['anomalies'] = array();
                    }
                    $log['anomalies'][] = $anomaly;
                }

                $total   = $log['total'] ?? 0;
                $checked = $log['checked'];
                $blocked = $log['blocked'];

                if ( $checked >= $total ) {
                    $log['status'] = 'completed';
                    $log['message'] = sprintf(
                        /* translators: 1: total keys, 2: blocked keys */
                        _n(
                            'Audit terminé : %1$d clé vérifiée, %2$d clé bloquée/inactive retirée.',
                            'Audit terminé : %1$d clés vérifiées, %2$d clés bloquées/inactives retirées.',
                            $checked,
                            'licenceflow'
                        ),
                        $checked,
                        $blocked
                    );
                    
                    if ( ! empty( $log['anomalies'] ) ) {
                        $alert_email = LicenceFlow_Settings::get( 'lflow_alert_email', get_option( 'admin_email' ) );
                        $this->send_audit_alert_email( $log['anomalies'], $alert_email );
                    }
                } else {
                    $log['message'] = sprintf(
                        __( 'Scan d\'audit en cours : %1$d/%2$d clés vérifiées (%3$d anomalies)...', 'licenceflow' ),
                        $checked,
                        $total,
                        $blocked
                    );
                }
                
                $updated = true;
                break;
            }
        }

        if ( $updated ) {
            update_option( 'lflow_audit_logs', $logs );
        }
    }

    /**
     * Send email report for blocked licenses detected during the audit.
     */
    private function send_audit_alert_email( array $anomalies, string $to ): void {
        $count = count( $anomalies );
        $subject = sprintf(
            /* translators: %d: number of blocked licenses */
            __( '[LicenceFlow] ⚠️ Alerte Stock : %d clé(s) bloquée(s) détectée(s) et retirée(s)', 'licenceflow' ),
            $count
        );

        $body  = '<p style="font-size: 1.1em; color: #1d2327;">' . sprintf(
            __( 'L\'audit de santé automatique a détecté <strong>%d clé(s) Microsoft inactive(s) ou bloquée(s)</strong>. Elles ont été retirées du stock automatiquement pour préserver la qualité de vos ventes.', 'licenceflow' ),
            $count
        ) . '</p>';
        $body .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse; width:100%; border:1px solid #ccd0d4; font-family: sans-serif;">';
        $body .= '<tr style="background:#f6f7f7;"><th>ID</th><th>Produit</th><th>Clé (tronquée)</th><th>Raison / Statut</th></tr>';

        foreach ( $anomalies as $a ) {
            $product = wc_get_product( $a['product_id'] );
            $pname   = $product ? $product->get_name() : '#' . $a['product_id'];
            if ( $a['variation_id'] > 0 ) {
                $variation = wc_get_product( $a['variation_id'] );
                if ( $variation && $variation->is_type( 'variation' ) ) {
                    $pname .= ' — ' . wc_get_formatted_variation( $variation, true, false );
                }
            }

            // Truncate key for email safety
            $truncated_key = substr( $a['key'], 0, 6 ) . '-XXXXX-...-' . substr( $a['key'], -5 );

            $body .= '<tr>';
            $body .= '<td><a href="' . admin_url( 'admin.php?page=lflow-licenses&action=edit&license_id=' . absint( $a['license_id'] ) ) . '">#' . absint( $a['license_id'] ) . '</a></td>';
            $body .= '<td>' . esc_html( $pname ) . '</td>';
            $body .= '<td><code style="font-family:monospace; background:#f0f0f1; padding:2px 4px; border-radius:3px;">' . esc_html( $truncated_key ) . '</code></td>';
            $body .= '<td style="color:#d63638; font-weight:600;">' . esc_html( $a['message'] ) . '</td>';
            $body .= '</tr>';
        }

        $body .= '</table>';
        $body .= '<p style="margin-top:20px;"><a href="' . admin_url( 'admin.php?page=lflow-licenses' ) . '" style="display:inline-block; padding:8px 16px; background:#2271b1; color:#fff; text-decoration:none; border-radius:4px; font-weight:600;">' . esc_html__( 'Accéder à la gestion des licences', 'licenceflow' ) . '</a></p>';
        $body .= '<p style="font-size:0.85em; color:#646970; margin-top:30px;">' . esc_html__( 'Cet audit est exécuté automatiquement toutes les 8 heures (à 06h00, 14h00 et 22h00).', 'licenceflow' ) . '</p>';

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

    /**
     * Store the audit logs in WordPress options, capping at 50 logs.
     */
    public function log_audit_result( array $log_entry ): void {
        $logs = get_option( 'lflow_audit_logs', array() );
        if ( ! is_array( $logs ) ) {
            $logs = array();
        }
        array_unshift( $logs, $log_entry );
        if ( count( $logs ) > 50 ) {
            $logs = array_slice( $logs, 0, 50 );
        }
        update_option( 'lflow_audit_logs', $logs );
    }

    /**
     * Verify and automatically reschedule the audit cron if needed.
     */
    public function maybe_schedule_audit_cron(): void {
        $schedule = wp_get_schedule( 'lflow_daily_audit_cron' );
        if ( 'thrice_daily' !== $schedule ) {
            if ( $schedule ) {
                wp_clear_scheduled_hook( 'lflow_daily_audit_cron' );
            }
            
            $time_string = '06:00:00';
            $timezone    = wp_timezone();
            $datetime    = new DateTime( $time_string, $timezone );
            $timestamp   = $datetime->getTimestamp();

            // Calcule le prochain créneau horaire (toutes les 8h)
            while ( $timestamp < time() ) {
                $timestamp += 8 * 3600;
            }

            wp_schedule_event( $timestamp, 'thrice_daily', 'lflow_daily_audit_cron' );
        }
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
