<?php
/**
 * LicenceFlow — Settings
 *
 * Registers all plugin options and provides a typed getter.
 * Single source of truth for defaults (matches lflow_set_defaults()).
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_Settings {

    /** @var self|null */
    private static $instance = null;

    private function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Defaults ──────────────────────────────────────────────────────────────

    /**
     * All default option values. Mirrors lflow_set_defaults() in licenceflow.php.
     */
    public static function all_defaults(): array {
        return array(
            // General
            'lflow_nb_rows_by_page'       => 15,
            'lflow_show_adminbar_notifs'   => 'on',
            'lflow_guest_customer'         => 'on',
            'lflow_meta_key_name'          => 'Licence',
            'lflow_meta_key_name_plural'   => 'Licences',
            'lflow_key_delivery'           => 'fifo',
            'lflow_different_keys'         => 'on',
            'lflow_hide_keys_on_site'      => '',
            'lflow_enable_cart_validation' => '',
            'lflow_stock_sync'             => '',
            'lflow_show_on_top'            => '',
            // Encryption
            'lflow_enc_key'                => LFLOW_DEFAULT_ENC_KEY,
            'lflow_enc_iv'                 => LFLOW_DEFAULT_ENC_IV,
            // Notifications
            'lflow_auto_expire'            => '',
            'lflow_auto_redeem'            => '',
            'lflow_alert_days_before'      => 7,
            'lflow_alert_email'            => get_option( 'admin_email' ),
            // Order status
            'lflow_send_when_completed'    => 'on',
            'lflow_send_when_processing'   => '',
            // API
            'lflow_api_key'                => '',
            'lflow_private_api_key'        => '',
        );
    }

    // ── Getter ────────────────────────────────────────────────────────────────

    /**
     * Get a plugin option value, with fallback to default.
     *
     * @param string $key     Option key (e.g. 'lflow_nb_rows_by_page')
     * @param mixed  $default Override default (null = use all_defaults())
     * @return mixed
     */
    public static function get( string $key, $default = null ) {
        if ( $default === null ) {
            $defaults = self::all_defaults();
            $default  = $defaults[ $key ] ?? '';
        }
        return get_option( $key, $default );
    }

    /**
     * Check if a toggle option is enabled ('on' value).
     */
    public static function is_on( string $key ): bool {
        return self::get( $key ) === 'on';
    }

    /**
     * Check if the encryption keys are still at their insecure defaults.
     */
    public static function has_default_encryption_keys(): bool {
        return self::get( 'lflow_enc_key' ) === LFLOW_DEFAULT_ENC_KEY
            || self::get( 'lflow_enc_iv' ) === LFLOW_DEFAULT_ENC_IV;
    }

    // ── Registration ──────────────────────────────────────────────────────────

    public function register_settings(): void {
        // General tab
        $general_options = array(
            'lflow_nb_rows_by_page', 'lflow_show_adminbar_notifs', 'lflow_guest_customer',
            'lflow_meta_key_name', 'lflow_meta_key_name_plural', 'lflow_key_delivery',
            'lflow_different_keys', 'lflow_hide_keys_on_site', 'lflow_enable_cart_validation',
            'lflow_stock_sync', 'lflow_show_on_top',
        );
        foreach ( $general_options as $opt ) {
            register_setting( 'lflow_settings_general', $opt, array( 'sanitize_callback' => array( $this, 'sanitize_option' ) ) );
        }

        // Encryption tab
        register_setting( 'lflow_settings_encryption', 'lflow_enc_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'lflow_settings_encryption', 'lflow_enc_iv',  array( 'sanitize_callback' => 'sanitize_text_field' ) );

        // Notifications tab
        $notif_options = array( 'lflow_auto_expire', 'lflow_auto_redeem', 'lflow_alert_days_before', 'lflow_alert_email' );
        foreach ( $notif_options as $opt ) {
            register_setting( 'lflow_settings_notifications', $opt, array( 'sanitize_callback' => array( $this, 'sanitize_option' ) ) );
        }

        // Order status tab
        register_setting( 'lflow_settings_order_status', 'lflow_send_when_completed',  array( 'sanitize_callback' => array( $this, 'sanitize_option' ) ) );
        register_setting( 'lflow_settings_order_status', 'lflow_send_when_processing', array( 'sanitize_callback' => array( $this, 'sanitize_option' ) ) );
    }

    /**
     * Generic sanitize callback for text/toggle options.
     */
    public function sanitize_option( $value ) {
        if ( $value === 'on' || $value === '' ) {
            return $value;
        }
        if ( is_numeric( $value ) ) {
            return absint( $value );
        }
        return sanitize_text_field( $value );
    }
}
