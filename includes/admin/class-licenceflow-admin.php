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
        add_action( 'wp_ajax_lflow_list_licenses',        array( $this, 'ajax_list_licenses' ) );
        add_action( 'wp_ajax_lflow_get_variations',       array( $this, 'ajax_get_variations' ) );
        add_action( 'wp_ajax_lflow_save_license',         array( $this, 'ajax_save_license' ) );
        add_action( 'wp_ajax_lflow_delete_license',       array( $this, 'ajax_delete_license' ) );
        add_action( 'wp_ajax_lflow_bulk_action',          array( $this, 'ajax_bulk_action' ) );
        add_action( 'wp_ajax_lflow_sync_stock',           array( $this, 'ajax_sync_stock' ) );
        add_action( 'wp_ajax_lflow_sync_all_stock',       array( $this, 'ajax_sync_all_stock' ) );
        add_action( 'wp_ajax_lflow_migrate_enc_keys',     array( $this, 'ajax_migrate_enc_keys' ) );
        add_action( 'wp_ajax_lflow_regenerate_api_key',   array( $this, 'ajax_regenerate_api_key' ) );
        add_action( 'wp_ajax_lflow_check_update',          array( $this, 'ajax_check_update' ) );
        add_action( 'wp_ajax_lflow_test_license_key',      array( $this, 'ajax_test_license_key' ) );
        add_action( 'wp_ajax_lflow_trigger_manual_audit',  array( $this, 'ajax_trigger_manual_audit' ) );
        add_action( 'wp_ajax_lflow_get_active_scan_progress', array( $this, 'ajax_get_active_scan_progress' ) );

        // Quick CSV export (admin-post)
        add_action( 'admin_post_lflow_quick_export', array( $this, 'handle_quick_export' ) );
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

        add_submenu_page(
            'licenceflow',
            __( 'Logs d\'audit', 'licenceflow' ),
            __( 'Logs d\'audit', 'licenceflow' ),
            'manage_woocommerce',
            'lflow-audit-logs',
            array( $this, 'render_audit_logs' )
        );

        add_submenu_page(
            'licenceflow',
            __( 'Documentation API', 'licenceflow' ),
            __( 'API', 'licenceflow' ),
            'manage_woocommerce',
            'lflow-api-docs',
            array( $this, 'render_api_docs' )
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
            'licenceflow_page_lflow-api-docs',
            'licenceflow_page_lflow-audit-logs',
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

        // OpenSSL manquant — CRITIQUE : les licences sont stockées en clair
        if ( ! extension_loaded( 'openssl' ) ) {
            echo '<div class="notice notice-error"><p>';
            echo wp_kses(
                __( '<strong>LicenceFlow — CRITIQUE :</strong> L\'extension PHP <code>openssl</code> est absente sur ce serveur. Les licences sont stockées <strong>en clair</strong> dans la base de données. Contactez votre hébergeur pour activer OpenSSL.', 'licenceflow' ),
                array( 'strong' => array(), 'code' => array() )
            );
            echo '</p></div>';
        }

        // Clés de chiffrement par défaut
        if ( LicenceFlow_Settings::has_default_encryption_keys() ) {
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

    public function render_api_docs(): void {
        LicenceFlow_Security::get_instance()->require_capability();
        require LFLOW_PATH . 'includes/admin/page-api-docs.php';
    }

    public function render_audit_logs(): void {
        LicenceFlow_Security::get_instance()->require_capability();
        require LFLOW_PATH . 'includes/admin/page-audit-logs.php';
    }

    // ── AJAX: list licenses (live search / AJAX filter) ───────────────────────

    /**
     * Returns the rendered WP_List_Table HTML for the licenses page.
     * Called by the live-search JS instead of a full page reload.
     *
     * POST params: s, product_id, variation_id, license_type, license_status,
     *              orderby, order, paged
     */
    public function ajax_list_licenses(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        // Bridge POST → GET so WP_List_Table::prepare_items() reads the right values
        $saved_get = $_GET;
        $_GET = array(
            'page'           => 'lflow-licenses',
            's'              => sanitize_text_field( $_POST['s'] ?? '' ),
            'product_id'     => absint( $_POST['product_id'] ?? 0 ),
            'variation_id'   => absint( $_POST['variation_id'] ?? 0 ),
            'license_type'   => sanitize_key( $_POST['license_type'] ?? '' ),
            'license_status' => sanitize_key( $_POST['license_status'] ?? '' ),
            'orderby'        => sanitize_key( $_POST['orderby'] ?? 'license_id' ),
            'order'          => strtoupper( sanitize_key( $_POST['order'] ?? 'DESC' ) ),
            'paged'          => max( 1, absint( $_POST['paged'] ?? 1 ) ),
        );

        require_once LFLOW_PATH . 'includes/admin/class-licenceflow-list-table.php';

        ob_start();
        $table = new LicenceFlow_List_Table();
        $table->prepare_items();
        $table->display();
        $html = ob_get_clean();

        $_GET = $saved_get;

        wp_send_json_success( array( 'html' => $html ) );
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

        $variation_id  = absint( $_POST['variation_id'] ?? 0 );
        $variation_map = LicenceFlow_Product_Config::get_variation_options( $product_id );
        $config        = LicenceFlow_Product_Config::get_config( $product_id, $variation_id );
        $license_type  = $config['license_type'] ?? 'key';
        $default_valid = (int) ( $config['default_valid'] ?? 0 );

        // Convert to indexed array of {id, label} for consistent JS iteration
        $variations = array();
        foreach ( $variation_map as $vid => $vlabel ) {
            $variations[] = array( 'id' => $vid, 'label' => $vlabel );
        }

        wp_send_json_success( array(
            'variations'    => $variations,
            'license_type'  => $license_type,
            'default_valid' => $default_valid,
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
        $raw_value = $_POST['license_value'] ?? '';

        // Parse || note syntax for single-value types: KEY || note visible client
        $inline_note = '';
        if ( in_array( $type, array( 'key', 'code' ), true ) ) {
            $raw_text = is_array( $raw_value ) ? (string) ( $raw_value['key'] ?? '' ) : (string) $raw_value;
            if ( strpos( $raw_text, '||' ) !== false ) {
                $parts = explode( '||', $raw_text, 2 );
                if ( is_array( $raw_value ) ) {
                    $raw_value['key'] = trim( $parts[0] );
                } else {
                    $raw_value = trim( $parts[0] );
                }
                $inline_note = trim( $parts[1] );
            }
        }

        $clean_value = $security->sanitize_license_field( $raw_value, $type );
        $serialized  = lflow_serialize_license_value( $clean_value, $type );

        $delivre_x_times = max( 1, $security->sanitize_int( $_POST['delivre_x_times'] ?? 1 ) );
        $data = array(
            'product_id'      => $product_id,
            'variation_id'    => $variation_id,
            'license_key'     => $serialized,
            'license_type'    => $type,
            'license_status'  => sanitize_key( $_POST['license_status'] ?? 'available' ),
            'expiration_date' => $security->sanitize_date( $_POST['expiration_date'] ?? '' ),
            'valid'           => $security->sanitize_int( $_POST['valid'] ?? 0 ),
            'license_note'    => sanitize_textarea_field( ! empty( $_POST['license_note'] ) ? $_POST['license_note'] : $inline_note ),
            'admin_notes'     => sanitize_textarea_field( $_POST['admin_notes'] ?? '' ),
            'delivre_x_times' => $delivre_x_times,
        );

        // Remove empty expiration_date to avoid storing '0000-00-00'
        if ( $data['expiration_date'] === '' ) {
            unset( $data['expiration_date'] );
        }

        if ( $license_id > 0 ) {
            // On update: also update remaining if admin explicitly submitted it
            $remaining = $security->sanitize_int( $_POST['remaining_delivre_x_times'] ?? -1 );
            if ( $remaining >= 0 ) {
                $data['remaining_delivre_x_times'] = min( $remaining, $delivre_x_times );
            }
            $ok = LicenceFlow_License_DB::update( $license_id, $data );
            $id = $ok ? $license_id : false;
        } else {
            // On insert: remaining starts at delivre_x_times
            $data['remaining_delivre_x_times'] = $delivre_x_times;
            $id = LicenceFlow_License_DB::insert( $data );
        }

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Erreur lors de l\'enregistrement.', 'licenceflow' ) ) );
        }

        // Sync stock after insert/update
        LicenceFlow_Core::get_instance()->sync_product_stock( $product_id, $variation_id );

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

        // Capture product/variation before delete for stock sync
        $license = LicenceFlow_License_DB::get( $license_id );

        $ok = LicenceFlow_License_DB::delete( $license_id );
        if ( ! $ok ) {
            wp_send_json_error( array( 'message' => __( 'Erreur lors de la suppression.', 'licenceflow' ) ) );
        }

        if ( $license ) {
            LicenceFlow_Core::get_instance()->sync_product_stock( (int) $license['product_id'], (int) ( $license['variation_id'] ?? 0 ) );
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
            // Capture product/variation pairs before delete for stock sync
            if ( LicenceFlow_Settings::is_on( 'lflow_stock_sync' ) ) {
                global $wpdb;
                $ids_sql      = implode( ',', array_map( 'intval', $license_ids ) );
                $delete_pairs = $wpdb->get_results(
                    "SELECT DISTINCT product_id, variation_id
                     FROM {$wpdb->prefix}lflow_licenses
                     WHERE license_id IN ($ids_sql)",
                    ARRAY_A
                );
            }

            LicenceFlow_License_DB::bulk_delete( $license_ids );

            if ( ! empty( $delete_pairs ) ) {
                $core = LicenceFlow_Core::get_instance();
                foreach ( $delete_pairs as $pair ) {
                    $core->sync_product_stock( (int) $pair['product_id'], (int) $pair['variation_id'] );
                }
            }

            wp_send_json_success( array( 'message' => sprintf(
                /* translators: %d: number of licenses */
                __( '%d licence(s) supprimée(s).', 'licenceflow' ),
                count( $license_ids )
            ) ) );
        }

        $valid_statuses = array_keys( lflow_license_statuses() );
        if ( in_array( $action, $valid_statuses, true ) ) {
            LicenceFlow_License_DB::bulk_update_status( $license_ids, $action );

            // Sync WooCommerce stock for every product affected by this bulk change.
            if ( LicenceFlow_Settings::is_on( 'lflow_stock_sync' ) ) {
                global $wpdb;
                $ids_sql = implode( ',', array_map( 'intval', $license_ids ) );
                $pairs   = $wpdb->get_results(
                    "SELECT DISTINCT product_id, variation_id
                     FROM {$wpdb->prefix}lflow_licenses
                     WHERE license_id IN ($ids_sql)",
                    ARRAY_A
                );
                $core = LicenceFlow_Core::get_instance();
                foreach ( $pairs as $pair ) {
                    $core->sync_product_stock( (int) $pair['product_id'], (int) $pair['variation_id'] );
                }
            }

            wp_send_json_success( array( 'message' => sprintf(
                /* translators: %d: number of licenses */
                __( '%d licence(s) mise(s) à jour.', 'licenceflow' ),
                count( $license_ids )
            ) ) );
        } elseif ( $action === 'verify_online' ) {
            // Bulk Microsoft validation
            $licenses = array();
            $whitelisted_ids = LicenceFlow_Settings::get( 'lflow_auditable_product_ids', array() );
            foreach ( $license_ids as $lid ) {
                $license = LicenceFlow_License_DB::get( $lid );
                if ( $license && ( $license['license_type'] ?? 'key' ) === 'key' ) {
                    $license_var_id = (int) ( $license['variation_id'] ?? 0 );
                    $match_id       = $license_var_id > 0 ? $license_var_id : (int) $license['product_id'];
                    if ( ! in_array( $match_id, $whitelisted_ids, true ) ) {
                        continue; // Skip products/variations not whitelisted for online audit
                    }
                    $plain_key = lflow_decrypt( $license['license_key'] ?? '' );
                    if ( preg_match( '/^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/i', $plain_key ) ) {
                        $licenses[ $lid ] = array(
                            'row' => $license,
                            'key' => $plain_key,
                        );
                    }
                }
            }

            if ( empty( $licenses ) ) {
                wp_send_json_error( array( 'message' => __( 'Aucune clé Microsoft 5x5 valide trouvée parmi la sélection.', 'licenceflow' ) ) );
            }

            $checked = 0;
            $valid_count = 0;
            $blocked_count = 0;
            $failures = 0;
            $bulk_anomalies = array();

            $batch_size = 30;
            $chunks = array_chunk( $licenses, $batch_size, true );

            $url = 'https://licenceflow-checker.app.tedisun.com/v1/check';
            $api_key = 'sk_lf_7b58cde3e5e4fa6b51df2f6d2e8b6a382e71d3c05b8aef52968840c1d683a219';

            $products_to_sync = array();

            foreach ( $chunks as $chunk ) {
                $batch_keys = array_column( $chunk, 'key' );

                $response = wp_remote_post( $url, array(
                    'headers' => array(
                        'Content-Type'          => 'application/json',
                        'X-LicenceFlow-Api-Key' => $api_key,
                    ),
                    'body'    => wp_json_encode( array( 'keys' => $batch_keys ) ),
                    'timeout' => 30,
                ) );

                if ( is_wp_error( $response ) ) {
                    $failures += count( $chunk );
                    continue;
                }

                $body_res = wp_remote_retrieve_body( $response );
                $data = json_decode( $body_res, true );

                if ( empty( $data['success'] ) || empty( $data['results'] ) ) {
                    $failures += count( $chunk );
                    continue;
                }

                // Map results by key
                $results_by_key = array();
                foreach ( $data['results'] as $res ) {
                    if ( ! empty( $res['key'] ) ) {
                        $results_by_key[ strtoupper( $res['key'] ) ] = $res;
                    }
                }

                foreach ( $chunk as $lid => $item ) {
                    $plain_key = strtoupper( $item['key'] );
                    if ( ! isset( $results_by_key[ $plain_key ] ) ) {
                        $failures++;
                        continue;
                    }

                    $res = $results_by_key[ $plain_key ];
                    $checked++;

                    $row = $item['row'];
                    $status = $res['status'] ?? 'unknown';
                    $remaining = $res['remaining_activations'] ?? null;

                    // Détection d'une clé MAK générique (Office ou Windows)
                    $is_mak = ( ! empty( $res['product_name'] ) && strpos( strtolower( $res['product_name'] ), 'mak' ) !== false );

                    // Disjoncteur pour Office 2024 Professionnel Plus LTSC (ID 14335 ou nom/slug correspondant)
                    $is_office_2024_ltsc = ( (int) $row['product_id'] === 14335 );
                    if ( ! $is_office_2024_ltsc ) {
                        $product = wc_get_product( $row['product_id'] );
                        if ( $product ) {
                            $product_name_lower = strtolower( $product->get_name() );
                            $product_slug_lower = strtolower( $product->get_slug() );
                            if ( strpos( $product_slug_lower, 'office-2024-pro-plus-ltsc' ) !== false || 
                                 strpos( $product_name_lower, 'office 2024 professionnel plus ltsc' ) !== false ) {
                                $is_office_2024_ltsc = true;
                            }
                        }
                    }

                    // Application du circuit de sécurité (MAK générique ou Office 2024 LTSC) - Seulement si pas d'erreur API
                    if ( $status !== 'error' && ( $is_mak || $is_office_2024_ltsc ) && ( $remaining === null || $remaining === 'N/A' || ( is_numeric( $remaining ) && (int) $remaining <= 0 ) ) ) {
                        $status = 'blocked';
                        $res['message'] = __( 'Le nombre d\'activations restantes est à zéro ou indisponible.', 'licenceflow' );
                        $res['error_code'] = 'NO_ACTIVATIONS_LEFT';
                    }

                    if ( $status === 'blocked' || $status === 'phone_activation' ) {
                        $blocked_count++;
                        $row = $item['row'];
                        $msg = $res['message'] ?? 'Bloquée par Microsoft.';
                        if ( $status === 'phone_activation' ) {
                            $msg = __( 'Activation par téléphone uniquement (non autorisée pour la vente en ligne).', 'licenceflow' );
                        }
                        $err = $res['error_code'] ?? '0x0';
                        $date = current_time( 'Y-m-d H:i:s' );
                        
                        $admin_note = trim( $row['admin_notes'] ?? '' );
                        $new_note = "[Test en masse $date] Retirée du stock : $msg (Code: $err).";
                        $admin_note = $admin_note ? $admin_note . "\n" . $new_note : $new_note;

                        // Mark status as inactive
                        LicenceFlow_License_DB::update( $lid, array(
                            'license_status' => 'inactive',
                            'admin_notes'    => $admin_note,
                        ) );

                        // Mark product for stock sync
                        $sync_key = $row['product_id'] . '_' . $row['variation_id'];
                        $products_to_sync[ $sync_key ] = array(
                            'product_id'   => (int) $row['product_id'],
                            'variation_id' => (int) $row['variation_id'],
                        );

                        // Capture bulk anomalies
                        $bulk_anomalies[] = array(
                            'license_id'   => $lid,
                            'product_id'   => (int) $row['product_id'],
                            'variation_id' => (int) $row['variation_id'],
                            'key'          => $item['key'],
                            'message'      => $msg,
                        );
                    } elseif ( $status === 'error' ) {
                        $failures++;
                    } else {
                        $valid_count++;
                    }
                }
            }

            // Sync stocks
            if ( ! empty( $products_to_sync ) ) {
                $core = LicenceFlow_Core::get_instance();
                foreach ( $products_to_sync as $prod ) {
                    $core->sync_product_stock( $prod['product_id'], $prod['variation_id'] );
                }
            }

            // Log manual bulk action in lflow_audit_logs only if at least one key gets deactivated
            if ( $checked > 0 && $blocked_count > 0 ) {
                LicenceFlow_Core::get_instance()->log_audit_result( array(
                    'date'      => current_time( 'Y-m-d H:i:s' ),
                    'status'    => 'completed',
                    'checked'   => $checked,
                    'blocked'   => $blocked_count,
                    'message'   => sprintf( __( 'Vérification manuelle (en masse) : %d clé(s) bloquée(s)/inactive(s) retirée(s) du stock.', 'licenceflow' ), $blocked_count ),
                    'anomalies' => $bulk_anomalies,
                ) );
            }

            $skipped = count( $license_ids ) - count( $licenses );
            $msg = sprintf(
                /* translators: 1: checked keys, 2: valid keys, 3: blocked keys, 4: failures, 5: skipped */
                __( 'Vérification en ligne terminée : %1$d clé(s) Microsoft testée(s) (%2$d valide(s), %3$d inactive(s)/bloquée(s) désactivée(s)), %4$d échec(s), %5$d clé(s) non-Microsoft ignorée(s).', 'licenceflow' ),
                $checked,
                $valid_count,
                $blocked_count,
                $failures,
                $skipped
            );

            wp_send_json_success( array( 'message' => $msg ) );
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

    // ── AJAX: sync all products stock ────────────────────────────────────────

    public function ajax_sync_all_stock(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        if ( ! LicenceFlow_Settings::is_on( 'lflow_stock_sync' ) ) {
            wp_send_json_error( array(
                'message' => __( 'La synchronisation du stock est désactivée dans les réglages.', 'licenceflow' ),
            ) );
        }

        $count = LicenceFlow_Core::get_instance()->sync_all_products_stock();

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %d: number of products synced */
                _n(
                    '%d produit synchronisé.',
                    '%d produits synchronisés.',
                    $count,
                    'licenceflow'
                ),
                $count
            ),
            'count' => $count,
        ) );
    }

    // ── AJAX: migrate encryption keys ────────────────────────────────────────

    public function ajax_migrate_enc_keys(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        $old_key = sanitize_text_field( $_POST['old_key'] ?? '' );
        $old_iv  = sanitize_text_field( $_POST['old_iv']  ?? '' );
        $new_key = sanitize_text_field( $_POST['new_key'] ?? '' );
        $new_iv  = sanitize_text_field( $_POST['new_iv']  ?? '' );

        if ( empty( $old_key ) || empty( $old_iv ) || empty( $new_key ) || empty( $new_iv ) ) {
            wp_send_json_error( array( 'message' => __( 'Tous les champs sont requis.', 'licenceflow' ) ) );
        }
        if ( strlen( $new_key ) < 16 ) {
            wp_send_json_error( array( 'message' => __( 'La nouvelle clé doit contenir au moins 16 caractères.', 'licenceflow' ) ) );
        }
        if ( strlen( $new_iv ) !== 16 ) {
            wp_send_json_error( array( 'message' => __( 'Le nouvel IV doit contenir exactement 16 caractères.', 'licenceflow' ) ) );
        }
        if ( $old_key === $new_key && $old_iv === $new_iv ) {
            wp_send_json_error( array( 'message' => __( 'Les nouvelles clés sont identiques aux anciennes — aucune migration nécessaire.', 'licenceflow' ) ) );
        }

        $result = LicenceFlow_License_DB::migrate_encryption_keys( $old_key, $old_iv, $new_key, $new_iv );

        // Only update the stored options if migration had no unrecoverable errors
        if ( $result['errors'] === 0 ) {
            update_option( 'lflow_enc_key', $new_key );
            update_option( 'lflow_enc_iv',  $new_iv );
        }

        wp_send_json_success( array(
            'migrated' => $result['migrated'],
            'skipped'  => $result['skipped'],
            'errors'   => $result['errors'],
            'total'    => $result['total'],
            'keys_updated' => $result['errors'] === 0,
            'message'  => $result['errors'] === 0
                ? sprintf(
                    /* translators: 1: migrated count 2: total count */
                    __( '%1$d/%2$d licences re-chiffrées avec succès. Nouvelles clés actives.', 'licenceflow' ),
                    $result['migrated'] + $result['skipped'],
                    $result['total']
                )
                : sprintf(
                    /* translators: 1: error count 2: total count */
                    __( 'Migration partielle : %1$d erreur(s) sur %2$d licences. Les clés n\'ont pas été mises à jour — vérifiez les clés sources et réessayez.', 'licenceflow' ),
                    $result['errors'],
                    $result['total']
                ),
        ) );
    }

    // ── AJAX: regenerate API key ──────────────────────────────────────────────

    public function ajax_regenerate_api_key(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        $new_key = wp_generate_password( 32, false );
        update_option( 'lflow_api_key', $new_key );

        wp_send_json_success( array( 'api_key' => $new_key ) );
    }

    // ── AJAX: check for update ────────────────────────────────────────────────

    public function ajax_check_update(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'licenceflow' ) ) );
        }

        $status = LicenceFlow_Updater::get_instance()->fetch_update_status();

        if ( ! empty( $status['error'] ) ) {
            wp_send_json_error( array( 'message' => $status['message'] ?? __( 'Erreur inconnue.', 'licenceflow' ) ) );
        }

        wp_send_json_success( $status );
    }

    // ── AJAX: test license key ────────────────────────────────────────────────

    public function ajax_test_license_key(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        $license_id = absint( $_POST['license_id'] ?? 0 );
        if ( ! $license_id ) {
            wp_send_json_error( array( 'message' => __( 'ID de licence invalide.', 'licenceflow' ) ) );
        }

        $license = LicenceFlow_License_DB::get( $license_id );
        if ( ! $license ) {
            wp_send_json_error( array( 'message' => __( 'Licence non trouvée.', 'licenceflow' ) ) );
        }

        $type = $license['license_type'] ?? 'key';
        if ( $type !== 'key' ) {
            wp_send_json_error( array( 'message' => __( 'Seules les licences de type "Clé de licence" peuvent être testées.', 'licenceflow' ) ) );
        }

        $whitelisted_ids = LicenceFlow_Settings::get( 'lflow_auditable_product_ids', array() );
        $license_var_id  = (int) ( $license['variation_id'] ?? 0 );
        $match_id        = $license_var_id > 0 ? $license_var_id : (int) $license['product_id'];

        if ( ! in_array( $match_id, $whitelisted_ids, true ) ) {
            wp_send_json_error( array( 'message' => __( "Ce produit ou cette variation n'est pas configuré(e) pour la vérification en ligne. Activez-le/la dans les Réglages.", 'licenceflow' ) ) );
        }

        $plain_key = lflow_decrypt( $license['license_key'] ?? '' );
        if ( ! preg_match( '/^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/i', $plain_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Seules les clés Microsoft au format 5x5 (XXXXX-XXXXX-XXXXX-XXXXX-XXXXX) sont vérifiables en ligne.', 'licenceflow' ) ) );
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

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => __( 'Erreur de connexion à l\'API du vérificateur : ', 'licenceflow' ) . $response->get_error_message() ) );
        }

        $body_res = wp_remote_retrieve_body( $response );
        $data = json_decode( $body_res, true );

        if ( empty( $data['success'] ) || empty( $data['results'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Réponse invalide de l\'API du vérificateur.', 'licenceflow' ) ) );
        }

        $result = $data['results'][0];

        // Format result message
        $product_name = $result['product_name'] ?? '';
        $status = $result['status'] ?? 'unknown';
        $error_code = $result['error_code'] ?? '';
        $remaining = $result['remaining_activations'] ?? null;
        $msg = $result['message'] ?? '';

        // Détection d'une clé MAK générique (Office ou Windows)
        $is_mak = ( ! empty( $result['product_name'] ) && strpos( strtolower( $result['product_name'] ), 'mak' ) !== false );

        // Disjoncteur pour Office 2024 Professionnel Plus LTSC (ID 14335 ou nom/slug correspondant)
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

        // Application du circuit de sécurité (MAK générique ou Office 2024 LTSC) - Seulement si pas d'erreur API
        if ( $status !== 'error' && ( $is_mak || $is_office_2024_ltsc ) && ( $remaining === null || $remaining === 'N/A' || ( is_numeric( $remaining ) && (int) $remaining <= 0 ) ) ) {
            $status = 'blocked';
            $msg = __( 'Le nombre d\'activations restantes est à zéro ou indisponible.', 'licenceflow' );
            $error_code = 'NO_ACTIVATIONS_LEFT';
        }

        $formatted_msg = '';
        if ( $status === 'blocked' || $status === 'phone_activation' ) {
            $msg_clean = ( $status === 'phone_activation' ) ? __( 'Désactivée car nécessite une activation par téléphone alors qu\'une activation en ligne est promise.', 'licenceflow' ) : $msg;
            $formatted_msg = sprintf(
                /* translators: 1: message, 2: error code */
                __( 'Clé Bloquée / Inactive : %1$s (Code: %2$s)', 'licenceflow' ),
                $msg_clean,
                $error_code
            );

            // Automatically deactivate key if it is blocked or requires phone activation
            $date = current_time( 'Y-m-d H:i:s' );
            $admin_note = trim( $license['admin_notes'] ?? '' );
            $new_note = "[Test direct $date] Retirée du stock : $msg_clean (Code: $error_code).";
            $admin_note = $admin_note ? $admin_note . "\n" . $new_note : $new_note;

            LicenceFlow_License_DB::update( $license_id, array(
                'license_status' => 'inactive',
                'admin_notes'    => $admin_note,
            ) );

            // Sync stock
            LicenceFlow_Core::get_instance()->sync_product_stock( (int) $license['product_id'], (int) $license['variation_id'] );
            $valid = false;

            // Log manual single check in lflow_audit_logs only if the key gets deactivated
            LicenceFlow_Core::get_instance()->log_audit_result( array(
                'date'      => current_time( 'Y-m-d H:i:s' ),
                'status'    => 'completed',
                'checked'   => 1,
                'blocked'   => 1,
                'message'   => sprintf( __( 'Vérification manuelle (clé #%d) : Clé désactivée.', 'licenceflow' ), $license_id ),
                'anomalies' => array(
                    array(
                        'license_id'   => $license_id,
                        'product_id'   => (int) $license['product_id'],
                        'variation_id' => (int) $license['variation_id'],
                        'key'          => $plain_key,
                        'message'      => $msg_clean,
                    )
                ),
            ) );
        } elseif ( $status === 'error' ) {
            wp_send_json_error( array( 'message' => __( 'Erreur de connexion ou Timeout de l\'API. Veuillez réessayer.', 'licenceflow' ) ) );
        } else {
            // Valid (online_key)
            $formatted_msg = sprintf(
                /* translators: 1: product name, 2: message */
                __( 'Clé Valide : %1$s. %2$s', 'licenceflow' ),
                $product_name,
                $msg
            );
            if ( $remaining !== null ) {
                $formatted_msg .= ' (' . sprintf( __( '%d activations restantes', 'licenceflow' ), $remaining ) . ')';
            }
            $valid = true;
        }

        wp_send_json_success( array(
            'valid'         => $valid,
            'message'       => $formatted_msg,
            'product_name'  => $product_name,
            'status'        => $status,
            'error_code'    => $error_code,
            'remaining'     => $remaining,
        ) );
    }

    // ── Quick CSV export ──────────────────────────────────────────────────────

    public function handle_quick_export(): void {
        if ( ! check_admin_referer( 'lflow_quick_export' ) ) {
            wp_die( esc_html__( 'Nonce invalide.', 'licenceflow' ) );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Permission refusée.', 'licenceflow' ) );
        }

        $args = array(
            'status'       => sanitize_key( $_GET['license_status'] ?? '' ),
            'product_id'   => absint( $_GET['product_id'] ?? 0 ),
            'variation_id' => absint( $_GET['variation_id'] ?? 0 ),
            'type'         => sanitize_key( $_GET['license_type'] ?? '' ),
            'search'       => sanitize_text_field( $_GET['s'] ?? '' ),
            'per_page'     => 5000,
        );

        $result   = LicenceFlow_License_DB::get_list( $args );
        $licenses = $result['items'];

        $filename = 'licenceflow-export-' . gmdate( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        // BOM for Excel UTF-8
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array(
            'ID', 'Produit', 'Variation', 'Type', 'Statut',
            'Valeur', 'Livraisons max', 'Livraisons restantes',
            'Propriétaire', 'Email', 'Commande', 'Date vente',
            'Date expiration (admin)', 'Validité client (jours)', 'Note',
        ) );

        foreach ( $licenses as $license ) {
            $decrypted = lflow_decrypt( $license['license_key'] ?? '' );
            fputcsv( $out, array(
                $license['license_id'],
                $license['product_id'],
                $license['variation_id'] ?: '',
                $license['license_type'],
                $license['license_status'],
                $decrypted,
                $license['delivre_x_times'],
                $license['remaining_delivre_x_times'],
                trim( ( $license['owner_first_name'] ?? '' ) . ' ' . ( $license['owner_last_name'] ?? '' ) ),
                $license['owner_email_address'] ?? '',
                $license['order_id'] ?: '',
                $license['sold_date'] ?? '',
                $license['expiration_date'] ?? '',
                $license['valid'] ?? 0,
                $license['license_note'] ?? '',
            ) );
        }

        fclose( $out );
        exit;
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
 
    /**
     * AJAX handler to trigger a manual license key audit.
     */
    public function ajax_trigger_manual_audit(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        // Check if there is already a running scan to prevent duplicate scans
        $logs = get_option( 'lflow_audit_logs', array() );
        if ( is_array( $logs ) ) {
            foreach ( $logs as $log ) {
                if ( isset( $log['status'] ) && $log['status'] === 'running' ) {
                    $start_time = strtotime( $log['date'] ?? '' );
                    if ( $start_time && ( time() - $start_time ) < 3600 ) {
                        wp_send_json_error( array(
                            'message' => __( 'Une analyse est déjà en cours. Veuillez attendre qu\'elle se termine.', 'licenceflow' ),
                        ) );
                    }
                }
            }
        }

        $scheduled = LicenceFlow_Core::get_instance()->start_audit_scan( true );

        if ( $scheduled === 0 ) {
            wp_send_json_error( array(
                'message' => __( 'Aucune clé disponible à vérifier ou configuration manquante.', 'licenceflow' ),
            ) );
        }

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %d: number of keys */
                _n(
                    'Analyse déclenchée : %d clé a été mise en file d\'attente (vérifications toutes les 30s).',
                    'Analyse déclenchée : %d clés ont été mises en file d\'attente (vérifications toutes les 30s).',
                    $scheduled,
                    'licenceflow'
                ),
                $scheduled
            ),
        ) );
    }

    /**
     * AJAX handler to get active scan progress.
     */
    public function ajax_get_active_scan_progress(): void {
        LicenceFlow_Security::get_instance()->check_ajax_nonce( 'admin' );
        LicenceFlow_Security::get_instance()->require_capability();

        $logs = get_option( 'lflow_audit_logs', array() );
        $active_scan = null;

        if ( is_array( $logs ) && ! empty( $logs ) ) {
            $first_log = $logs[0];
            if ( isset( $first_log['status'] ) && $first_log['status'] === 'running' ) {
                $active_scan = $first_log;
            }
        }

        if ( $active_scan ) {
            $ts = isset( $active_scan['date'] ) ? strtotime( $active_scan['date'] ) : time();
            $formatted_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );

            wp_send_json_success( array(
                'active'    => true,
                'scan_id'   => $active_scan['scan_id'] ?? '',
                'date'      => $formatted_date,
                'status'    => $active_scan['status'],
                'checked'   => $active_scan['checked'],
                'total'     => $active_scan['total'],
                'blocked'   => $active_scan['blocked'],
                'message'   => $active_scan['message'],
                'anomalies' => $active_scan['anomalies'] ?? array(),
            ) );
        } else {
            wp_send_json_success( array(
                'active' => false,
            ) );
        }
    }
}
