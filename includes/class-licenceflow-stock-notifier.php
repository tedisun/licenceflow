<?php
/**
 * LicenceFlow — Stock Alert Notifier
 *
 * Sends email and/or WhatsApp notifications when a product's available
 * license stock drops to or below the configured threshold.
 *
 * Architecture:
 *  - Hooks into custom actions fired by LicenceFlow_Core and admin pages,
 *    so this class never needs to be called directly outside of lflow_init().
 *  - lflow_stock_after_delivery(product_id, variation_id) → maybe_notify()
 *  - lflow_stock_after_restore(product_id, variation_id)  → maybe_reset()
 *
 * State storage:
 *  - Single WP option 'lflow_stock_alert_state' (array keyed by "{pid}_{vid}")
 *    so wp_options stays clean regardless of product count.
 *
 * Threshold change:
 *  - Changing the global threshold clears all flags so the next delivery
 *    re-evaluates against the new value.
 *
 * WhatsApp delivery priority:
 *  1. Filter 'lflow_whatsapp_send' (third-party override, return true to stop)
 *  2. WTAN_Api::send() + WTAN_Logger (WootsApp Notifier, if active)
 *  3. External webhook URL — payload: {"phone":"…","message":"…"}
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_Stock_Notifier {

    /** @var self|null */
    private static ?self $instance = null;

    private function __construct() {
        // Core WooCommerce delivery / restore events
        add_action( 'lflow_stock_after_delivery', array( $this, 'maybe_notify' ), 10, 2 );
        add_action( 'lflow_stock_after_restore',  array( $this, 'maybe_reset' ),  10, 2 );

        // Daily cron — catches stock drops from expirations
        add_action( 'lflow_daily_cron', array( $this, 'cron_check' ) );

        // Clear all flags when the global threshold option changes
        add_action( 'update_option_lflow_stock_alert_threshold', array( $this, 'clear_all_state' ) );
        add_action( 'update_option_lflow_stock_alert_groups',    array( $this, 'clear_all_state' ) );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Called after every license delivery (via lflow_stock_after_delivery action).
     * Sends an alert if stock is at/below threshold and no alert was already sent
     * for this crossing. Resets the flag if stock has recovered above threshold.
     */
    public function maybe_notify( int $product_id, int $variation_id = 0 ): void {
        if ( ! LicenceFlow_Settings::is_on( 'lflow_stock_alert_enabled' ) ) {
            return;
        }
        if ( $product_id <= 0 ) {
            return;
        }

        $stock     = LicenceFlow_License_DB::count_available( $product_id, $variation_id );
        $threshold = $this->get_effective_threshold( $product_id, $variation_id );
        $key       = $this->state_key( $product_id, $variation_id );

        if ( $stock <= $threshold ) {
            if ( ! $this->product_has_licenses( $product_id, $variation_id ) ) {
                return; // Not managed by LicenceFlow — skip
            }
            if ( ! $this->is_notified( $key ) ) {
                $this->send_notifications( $product_id, $variation_id, $stock );
                $this->mark_notified( $key );
            }
        } else {
            $this->clear_notified( $key ); // Stock recovered — reset for next crossing
        }
    }

    /**
     * Called when licenses are added or restored (via lflow_stock_after_restore action).
     * Clears the "already notified" flag when stock goes above the threshold so the
     * next threshold-crossing triggers a fresh alert.
     */
    public function maybe_reset( int $product_id, int $variation_id = 0 ): void {
        if ( $product_id <= 0 ) {
            return;
        }
        $stock     = LicenceFlow_License_DB::count_available( $product_id, $variation_id );
        $threshold = $this->get_effective_threshold( $product_id, $variation_id );
        if ( $stock > $threshold ) {
            $this->clear_notified( $this->state_key( $product_id, $variation_id ) );
        }
    }

    /**
     * Clears all alert state flags.
     * Called when the global threshold option changes so every product
     * is re-evaluated against the new value on the next delivery.
     */
    public function clear_all_state(): void {
        delete_option( 'lflow_stock_alert_state' );
    }

    /**
     * Daily cron callback — scans all known product/variation combos.
     * Covers stock drops caused by license expirations (no delivery involved).
     */
    public function cron_check(): void {
        if ( ! LicenceFlow_Settings::is_on( 'lflow_stock_alert_enabled' ) ) {
            return;
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT DISTINCT product_id, variation_id FROM {$wpdb->prefix}lflow_licenses",
            ARRAY_A
        ) ?: array();

        foreach ( $rows as $row ) {
            $this->maybe_notify( (int) $row['product_id'], (int) $row['variation_id'] );
        }
    }

    // ── Notification dispatch ─────────────────────────────────────────────────

    private function send_notifications( int $product_id, int $variation_id, int $stock ): void {
        $label     = $this->get_product_label( $product_id, $variation_id );
        $admin_url = admin_url( 'admin.php?page=lflow-licenses&product_id=' . $product_id );

        $this->send_email( $label, $stock, $admin_url );
        $this->send_whatsapp( $label, $stock, $admin_url );
    }

    // ── Email ─────────────────────────────────────────────────────────────────

    private function send_email( string $label, int $stock, string $admin_url ): void {
        $raw = LicenceFlow_Settings::get( 'lflow_stock_alert_emails', '' );
        if ( empty( $raw ) ) {
            $raw = LicenceFlow_Settings::get( 'lflow_alert_email', get_option( 'admin_email' ) );
        }

        $emails = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        if ( empty( $emails ) ) {
            return;
        }

        $subject = sprintf( '[LicenceFlow] Stock bas — %s', $label );
        $body    = $this->build_email_html( $label, $stock, $admin_url );
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        );

        foreach ( $emails as $email ) {
            if ( is_email( $email ) ) {
                wp_mail( $email, $subject, $body, $headers );
            }
        }
    }

    private function build_email_html( string $label, int $stock, string $admin_url ): string {
        $site = esc_html( get_bloginfo( 'name' ) );
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:30px 0;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:6px;overflow:hidden;border:1px solid #ddd;">
    <tr><td style="background:#c0392b;padding:18px 28px;">
        <span style="color:#fff;font-size:17px;font-weight:bold;">🔴 Stock bas — LicenceFlow</span>
    </td></tr>
    <tr><td style="padding:28px;">
        <p style="margin:0 0 14px;font-size:15px;color:#1d2327;">
            Le stock du produit <strong><?php echo esc_html( $label ); ?></strong> est bas.
        </p>
        <table width="100%" cellpadding="8" cellspacing="0"
               style="background:#fff8f8;border:1px solid #f0b8b8;border-radius:4px;margin-bottom:22px;">
            <tr>
                <td style="color:#555;font-size:13px;width:40%;">Produit</td>
                <td style="font-weight:600;font-size:13px;"><?php echo esc_html( $label ); ?></td>
            </tr>
            <tr>
                <td style="color:#555;font-size:13px;border-top:1px solid #f0b8b8;">Stock disponible</td>
                <td style="font-weight:700;font-size:14px;color:#c0392b;border-top:1px solid #f0b8b8;">
                    <?php echo (int) $stock; ?> unité<?php echo $stock > 1 ? 's' : ''; ?>
                </td>
            </tr>
        </table>
        <p style="margin:0 0 20px;font-size:14px;color:#555;">
            Pensez à réapprovisionner ce produit pour éviter une rupture de stock.
        </p>
        <a href="<?php echo esc_url( $admin_url ); ?>"
           style="display:inline-block;background:#2271b1;color:#fff;text-decoration:none;
                  padding:10px 20px;border-radius:4px;font-size:14px;font-weight:600;">
            Gérer les licences →
        </a>
    </td></tr>
    <tr><td style="padding:14px 28px;background:#f9f9f9;border-top:1px solid #eee;">
        <p style="margin:0;font-size:12px;color:#999;"><?php echo $site; ?> · LicenceFlow</p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    // ── WhatsApp ──────────────────────────────────────────────────────────────

    private function send_whatsapp( string $label, int $stock, string $admin_url ): void {
        $raw_phones = LicenceFlow_Settings::get( 'lflow_stock_alert_whatsapp', '' );
        if ( empty( trim( $raw_phones ) ) ) {
            return;
        }

        $phones = array_filter( array_map( 'trim', explode( ',', $raw_phones ) ) );
        if ( empty( $phones ) ) {
            return;
        }

        $message = sprintf(
            "🔴 *Stock bas — %s*\nStock actuel : %d unité(s) disponible(s)\n%s",
            $label,
            $stock,
            $admin_url
        );

        foreach ( $phones as $raw_phone ) {
            $this->dispatch_whatsapp( $raw_phone, $message );
        }
    }

    private function dispatch_whatsapp( string $raw_phone, string $message ): void {
        // Allow third-party plugins to fully handle sending (return true to short-circuit)
        $handled = apply_filters( 'lflow_whatsapp_send', false, $raw_phone, $message );
        if ( $handled ) {
            return;
        }

        // WootsApp Notifier (WTAN) — credentials read from WP options automatically
        if ( class_exists( 'WTAN_Api' ) ) {
            $country = LicenceFlow_Settings::get( 'lflow_stock_alert_whatsapp_country', 'BF' );
            $phone   = ( class_exists( 'WTAN_Phone' ) && $country )
                ? WTAN_Phone::normalize( $raw_phone, strtoupper( $country ) )
                : $raw_phone;

            if ( $phone ) {
                $result = WTAN_Api::send( $phone, $message );
                if ( class_exists( 'WTAN_Logger' ) ) {
                    WTAN_Logger::insert( 0, $phone, $result['success'] ?? false, $result['body'] ?? '' );
                }
            }
            return;
        }

        // Fallback: external webhook (n8n, Make, Zapier…)
        $webhook = trim( LicenceFlow_Settings::get( 'lflow_stock_alert_webhook_url', '' ) );
        if ( ! empty( $webhook ) && filter_var( $webhook, FILTER_VALIDATE_URL ) ) {
            wp_remote_post( $webhook, array(
                'body'     => wp_json_encode( array( 'phone' => $raw_phone, 'message' => $message ) ),
                'headers'  => array( 'Content-Type' => 'application/json' ),
                'timeout'  => 5,
                'blocking' => false, // fire-and-forget — never block delivery
            ) );
        }
    }

    // ── State storage (single WP option, array) ───────────────────────────────

    private function state_key( int $product_id, int $variation_id ): string {
        return $product_id . '_' . $variation_id;
    }

    private function get_state(): array {
        return (array) get_option( 'lflow_stock_alert_state', array() );
    }

    private function is_notified( string $key ): bool {
        return isset( $this->get_state()[ $key ] );
    }

    private function mark_notified( string $key ): void {
        $state         = $this->get_state();
        $state[ $key ] = 1;
        update_option( 'lflow_stock_alert_state', $state, false );
    }

    private function clear_notified( string $key ): void {
        $state = $this->get_state();
        if ( isset( $state[ $key ] ) ) {
            unset( $state[ $key ] );
            update_option( 'lflow_stock_alert_state', $state, false );
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve the effective threshold for a given product or variation.
     * Looks up groups (cached in memory), legacy option overrides, and defaults.
     */
    private function get_effective_threshold( int $product_id, int $variation_id = 0 ): int {
        static $cached_groups = null;

        if ( null === $cached_groups ) {
            $cached_groups = get_option( 'lflow_stock_alert_groups', array() );
            if ( ! is_array( $cached_groups ) ) {
                $cached_groups = array();
            }
        }

        // 1. Passe 1 : Recherche spécifique par variation
        if ( $variation_id > 0 ) {
            foreach ( $cached_groups as $group ) {
                $products = array_map( 'intval', (array) ( $group['products'] ?? array() ) );
                if ( in_array( $variation_id, $products, true ) ) {
                    return max( 0, (int) ( $group['threshold'] ?? 2 ) );
                }
            }
        }

        // 2. Passe 2 : Recherche par produit parent (ou simple)
        foreach ( $cached_groups as $group ) {
            $products = array_map( 'intval', (array) ( $group['products'] ?? array() ) );
            if ( in_array( $product_id, $products, true ) ) {
                return max( 0, (int) ( $group['threshold'] ?? 2 ) );
            }
        }

        // 3. Fallback: Option spécifique historique
        $override = get_option( 'lflow_stock_alert_threshold_' . $product_id, null );
        if ( null !== $override && is_numeric( $override ) ) {
            return max( 0, (int) $override );
        }

        // 4. Seuil global
        return max( 0, (int) LicenceFlow_Settings::get( 'lflow_stock_alert_threshold', 2 ) );
    }

    /**
     * Builds a human-readable "Product — Variation" label.
     */
    private function get_product_label( int $product_id, int $variation_id ): string {
        $product = $product_id ? wc_get_product( $product_id ) : null;
        if ( ! $product ) {
            return 'Produit #' . $product_id;
        }
        $name = $product->get_name();
        if ( $variation_id > 0 ) {
            $variation = wc_get_product( $variation_id );
            if ( $variation && $variation->is_type( 'variation' ) ) {
                $attrs = wc_get_formatted_variation( $variation, true, false );
                if ( $attrs ) {
                    $name .= ' — ' . $attrs;
                }
            }
        }
        return $name;
    }

    /**
     * Returns true if LicenceFlow has at least one license record (any status)
     * for this product/variation. Products with zero records are not managed.
     */
    private function product_has_licenses( int $product_id, int $variation_id ): bool {
        global $wpdb;
        if ( $variation_id > 0 ) {
            return (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}lflow_licenses WHERE product_id = %d AND variation_id = %d",
                $product_id, $variation_id
            ) );
        }
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lflow_licenses WHERE product_id = %d",
            $product_id
        ) );
    }
}
