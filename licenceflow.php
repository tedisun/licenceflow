<?php
/**
 * Plugin Name: LicenceFlow
 * Plugin URI:  https://tedisun.com/licenceflow
 * Description: Digital license & subscription delivery for WooCommerce. Sell keys, accounts, invitation links and access codes — automatically delivered on purchase.
 * Version:     1.0.5
 * Author:      Tedisun SARL
 * Author URI:  https://tedisun.com
 * Text Domain: licenceflow
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 * @license GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ───────────────────────────────────────────────────────────────

define( 'LFLOW_VERSION',   '1.0.5' );
define( 'LFLOW_FILE',      __FILE__ );
define( 'LFLOW_PATH',      plugin_dir_path( __FILE__ ) );
define( 'LFLOW_URL',       plugin_dir_url( __FILE__ ) );
define( 'LFLOW_BASENAME',  plugin_basename( __FILE__ ) );

// Encryption defaults — MUST be changed via Settings > Encryption on first setup.
// These values are intentionally non-functional defaults; the plugin will alert the admin
// if they have not been replaced.
define( 'LFLOW_DEFAULT_ENC_KEY', 'CHANGE_THIS_KEY_IN_SETTINGS_NOW!' );
define( 'LFLOW_DEFAULT_ENC_IV',  'CHANGE_THIS_IV!!' );

// ─── Dependency check ────────────────────────────────────────────────────────

add_action( 'plugins_loaded', 'lflow_check_dependencies', 1 );
function lflow_check_dependencies() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>LicenceFlow</strong> requires <strong>WooCommerce</strong> to be installed and active.';
            echo '</p></div>';
        } );
        return;
    }
    lflow_init();
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────

function lflow_init() {
    require_once LFLOW_PATH . 'includes/functions.php';
    require_once LFLOW_PATH . 'includes/class-licenceflow-security.php';
    require_once LFLOW_PATH . 'includes/class-licenceflow-settings.php';
    require_once LFLOW_PATH . 'includes/class-licenceflow-license-db.php';
    require_once LFLOW_PATH . 'includes/class-licenceflow-product-config.php';
    require_once LFLOW_PATH . 'includes/class-licenceflow-core.php';
    require_once LFLOW_PATH . 'includes/class-licenceflow-updater.php';

    if ( is_admin() ) {
        require_once LFLOW_PATH . 'includes/admin/class-licenceflow-admin.php';
        require_once LFLOW_PATH . 'includes/metaboxes/class-licenceflow-product-metabox.php';
        require_once LFLOW_PATH . 'includes/metaboxes/class-licenceflow-order-metabox.php';
    }

    // Boot singletons
    LicenceFlow_Security::get_instance();
    LicenceFlow_Settings::get_instance();
    LicenceFlow_Core::get_instance();
    LicenceFlow_Updater::get_instance();

    if ( is_admin() ) {
        LicenceFlow_Admin::get_instance();
        LicenceFlow_Product_Metabox::get_instance();
        LicenceFlow_Order_Metabox::get_instance();
    }

    // REST API
    add_action( 'rest_api_init', 'lflow_register_api' );

    // DB upgrade check
    lflow_maybe_upgrade_db();
}

function lflow_register_api() {
    require_once LFLOW_PATH . 'includes/api/v1/api.php';
    $api = new LicenceFlow_API_V1();
    $api->register_routes();
}

// ─── Activation / Deactivation ───────────────────────────────────────────────

register_activation_hook( __FILE__, 'lflow_activate' );
register_deactivation_hook( __FILE__, 'lflow_deactivate' );

function lflow_activate() {
    lflow_create_tables();
    lflow_set_defaults();
    // Schedule daily cron for expiry alerts
    if ( ! wp_next_scheduled( 'lflow_daily_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'lflow_daily_cron' );
    }
    flush_rewrite_rules();
}

function lflow_deactivate() {
    wp_clear_scheduled_hook( 'lflow_daily_cron' );
    flush_rewrite_rules();
}

// ─── Database setup ──────────────────────────────────────────────────────────

function lflow_create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();

    // Main licenses table
    $sql = "CREATE TABLE {$wpdb->prefix}lflow_licenses (
        license_id                INT(11)      NOT NULL AUTO_INCREMENT,
        product_id                INT(11)      NOT NULL DEFAULT 0,
        variation_id              INT(11)      NOT NULL DEFAULT 0,
        license_key               TEXT         CHARACTER SET utf8,
        license_type              VARCHAR(20)  NOT NULL DEFAULT 'key',
        license_status            VARCHAR(50)  NOT NULL DEFAULT 'available',
        owner_first_name          VARCHAR(255) DEFAULT NULL,
        owner_last_name           VARCHAR(255) DEFAULT NULL,
        owner_email_address       VARCHAR(255) DEFAULT NULL,
        delivre_x_times           INT(11)      NOT NULL DEFAULT 1,
        remaining_delivre_x_times INT(11)      NOT NULL DEFAULT 1,
        activation_date           DATE         DEFAULT NULL,
        creation_date             DATE         DEFAULT NULL,
        sold_date                 DATE         DEFAULT NULL,
        expiration_date           DATE         DEFAULT NULL,
        valid                     INT(11)      NOT NULL DEFAULT 0,
        order_id                  INT(11)      DEFAULT NULL,
        admin_notes               TEXT         DEFAULT NULL,
        PRIMARY KEY (license_id),
        KEY product_id (product_id),
        KEY variation_id (variation_id),
        KEY license_status (license_status),
        KEY order_id (order_id)
    ) $charset;";
    dbDelta( $sql );

    // Licensed products config
    $sql = "CREATE TABLE {$wpdb->prefix}lflow_licensed_products (
        config_id    INT(11)     NOT NULL AUTO_INCREMENT,
        product_id   INT(11)     NOT NULL DEFAULT 0,
        variation_id INT(11)     NOT NULL DEFAULT 0,
        active       TINYINT(1)  NOT NULL DEFAULT 0,
        license_type VARCHAR(20) NOT NULL DEFAULT 'key',
        delivery_qty INT(11)     NOT NULL DEFAULT 1,
        show_in      VARCHAR(10) NOT NULL DEFAULT 'both',
        PRIMARY KEY (config_id),
        UNIQUE KEY product_variation (product_id, variation_id)
    ) $charset;";
    dbDelta( $sql );

    // License metadata
    $sql = "CREATE TABLE {$wpdb->prefix}lflow_license_meta (
        meta_id    INT(11)      NOT NULL AUTO_INCREMENT,
        license_id INT(11)      NOT NULL,
        meta_key   VARCHAR(255) NOT NULL,
        meta_value LONGTEXT     DEFAULT NULL,
        PRIMARY KEY (meta_id),
        KEY license_id (license_id),
        KEY meta_key (meta_key)
    ) $charset;";
    dbDelta( $sql );

    update_option( 'lflow_db_version', LFLOW_VERSION );
}

/**
 * Run ALTER TABLE migrations for existing installs.
 * Safe to call on every request — checks DB version first.
 */
