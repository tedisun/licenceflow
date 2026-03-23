<?php
/**
 * LicenceFlow — Product metabox
 *
 * Adds a "LicenceFlow" metabox to the WooCommerce product edit screen.
 * Three tabs: Paramètres, Licences (list), Import (CSV).
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_Product_Metabox {

    /** @var self|null */
    private static $instance = null;

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save' ), 10, 2 );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Register ──────────────────────────────────────────────────────────────

    public function register(): void {
        add_meta_box(
            'lflow-product-metabox',
            __( 'LicenceFlow', 'licenceflow' ),
            array( $this, 'render' ),
            'product',
            'normal',
            'default'
        );
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render( WP_Post $post ): void {
        $product_id = $post->ID;
        $product    = wc_get_product( $product_id );
        $is_variable = $product && $product->is_type( 'variable' );

        // Main product config
        $config = LicenceFlow_Product_Config::get_config( $product_id, 0 );

        wp_nonce_field( 'lflow_save_product_settings', 'lflow_product_nonce' );
        ?>
        <div class="lflow-metabox-inner">

            <!-- Tabs -->
            <div class="lflow-metabox-tabs">
                <a href="#" class="active" data-tab="settings"><?php esc_html_e( 'Paramètres', 'licenceflow' ); ?></a>
                <a href="#" data-tab="licenses"><?php esc_html_e( 'Licences', 'licenceflow' ); ?></a>
                <a href="#" data-tab="import"><?php esc_html_e( 'Import CSV', 'licenceflow' ); ?></a>
            </div>

            <!-- Tab: Paramètres -->
            <div class="lflow-metabox-tab-content active" id="lflow-metabox-settings">

                <!-- Enable licensing -->
                <p>
                    <label style="font-weight:600;">
                        <input type="checkbox" name="lflow_active" value="1" <?php checked( $config['active'], 1 ); ?>>
                        <?php esc_html_e( 'Activer LicenceFlow pour ce produit', 'licenceflow' ); ?>
                    </label>
                </p>

                <table class="form-table" style="margin-top:8px;">
                    <tr>
                        <th style="width:160px; padding:6px 10px;"><label><?php esc_html_e( 'Type de licence', 'licenceflow' ); ?></label></th>
                        <td style="padding:6px;">
                            <select name="lflow_license_type">
                                <?php foreach ( lflow_license_types() as $slug => $label ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $config['license_type'], $slug ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Définit le formulaire d\'ajout de licence pour ce produit.', 'licenceflow' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding:6px 10px;"><label><?php esc_html_e( 'Quantité à livrer', 'licenceflow' ); ?></label></th>
                        <td style="padding:6px;">
                            <input type="number" name="lflow_delivery_qty" value="<?php echo absint( $config['delivery_qty'] ); ?>" min="1" style="width:70px;">
                            <p class="description"><?php esc_html_e( 'Nombre de licences livrées par unité commandée.', 'licenceflow' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding:6px 10px;"><label><?php esc_html_e( 'Afficher dans', 'licenceflow' ); ?></label></th>
                        <td style="padding:6px;">
                            <select name="lflow_show_in">
                                <option value="both"   <?php selected( $config['show_in'], 'both' ); ?>><?php esc_html_e( 'Email + Site', 'licenceflow' ); ?></option>
                                <option value="email"  <?php selected( $config['show_in'], 'email' ); ?>><?php esc_html_e( 'Email uniquement', 'licenceflow' ); ?></option>
                                <option value="website" <?php selected( $config['show_in'], 'website' ); ?>><?php esc_html_e( 'Site uniquement', 'licenceflow' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php if ( $is_variable ) : ?>
                    <hr style="margin:16px 0;">
                    <h4 style="margin-bottom:10px;"><?php esc_html_e( 'Paramètres par variation', 'licenceflow' ); ?></h4>
                    <?php
                    $variation_configs = LicenceFlow_Product_Config::get_variation_configs( $product_id );
                    foreach ( $product->get_children() as $variation_id ) :
                        $variation = wc_get_product( $variation_id );
                        if ( ! $variation ) continue;
                        $vcfg = $variation_configs[ $variation_id ] ?? array(
                            'active' => 0, 'license_type' => $config['license_type'],
                            'delivery_qty' => 1, 'show_in' => 'both',
                        );
                        ?>
                        <div class="lflow-variation-row">
                            <h4><?php echo esc_html( $variation->get_formatted_name() ); ?></h4>
                            <label>
                                <input type="checkbox" name="lflow_variation[<?php echo absint( $variation_id ); ?>][active]" value="1" <?php checked( $vcfg['active'], 1 ); ?>>
                                <?php esc_html_e( 'Activer', 'licenceflow' ); ?>
                            </label>
                            &nbsp;&nbsp;
                            <label><?php esc_html_e( 'Type :', 'licenceflow' ); ?>
                                <select name="lflow_variation[<?php echo absint( $variation_id ); ?>][license_type]">
                                    <?php foreach ( lflow_license_types() as $slug => $label ) : ?>
                                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $vcfg['license_type'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            &nbsp;&nbsp;
                            <label><?php esc_html_e( 'Qté :', 'licenceflow' ); ?>
                                <input type="number" name="lflow_variation[<?php echo absint( $variation_id ); ?>][delivery_qty]" value="<?php echo absint( $vcfg['delivery_qty'] ); ?>" min="1" style="width:60px;">
                            </label>
                        </div>
                        <?php
                    endforeach;
                endif;
                ?>

            </div><!-- /settings -->

            <!-- Tab: Licences -->
            <div class="lflow-metabox-tab-content" id="lflow-metabox-licenses">
                <?php
                $available = LicenceFlow_License_DB::count_available( $product_id );
                $recent    = LicenceFlow_License_DB::get_list( array(
                    'product_id' => $product_id,
                    'per_page'   => 8,
                    'orderby'    => 'license_id',
                    'order'      => 'DESC',
                ) );
                $lic_type  = LicenceFlow_Product_Config::get_license_type( $product_id, 0 );
                ?>
                <p style="margin-bottom:8px;">
                    <strong id="lflow-quick-available-count"><?php echo absint( $available ); ?></strong>
                    <?php esc_html_e( 'licence(s) disponible(s)', 'licenceflow' ); ?>
                    &mdash;
                    <a href="#" id="lflow-quick-add-toggle" style="font-weight:600;">
                        + <?php esc_html_e( 'Ajout rapide', 'licenceflow' ); ?>
                    </a>
                    &nbsp;|&nbsp;
                    <a href="<?php echo esc_url( LicenceFlow_Admin::add_license_url() . '&product_id=' . $product_id ); ?>">
                        <?php esc_html_e( 'Formulaire complet', 'licenceflow' ); ?>
                    </a>
                    &nbsp;|&nbsp;
                    <a href="<?php echo esc_url( LicenceFlow_Admin::licenses_url( array( 'product_id' => $product_id ) ) ); ?>">
                        <?php esc_html_e( 'Voir toutes', 'licenceflow' ); ?>
                    </a>
                </p>

                <!-- Quick-add inline form -->
                <div id="lflow-quick-add-form" style="display:none; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px 12px; margin-bottom:10px;">
                    <form>
                        <input type="hidden" name="product_id" value="<?php echo absint( $product_id ); ?>">
                        <input type="hidden" name="license_type" value="<?php echo esc_attr( $lic_type ); ?>">
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <?php if ( $lic_type === 'key' || $lic_type === 'code' ) : ?>
                                <input type="text" name="license_value[key]" placeholder="<?php esc_attr_e( 'Clé / Code', 'licenceflow' ); ?>" style="flex:1; min-width:200px; font-family:monospace;">
                            <?php elseif ( $lic_type === 'account' ) : ?>
                                <input type="text" name="license_value[key]" placeholder="identifiant:motdepasse" style="flex:1; min-width:200px; font-family:monospace;">
                            <?php else : ?>
                                <input type="text" name="license_value[key]" placeholder="https://…" style="flex:1; min-width:200px; font-family:monospace;">
                            <?php endif; ?>
                            <button type="submit" class="button button-primary lflow-quick-add-submit">+ <?php esc_html_e( 'Ajouter', 'licenceflow' ); ?></button>
                        </div>
                        <p class="description" style="margin-top:4px; font-size:.8em;">
                            <?php esc_html_e( 'Appuyez sur Entrée ou cliquez Ajouter pour enchaîner les saisies sans quitter la page.', 'licenceflow' ); ?>
                        </p>
                    </form>
                </div>

                <?php if ( ! empty( $recent['items'] ) ) : ?>
                <table class="widefat" style="font-size:.82rem;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php esc_html_e( 'Statut', 'licenceflow' ); ?></th>
                            <th><?php esc_html_e( 'Clé / Valeur', 'licenceflow' ); ?></th>
                            <th><?php esc_html_e( 'Client', 'licenceflow' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="lflow-quick-licenses-tbody">
                        <?php foreach ( $recent['items'] as $row ) :
                            $k = $row['license_key'] ?? '';
                            $short_k = mb_strlen( $k ) > 28 ? mb_substr( $k, 0, 26 ) . '…' : $k;
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url( LicenceFlow_Admin::edit_license_url( (int) $row['license_id'] ) ); ?>">#<?php echo absint( $row['license_id'] ); ?></a></td>
                            <td><span class="lflow-status-badge lflow-status-<?php echo esc_attr( $row['license_status'] ); ?>"><?php echo esc_html( lflow_license_statuses()[ $row['license_status'] ] ?? $row['license_status'] ); ?></span></td>
                            <td><code style="font-size:.78em;"><?php echo esc_html( $short_k ); ?></code></td>
                            <td><?php echo esc_html( $row['owner_email_address'] ?: '—' ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                    <p style="color:#646970;" id="lflow-quick-licenses-tbody"><?php esc_html_e( 'Aucune licence pour ce produit.', 'licenceflow' ); ?></p>
                <?php endif; ?>
            </div><!-- /licenses -->

            <!-- Tab: Import CSV -->
            <div class="lflow-metabox-tab-content" id="lflow-metabox-import">
                <p><?php esc_html_e( 'Importer des licences pour ce produit depuis un fichier CSV.', 'licenceflow' ); ?></p>
                <p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=lflow-import-export&product_id=' . $product_id ) ); ?>" class="button">
                        <?php esc_html_e( 'Aller à Import / Export', 'licenceflow' ); ?>
                    </a>
                </p>
                <p class="description"><?php esc_html_e( 'Format CSV : license_type, license_value, expiration_date (optionnel), valid (optionnel), admin_notes (optionnel).', 'licenceflow' ); ?></p>
            </div><!-- /import -->

        </div><!-- /lflow-metabox-inner -->
        <?php
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save( $post_id, $post = null ): void {
        // Verify nonce
        if ( ! isset( $_POST['lflow_product_nonce'] ) ) return;
        if ( ! LicenceFlow_Security::get_instance()->verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['lflow_product_nonce'] ) ),
            'save_product_settings'
        ) ) return;

        // Capability check
        if ( ! lflow_current_user_can() ) return;

        // Don't save on autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        // Save main product config
        $data = array(
            'active'       => isset( $_POST['lflow_active'] ) ? 1 : 0,
            'license_type' => sanitize_key( $_POST['lflow_license_type'] ?? 'key' ),
            'delivery_qty' => max( 1, absint( $_POST['lflow_delivery_qty'] ?? 1 ) ),
            'show_in'      => sanitize_key( $_POST['lflow_show_in'] ?? 'both' ),
        );
        LicenceFlow_Product_Config::save_config( $post_id, 0, $data );

        // Save variation configs
        if ( ! empty( $_POST['lflow_variation'] ) && is_array( $_POST['lflow_variation'] ) ) {
            foreach ( $_POST['lflow_variation'] as $variation_id => $vdata ) {
                $vdata = array(
                    'active'       => isset( $vdata['active'] ) ? 1 : 0,
                    'license_type' => sanitize_key( $vdata['license_type'] ?? 'key' ),
                    'delivery_qty' => max( 1, absint( $vdata['delivery_qty'] ?? 1 ) ),
                    'show_in'      => sanitize_key( $vdata['show_in'] ?? 'both' ),
                );
                LicenceFlow_Product_Config::save_config( $post_id, absint( $variation_id ), $vdata );
            }
        }
    }
}
