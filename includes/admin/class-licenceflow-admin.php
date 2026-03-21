<?php
/**
 * LicenceFlow — Admin shell
 *
 * Registers menus, enqueues assets, and handles all wp_ajax_lflow_* actions.
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_Admin {

    /** @var self|null */
    private static $instance = null;

    private function __construct() {
        add_action( 'admin_menu',             array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
        add_action( 'admin_notices',          array( $this, 'maybe_show_encryption_notice' ) );

        // AJAX handlers
        add_action( 'wp_ajax_lflow_get_variations',       array( $this, 'ajax_get_variations' ) );
        add_action( 'wp_ajax_lflow_save_license',         array( $this, 'ajax_save_license' ) );
        add_action( 'wp_ajax_lflow_delete_license',       array( $this, 'ajax_delete_license' ) );
        add_action( 'wp_ajax_lflow_bulk_action',          array( $this, 'ajax_bulk_action' ) );
        add_action( 'wp_ajax_lflow_sync_stock',           array( $this, 'ajax_sync_stock' ) );
        add_action( 'wp_ajax_lflow_regenerate_api_key',   array( $this, 'ajax_regenerate_api_key' ) );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Menu ──────────────────────────────────────────────────────────────────

    public function register_menu(): void {
        add_menu_page(
            __( 'LicenceFlow', 'licenceflow' ),
            __( 'LicenceFlow', 'licenceflow' ),
            'manage_woocommerce',
            'licenceflow',
            array( $this, 'render_getting_started' ),
            'dashicons-lock',
            56
        );

        add_submenu_page(
            'licenceflow',
            __( 'Démarrage', 'licenceflow' ),
            __( 'Démarrage', 'licenceflow' ),
            'manage_woocommerce',
            'licenceflow',
            array( $this, 'render_getting_started' )
        );

        add_submenu_page(
            'licenceflow',
            __( 'Licences', 'licenceflow' ),
            __( 'Licences', 'licenceflow' ),
            'manage_woocommerce',
            'lflow-licenses',
            array( $this, 'render_licenses' )
        );

        // Hidden submenu for "Add license" (accessible via button, not nav)
        add_submenu_page(
            'licenceflow',
            __( 'Ajouter une licence', 'licenceflow' ),
            __( 'Ajouter une licence', 'licenceflow' ),
            'manage_woocommerce',
            'lflow-add-license',
            array( $this, 'render_add_license' )
        );

        add_submenu_page(
            'licenceflow',
            __( 'Statistiques', 'licenceflow' ),
            __( 'Statistiques', 'licenceflow' ),
            'manage_woocommerce',
            'lflow-statistics',
            array( $this, 'render_statistics' )
        );

        add_submenu_page(
            'licenceflow',
            __( 'Import / Export', 'licenceflow' ),
            __( 'Import / Export', 'licenceflow' ),
            'manage_woocommerce',
            'lflow-import-export',
            array( $this, 'render_import_export' )
        );

        add_submenu_page(
            'licenceflow',
            __( 'Réglages', 'licenceflow' ),
            __( 'Réglages', 'licenceflow' ),
            'manage_woocommerce',
            'lflow-settings',
            array( $this, 'render_settings' )
        );
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public function enqueue_assets( string $hook ): void {
        // Only load on LicenceFlow admin pages
        $lflow_pages = array(
            'toplevel_page_licenceflow',
            'licenceflow_page_lflow-licenses',
            'licenceflow_page_lflow-add-license',
            'licenceflow_page_lflow-statistics',
            'licenceflow_page_lflow-import-export',
            'licenceflow_page_lflow-settings',
        );

        $on_product_page = in_array( $hook, array( 'post.php', 'post-new.php' ), true )
            && ( get_post_type() === 'product' || ( isset( $_GET['post_type'] ) && $_GET['post_type'] === 'product' ) );

        if ( ! in_array( $hook, $lflow_pages, true ) && ! $on_product_page ) {
            return;
        }

        wp_enqueue_style(
            'lflow-admin',
            LFLOW_URL . 'assets/css/admin.css',
            array(),
            LFLOW_VERSION
        );

        wp_enqueue_script(
            'lflow-admin',
            LFLOW_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            LFLOW_VERSION,
            true
        );

        wp_localize_script( 'lflow-admin', 'lflow_admin', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => LicenceFlow_Security::get_instance()->create_nonce( 'admin' ),
            'edit_url'      => admin_url( 'admin.php?page=lflow-licenses&action=edit' ),
            'license_types' => lflow_license_types(),
            'i18n'          => array(
                'confirm_delete'  => __( 'Supprimer cette licence ? Cette action est irréversible.', 'licenceflow' ),
                'confirm_bulk'    => __( 'Appliquer cette action aux licences sélectionnées ?', 'licenceflow' ),
                'saving'          => __( 'Enregistrement…', 'licenceflow' ),
                'saved'           => __( 'Enregistré.', 'licenceflow' ),
                'error'           => __( 'Une erreur est survenue.', 'licenceflow' ),
                'no_selection'    => __( 'Aucune licence sélectionnée.', 'licenceflow' ),
            ),
        ) );

        // Load license form JS only on add/edit pages
        if ( in_array( $hook, array( 'licenceflow_page_lflow-add-license', 'licenceflow_page_lflow-licenses' ), true ) ) {
            wp_enqueue_script(
                'lflow-license-form',
                LFLOW_URL . 'assets/js/license-form.js',
                array( 'lflow-admin' ),
                LFLOW_VERSION,
                true
            );
        }
    }

    // ── Admin notice ──────────────────────────────────────────────────────────

    public function maybe_show_encryption_notice(): void {
        if ( ! lflow_current_user_can() ) {
            return;
        }
        if ( ! LicenceFlow_Settings::has_default_encryption_keys() ) {
            return;
        }
        $settings_url = admin_url( 'admin.php?page=lflow-settings&tab=encryption' );
        echo '<div class="notice notice-error"><p>';
        printf(
            wp_kses(
                /* translators: %s: URL to encryption settings */
                __( '<strong>LicenceFlow :</strong> Vos clés de chiffrement sont encore aux valeurs par défaut. <a href="%s">Changez-les maintenant</a> pour protéger vos données.', 'licenceflow' ),
                array( 'strong' => array(), 'a' => array( 'href' => array() ) )
            ),
            esc_url( $settings_url )
        );
        echo '</p></div>';
    }

    // ── Page renderers ────────────────────────────────────────────────────────

    public function render_getting_started(): void {
        LicenceFlow_Security::get_instance()->require_capability();
        require LFLOW_PATH . 'includes/admin/page-getting-started.php';
    }

    public function render_licenses(): void {
        LicenceFlow_Security::get_instance()->require_capability();

        // Dispatch: list view or edit view
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

        if ( $action === 'edit' && ! empty( $_GET['license_id'] ) ) {
            require LFLOW_PATH . 'includes/admin/page-edit-license.php';
        } else {
            require LFLOW_PATH . 'includes/admin/page-licenses.php';
        }
    }

    public function render_add_license(): void {
        LicenceFlow_Security::get_instance()->require_capability();
        require LFLOW_PATH . 'includes/admin/page-add-license.php';
    }

    public function render_statistics(): void {
        LicenceFlow_Security::get_instance()->require_capability();
        require LFLOW_PATH . 'includes/admin/page-statistics.php';
    }

    public function render_import_export(): void {
        LicenceFlow_Security::get_instance()->require_capability();
        require LFLOW_PATH . 'includes/admin/page-import-export.php';
    }

    public function render_settings(): void {
        LicenceFlow_Security::get_instance()->require_capability();
        require LFLOW_PATH . 'includes/admin/page-settings.php';
    }

    // ── AJAX: get variations ──────────────────────────────────────────────────

    /**
     * Returns the variations of a product + the product's license_type config.
     * Action: lflow_get_variations
     * POST: product_id, nonce
     */
    public function ajax_get_variations(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        $product_id = absint( $_POST['product_id'] ?? 0 );
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Produit invalide.', 'licenceflow' ) ) );
        }

        $variations    = LicenceFlow_Product_Config::get_variation_options( $product_id );
        $config        = LicenceFlow_Product_Config::get_config( $product_id, 0 );
        $license_type  = $config['license_type'] ?? 'key';

        wp_send_json_success( array(
            'variations'   => $variations,
            'license_type' => $license_type,
        ) );
    }

    // ── AJAX: save license ────────────────────────────────────────────────────

    /**
     * Insert or update a license from form POST data.
     * Action: lflow_save_license
     */
    public function ajax_save_license(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        $security    = LicenceFlow_Security::get_instance();
        $license_id  = $security->sanitize_int( $_POST['license_id'] ?? 0 );
        $product_id  = $security->sanitize_int( $_POST['product_id'] ?? 0 );
        $variation_id = $security->sanitize_int( $_POST['variation_id'] ?? 0 );
        $type        = sanitize_key( $_POST['license_type'] ?? 'key' );

        // Sanitize the license field value (type-aware)
        $raw_value   = $_POST['license_value'] ?? '';
        $clean_value = $security->sanitize_license_field( $raw_value, $type );
        $serialized  = lflow_serialize_license_value( $clean_value, $type );

        $data = array(
            'product_id'      => $product_id,
            'variation_id'    => $variation_id,
            'license_key'     => $serialized,
            'license_type'    => $type,
            'license_status'  => sanitize_key( $_POST['license_status'] ?? 'available' ),
            'expiration_date' => $security->sanitize_date( $_POST['expiration_date'] ?? '' ),
            'valid'           => $security->sanitize_int( $_POST['valid'] ?? 0 ),
            'admin_notes'     => sanitize_textarea_field( $_POST['admin_notes'] ?? '' ),
            'delivre_x_times' => max( 1, $security->sanitize_int( $_POST['delivre_x_times'] ?? 1 ) ),
        );

        // Remove empty expiration_date to avoid storing '0000-00-00'
        if ( $data['expiration_date'] === '' ) {
            unset( $data['expiration_date'] );
        }

        if ( $license_id > 0 ) {
            $ok = LicenceFlow_License_DB::update( $license_id, $data );
            $id = $ok ? $license_id : false;
        } else {
            $id = LicenceFlow_License_DB::insert( $data );
        }

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Erreur lors de l\'enregistrement.', 'licenceflow' ) ) );
        }

        wp_send_json_success( array(
            'license_id' => $id,
            'message'    => $license_id > 0
                ? __( 'Licence mise à jour.', 'licenceflow' )
                : __( 'Licence ajoutée.', 'licenceflow' ),
        ) );
    }

    // ── AJAX: delete license ──────────────────────────────────────────────────

    public function ajax_delete_license(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        $license_id = absint( $_POST['license_id'] ?? 0 );
        if ( ! $license_id ) {
            wp_send_json_error( array( 'message' => __( 'ID invalide.', 'licenceflow' ) ) );
        }

        $ok = LicenceFlow_License_DB::delete( $license_id );
        if ( ! $ok ) {
            wp_send_json_error( array( 'message' => __( 'Erreur lors de la suppression.', 'licenceflow' ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Licence supprimée.', 'licenceflow' ) ) );
    }

    // ── AJAX: bulk action ─────────────────────────────────────────────────────

    public function ajax_bulk_action(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        $action      = sanitize_key( $_POST['bulk_action'] ?? '' );
        $license_ids = array_map( 'absint', (array) ( $_POST['license_ids'] ?? array() ) );

        if ( empty( $license_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Aucune licence sélectionnée.', 'licenceflow' ) ) );
        }

        if ( $action === 'delete' ) {
            LicenceFlow_License_DB::bulk_delete( $license_ids );
            wp_send_json_success( array( 'message' => sprintf(
                /* translators: %d: number of licenses */
                __( '%d licence(s) supprimée(s).', 'licenceflow' ),
                count( $license_ids )
            ) ) );
        }

        $valid_statuses = array_keys( lflow_license_statuses() );
        if ( in_array( $action, $valid_statuses, true ) ) {
            LicenceFlow_License_DB::bulk_update_status( $license_ids, $action );
            wp_send_json_success( array( 'message' => sprintf(
                /* translators: %d: number of licenses */
                __( '%d licence(s) mise(s) à jour.', 'licenceflow' ),
                count( $license_ids )
            ) ) );
        }

        wp_send_json_error( array( 'message' => __( 'Action invalide.', 'licenceflow' ) ) );
    }

    // ── AJAX: sync stock ──────────────────────────────────────────────────────

    public function ajax_sync_stock(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        $product_id   = absint( $_POST['product_id'] ?? 0 );
        $variation_id = absint( $_POST['variation_id'] ?? 0 );

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Produit invalide.', 'licenceflow' ) ) );
        }

        LicenceFlow_Core::get_instance()->sync_product_stock( $product_id, $variation_id );

        wp_send_json_success( array( 'message' => __( 'Stock synchronisé.', 'licenceflow' ) ) );
    }

    // ── AJAX: regenerate API key ──────────────────────────────────────────────

    public function ajax_regenerate_api_key(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        $new_key = wp_generate_password( 32, false );
        update_option( 'lflow_api_key', $new_key );

        wp_send_json_success( array( 'api_key' => $new_key ) );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Return the URL to the licenses list page (with optional args).
     */
    public static function licenses_url( array $args = array() ): string {
        return add_query_arg(
            array_merge( array( 'page' => 'lflow-licenses' ), $args ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * Return the URL to add a new license.
     */
    public static function add_license_url(): string {
        return admin_url( 'admin.php?page=lflow-add-license' );
    }

    /**
     * Return the URL to edit a license.
     */
    public static function edit_license_url( int $license_id ): string {
        return self::licenses_url( array( 'action' => 'edit', 'license_id' => $license_id ) );
    }
}
