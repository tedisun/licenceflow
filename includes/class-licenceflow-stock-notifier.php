<?php
/**
 * LicenceFlow — Stock Alert Notifier
 *
 * Sends email and/or WhatsApp notifications when a product's available
 * license stock drops to or below the configured threshold.
 *
 * State is tracked per product/variation via WP options
 * (lflow_sa_{product_id}_{variation_id}) so each threshold-crossing
 * triggers exactly one alert; the flag is cleared as soon as stock
 * recovers above the threshold.
 *
 * WhatsApp delivery priority:
 *  1. Filter lflow_whatsapp_send (third-party override)
 *  2. WootsApp Notifier function/class (if active)
 *  3. External webhook URL (n8n, etc.)
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_Stock_Notifier {

    /** @var self|null */
    private static ?self $instance = null;

    private function __construct() {
        add_action( 'lflow_daily_cron', array( $this, 'cron_check' ) );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Called after every real license delivery.
     * Sends an alert if stock is at/below threshold and no alert was already
     * sent for this threshold crossing. Resets the flag if stock is above.
     */
    public function maybe_notify( int $product_id, int $variation_id = 0 ): void {
        if ( ! LicenceFlow_Settings::is_on( 'lflow_stock_alert_enabled' ) ) {
            return;
        }
        if ( $product_id <= 0 ) {
            return;
        }

        $stock     = LicenceFlow_License_DB::count_available( $product_id, $variation_id );
        $threshold = $this->get_effective_threshold( $product_id );
        $state_key = $this->state_key( $product_id, $variation_id );

        if ( $stock <= $threshold ) {
            if ( ! $this->product_has_licenses( $product_id, $variation_id ) ) {
                return; // Not managed by LicenceFlow — skip
            }
            if ( ! get_option( $state_key ) ) {
                $this->send_notifications( $product_id, $variation_id, $stock );
                update_option( $state_key, 1, false );
            }
        } else {
            delete_option( $state_key ); // Stock recovered — reset for next crossing
        }
    }

    /**
     * Called when new licenses are added (create/bulk).
     * Resets the "already notified" flag so the next threshold-crossing
     * triggers a fresh alert.
     */
    public function maybe_reset( int $product_id, int $variation_id = 0 ): void {
        if ( $product_id <= 0 ) {
            return;
        }
        $stock     = LicenceFlow_License_DB::count_available( $product_id, $variation_id );
        $threshold = $this->get_effective_threshold( $product_id );
        if ( $stock > $threshold ) {
            delete_option( $this->state_key( $product_id, $variation_id ) );
        }
    }

    /**
     * Daily cron callback — scans all known product/variation combos.
     * Catches expirations that silently reduce stock without going through deliver.
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
        $raw_phone = trim( LicenceFlow_Settings::get( 'lflow_stock_alert_whatsapp', '' ) );
        if ( empty( $raw_phone ) ) {
            return;
        }

        $message = sprintf(
            "🔴 *Stock bas — %s*\nStock actuel : %d unité(s) disponible(s)\n%s",
            $label,
            $stock,
            $admin_url
        );

        // Allow third-party plugins to fully handle sending (return true to short-circuit)
        $handled = apply_filters( 'lflow_whatsapp_send', false, $raw_phone, $message );
        if ( $handled ) {
            return;
        }

        // WootsApp Notifier (WTAN) — uses Evolution API credentials stored in WP options
        if ( class_exists( 'WTAN_Api' ) ) {
            $phone = class_exists( 'WTAN_Phone' ) ? WTAN_Phone::normalize( $raw_phone, 'BF' ) : $raw_phone;
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
                'blocking' => false, // fire-and-forget — never block the delivery response
            ) );
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the WP option key used to track the "already notified" state.
     */
    private function state_key( int $product_id, int $variation_id ): string {
        return 'lflow_sa_' . $product_id . '_' . $variation_id;
    }

    /**
     * Per-product threshold override (option lflow_stock_alert_threshold_{product_id})
     * falls back to the global setting.
     */
    private function get_effective_threshold( int $product_id ): int {
        $override = get_option( 'lflow_stock_alert_threshold_' . $product_id, null );
        if ( null !== $override && is_numeric( $override ) ) {
            return max( 0, (int) $override );
        }
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
     * Returns true if LicenceFlow has at least one license record for this
     * product/variation (any status). Products with zero records are not
     * managed by LicenceFlow and should not trigger alerts.
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
