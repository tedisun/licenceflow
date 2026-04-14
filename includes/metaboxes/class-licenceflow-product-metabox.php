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
                        <th style="padding:6px 10px;"><label><?php esc_html_e( 'Validité client (jours)', 'licenceflow' ); ?></label></th>
                        <td style="padding:6px;">
                            <input type="number" name="lflow_default_valid" value="<?php echo absint( $config['default_valid'] ?? 0 ); ?>" min="0" style="width:80px;">
                            <p class="description"><?php esc_html_e( 'Pré-remplit la validité lors de l\'ajout de licences. Laisser à 0 pour illimité.', 'licenceflow' ); ?></p>
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
                            'delivery_qty' => 1, 'show_in' => 'both', 'default_valid' => 0,
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
                            <label><?php esc_html_e( 'Validité (j) :', 'licenceflow' ); ?>
                                <input type="number" name="lflow_variation[<?php echo absint( $variation_id ); ?>][default_valid]" value="<?php echo absint( $vcfg['default_valid'] ?? 0 ); ?>" min="0" style="width:60px;" title="<?php esc_attr_e( '0 = illimité', 'licenceflow' ); ?>">
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
                $available    = LicenceFlow_License_DB::count_available( $product_id );
                $recent       = LicenceFlow_License_DB::get_list( array(
                    'product_id' => $product_id,
                    'per_page'   => 8,
                    'orderby'    => 'license_id',
                    'order'      => 'DESC',
                ) );
                $lic_type     = LicenceFlow_Product_Config::get_license_type( $product_id, 0 );
                $default_valid = (int) ( $config['default_valid'] ?? 0 );

                // Build variation list once for the quick-add form
                $qa_variations = array();
                if ( $is_variable ) {
                    foreach ( $product->get_children() as $vid ) {
                        $vobj = wc_get_product( $vid );
                        if ( $vobj ) {
                            $qa_variations[ $vid ] = $vobj->get_formatted_name();
                        }
                    }
                }
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

                <!-- Quick-add inline form
                     NOTE: intentionally NOT a <form> element — the product edit
                     page already wraps everything in a <form>, and nested forms
                     are invalid HTML. The submit button uses type="button" and
                     the JS click handler posts via AJAX. -->
                <div id="lflow-quick-add-form" style="display:none; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:12px; margin-bottom:12px;">
                    <input type="hidden" name="qa_product_id" value="<?php echo absint( $product_id ); ?>">
                    <input type="hidden" name="qa_license_type" id="lflow-qa-type" value="<?php echo esc_attr( $lic_type ); ?>">
                    <table style="width:100%; border-collapse:collapse; margin:0;">
                        <?php if ( $is_variable && ! empty( $qa_variations ) ) : ?>
                        <tr>
                            <td style="padding:4px 10px 6px 0; white-space:nowrap; width:130px; font-weight:600; font-size:.9em; vertical-align:middle;"><?php esc_html_e( 'Variation', 'licenceflow' ); ?></td>
                            <td style="padding:4px 0 6px;">
                                <select name="variation_id" id="lflow-qa-variation" style="width:100%; max-width:320px;">
                                    <option value="0"><?php esc_html_e( '— Produit principal —', 'licenceflow' ); ?></option>
                                    <?php foreach ( $qa_variations as $vid => $vname ) : ?>
                                        <option value="<?php echo absint( $vid ); ?>"><?php echo esc_html( $vname ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <!-- key / code : single value textarea -->
                        <tr class="lflow-qa-field lflow-qa-field-key lflow-qa-field-code" <?php if ( ! in_array( $lic_type, array( 'key', 'code' ), true ) ) echo 'style="display:none;"'; ?>>
                            <td style="padding:4px 10px 6px 0; font-weight:600; font-size:.9em; vertical-align:top; padding-top:8px;"><?php esc_html_e( 'Valeur', 'licenceflow' ); ?></td>
                            <td style="padding:4px 0 6px;">
                                <textarea name="license_value[key]" id="lflow-qa-value" rows="2" style="width:100%; font-family:monospace; resize:vertical;" placeholder="<?php esc_attr_e( 'Valeur de la licence · valeur || note client', 'licenceflow' ); ?>"></textarea>
                            </td>
                        </tr>

                        <!-- account : username + password -->
                        <tr class="lflow-qa-field lflow-qa-field-account" <?php if ( $lic_type !== 'account' ) echo 'style="display:none;"'; ?>>
                            <td style="padding:4px 10px 4px 0; font-weight:600; font-size:.9em; vertical-align:middle;"><?php esc_html_e( 'Identifiant', 'licenceflow' ); ?></td>
                            <td style="padding:4px 0;">
                                <input type="text" name="license_value[username]" id="lflow-qa-username" style="width:100%; max-width:280px;" autocomplete="off">
                            </td>
                        </tr>
                        <tr class="lflow-qa-field lflow-qa-field-account" <?php if ( $lic_type !== 'account' ) echo 'style="display:none;"'; ?>>
                            <td style="padding:4px 10px 4px 0; font-weight:600; font-size:.9em; vertical-align:middle;"><?php esc_html_e( 'Mot de passe', 'licenceflow' ); ?></td>
                            <td style="padding:4px 0;">
                                <input type="text" name="license_value[password]" id="lflow-qa-password" style="width:100%; max-width:280px;" autocomplete="off">
                            </td>
                        </tr>

                        <!-- link : url + label -->
                        <tr class="lflow-qa-field lflow-qa-field-link" <?php if ( $lic_type !== 'link' ) echo 'style="display:none;"'; ?>>
                            <td style="padding:4px 10px 4px 0; font-weight:600; font-size:.9em; vertical-align:middle;"><?php esc_html_e( 'URL', 'licenceflow' ); ?></td>
                            <td style="padding:4px 0;">
                                <input type="url" name="license_value[url]" id="lflow-qa-url" style="width:100%; max-width:340px;">
                            </td>
                        </tr>
                        <tr class="lflow-qa-field lflow-qa-field-link" <?php if ( $lic_type !== 'link' ) echo 'style="display:none;"'; ?>>
                            <td style="padding:4px 10px 4px 0; font-weight:600; font-size:.9em; vertical-align:middle;"><?php esc_html_e( 'Label', 'licenceflow' ); ?></td>
                            <td style="padding:4px 0;">
                                <input type="text" name="license_value[label]" id="lflow-qa-label" style="width:100%; max-width:280px;">
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:4px 10px 4px 0; font-weight:600; font-size:.9em; vertical-align:middle;"><?php esc_html_e( 'Nb livraisons', 'licenceflow' ); ?></td>
                            <td style="padding:4px 0;">
                                <input type="number" name="delivre_x_times" id="lflow-qa-delivre" value="1" min="1" style="width:70px;">
                                <span style="font-size:.85em; color:#646970; margin-left:6px;"><?php esc_html_e( 'fois que cette licence peut être livrée', 'licenceflow' ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:4px 10px 4px 0; font-weight:600; font-size:.9em; vertical-align:middle;"><?php esc_html_e( 'Validité (jours)', 'licenceflow' ); ?></td>
                            <td style="padding:4px 0;">
                                <input type="number" name="valid" id="lflow-qa-valid" value="<?php echo absint( $default_valid ); ?>" min="0" style="width:80px;">
                                <span style="font-size:.85em; color:#646970; margin-left:6px;"><?php esc_html_e( '0 = illimité', 'licenceflow' ); ?></span>
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top:10px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                        <button type="button" class="button button-primary lflow-quick-add-submit">+ <?php esc_html_e( 'Ajouter', 'licenceflow' ); ?></button>
                        <span style="font-size:.82em; color:#646970;"><?php esc_html_e( 'Clé · ', 'licenceflow' ); ?><code>valeur || note client</code></span>
                    </div>
                </div>

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
                        <?php if ( empty( $recent['items'] ) ) : ?>
                            <tr id="lflow-no-licenses-row"><td colspan="4" style="color:#646970;"><?php esc_html_e( 'Aucune licence pour ce produit.', 'licenceflow' ); ?></td></tr>
                        <?php else : ?>
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
                        <?php endif; ?>
                    </tbody>
                </table>
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
            'active'        => isset( $_POST['lflow_active'] ) ? 1 : 0,
            'license_type'  => sanitize_key( $_POST['lflow_license_type'] ?? 'key' ),
            'delivery_qty'  => 1, // fixed at 1; not exposed in UI
            'show_in'       => sanitize_key( $_POST['lflow_show_in'] ?? 'both' ),
            'default_valid' => max( 0, absint( $_POST['lflow_default_valid'] ?? 0 ) ),
        );
        LicenceFlow_Product_Config::save_config( $post_id, 0, $data );

        // Save variation configs
        if ( ! empty( $_POST['lflow_variation'] ) && is_array( $_POST['lflow_variation'] ) ) {
            foreach ( $_POST['lflow_variation'] as $variation_id => $vdata ) {
                $vdata = array(
                    'active'        => isset( $vdata['active'] ) ? 1 : 0,
                    'license_type'  => sanitize_key( $vdata['license_type'] ?? 'key' ),
                    'delivery_qty'  => 1, // fixed at 1
                    'show_in'       => sanitize_key( $vdata['show_in'] ?? 'both' ),
                    'default_valid' => max( 0, absint( $vdata['default_valid'] ?? 0 ) ),
                );
                LicenceFlow_Product_Config::save_config( $post_id, absint( $variation_id ), $vdata );
            }
        }
    }
}