function lflow_maybe_upgrade_db() {
    $installed = get_option( 'lflow_db_version', '0' );
    if ( version_compare( $installed, LFLOW_VERSION, '>=' ) ) {
        return;
    }

    global $wpdb;

    // Add admin_notes to licenses if missing
    $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}lflow_licenses LIKE 'admin_notes'" );
    if ( empty( $cols ) ) {
        $wpdb->query( "ALTER TABLE {$wpdb->prefix}lflow_licenses ADD COLUMN admin_notes TEXT DEFAULT NULL" );
    }

    // Add new columns to licensed_products if missing
    $new_cols = array(
        'license_type' => "ADD COLUMN license_type VARCHAR(20) NOT NULL DEFAULT 'key'",
        'delivery_qty' => "ADD COLUMN delivery_qty INT(11) NOT NULL DEFAULT 1",
        'show_in'      => "ADD COLUMN show_in VARCHAR(10) NOT NULL DEFAULT 'both'",
    );
    foreach ( $new_cols as $col => $ddl ) {
        $exists = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}lflow_licensed_products LIKE '$col'" );
        if ( empty( $exists ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}lflow_licensed_products $ddl" );
        }
    }

    update_option( 'lflow_db_version', LFLOW_VERSION );
}

function lflow_set_defaults() {
    $defaults = array(
        'lflow_nb_rows_by_page'         => 15,
        'lflow_show_adminbar_notifs'     => 'on',
        'lflow_guest_customer'           => 'on',
        'lflow_meta_key_name'            => 'Licence',
        'lflow_meta_key_name_plural'     => 'Licences',
        'lflow_key_delivery'             => 'fifo',
        'lflow_send_when_completed'      => 'on',
        'lflow_send_when_processing'     => '',
        'lflow_stock_sync'               => '',
        'lflow_show_on_top'              => '',
        'lflow_hide_keys_on_site'        => '',
        'lflow_enable_cart_validation'   => '',
        'lflow_different_keys'           => 'on',
        'lflow_auto_expire'              => '',
        'lflow_auto_redeem'              => '',
        'lflow_alert_days_before'        => 7,
        'lflow_enc_key'                  => LFLOW_DEFAULT_ENC_KEY,
        'lflow_enc_iv'                   => LFLOW_DEFAULT_ENC_IV,
        'lflow_api_key'                  => wp_generate_password( 20, false ),
        'lflow_private_api_key'          => wp_generate_uuid4(),
    );
    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            add_option( $key, $value );
        }
    }
}
