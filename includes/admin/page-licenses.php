<?php
/**
 * LicenceFlow — License list page
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

require_once LFLOW_PATH . 'includes/admin/class-licenceflow-list-table.php';

$table = new LicenceFlow_List_Table();
$table->prepare_items();

// Current filter values
$cur_search     = sanitize_text_field( $_GET['s'] ?? '' );
$cur_product    = absint( $_GET['product_id'] ?? 0 );
$cur_variation  = absint( $_GET['variation_id'] ?? 0 );
$cur_type       = sanitize_key( $_GET['license_type'] ?? '' );
$cur_status     = sanitize_key( $_GET['license_status'] ?? '' );

// Products dropdown — only licensed products
$licensed_products = LicenceFlow_Product_Config::get_licensed_products_for_select();

// Variation dropdown pre-populated from URL
$cur_variations = array();
if ( $cur_product > 0 ) {
    $product = wc_get_product( $cur_product );
    if ( $product && $product->is_type( 'variable' ) ) {
        foreach ( $product->get_children() as $var_id ) {
            $variation = wc_get_product( $var_id );
            if ( $variation ) {
                $cur_variations[ $var_id ] = implode( ', ', $variation->get_variation_attributes() ) ?: '#' . $var_id;
            }
        }
    }
}
?>
<div class="wrap lflow-wrap">

    <?php if ( ! empty( $_GET['added'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>
            <?php esc_html_e( 'Licence ajoutée avec succès.', 'licenceflow' ); ?>
        </p></div>
    <?php endif; ?>

    <h1 class="wp-heading-inline"><?php esc_html_e( 'Licences', 'licenceflow' ); ?></h1>
    <a href="<?php echo esc_url( LicenceFlow_Admin::add_license_url() ); ?>" class="page-title-action">
        <?php esc_html_e( 'Ajouter une licence', 'licenceflow' ); ?>
    </a>
    <button type="button" id="lflow-txt-import-btn" class="page-title-action">
        <?php esc_html_e( 'Importer TXT', 'licenceflow' ); ?>
    </button>
    <hr class="wp-header-end">

    <div id="lflow-inline-notice" class="lflow-notice-inline" style="display:none;"></div>

    <form method="get" id="lflow-licenses-form">
        <input type="hidden" name="page" value="lflow-licenses">

        <!-- Status view tabs -->
        <?php $table->views(); ?>

        <!-- ── Bulk actions ── -->
        <div style="clear:both; display:flex; align-items:center; gap:8px; margin:8px 0 6px;">
            <select id="lflow-bulk-action" name="bulk_action">
                <option value=""><?php esc_html_e( '— Action groupée —', 'licenceflow' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Supprimer', 'licenceflow' ); ?></option>
                <?php foreach ( lflow_license_statuses() as $slug => $label ) : ?>
                    <option value="<?php echo esc_attr( $slug ); ?>">
                        <?php printf( esc_html__( 'Marquer : %s', 'licenceflow' ), esc_html( $label ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button id="lflow-bulk-apply" class="button action"><?php esc_html_e( 'Appliquer', 'licenceflow' ); ?></button>
        </div>

        <!-- ── Filter bar ── -->
        <div class="lflow-filter-bar" style="display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; margin:12px 0 0; padding:12px 14px; background:#fff; border:1px solid #ddd; border-radius:4px;">

            <!-- Free text search: client, email, order# -->
            <div>
                <label for="lflow-filter-s" style="display:block; font-size:12px; color:#646970; margin-bottom:3px;">
                    <?php esc_html_e( 'Rechercher', 'licenceflow' ); ?>
                </label>
                <input type="search" id="lflow-filter-s" name="s" value="<?php echo esc_attr( $cur_search ); ?>"
                       placeholder="<?php esc_attr_e( 'Nom, email, n° commande…', 'licenceflow' ); ?>"
                       style="width:200px;">
            </div>

            <!-- Product -->
            <div>
                <label for="lflow-filter-product" style="display:block; font-size:12px; color:#646970; margin-bottom:3px;">
                    <?php esc_html_e( 'Produit', 'licenceflow' ); ?>
                </label>
                <select id="lflow-filter-product" name="product_id" class="lflow-product-select" style="min-width:180px;">
                    <option value="0"><?php esc_html_e( '— Tous les produits —', 'licenceflow' ); ?></option>
                    <?php foreach ( $licensed_products as $pid => $pname ) : ?>
                        <option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $cur_product, $pid ); ?>>
                            <?php echo esc_html( $pname ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Variation (dynamic) -->
            <div>
                <label for="lflow-filter-variation" style="display:block; font-size:12px; color:#646970; margin-bottom:3px;">
                    <?php esc_html_e( 'Variation', 'licenceflow' ); ?>
                </label>
                <select id="lflow-filter-variation" name="variation_id" style="min-width:140px;" <?php echo empty( $cur_variations ) ? 'disabled' : ''; ?>>
                    <option value="0"><?php esc_html_e( '— Toutes —', 'licenceflow' ); ?></option>
                    <?php foreach ( $cur_variations as $vid => $vlabel ) : ?>
                        <option value="<?php echo esc_attr( $vid ); ?>" <?php selected( $cur_variation, $vid ); ?>>
                            <?php echo esc_html( $vlabel ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Type -->
            <div>
                <label for="lflow-filter-type" style="display:block; font-size:12px; color:#646970; margin-bottom:3px;">
                    <?php esc_html_e( 'Type', 'licenceflow' ); ?>
                </label>
                <select id="lflow-filter-type" name="license_type" style="min-width:140px;">
                    <option value=""><?php esc_html_e( '— Tous —', 'licenceflow' ); ?></option>
                    <?php foreach ( lflow_license_types() as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cur_type, $slug ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div>
                <label for="lflow-filter-status" style="display:block; font-size:12px; color:#646970; margin-bottom:3px;">
                    <?php esc_html_e( 'Statut', 'licenceflow' ); ?>
                </label>
                <select id="lflow-filter-status" name="license_status" style="min-width:130px;">
                    <option value=""><?php esc_html_e( '— Tous —', 'licenceflow' ); ?></option>
                    <?php foreach ( lflow_license_statuses() as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cur_status, $slug ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Submit -->
            <div style="align-self:flex-end;">
                <?php submit_button( __( 'Filtrer', 'licenceflow' ), 'primary', 'filter_action', false ); ?>
                <a href="#" class="button lflow-filter-reset" style="margin-left:4px;">
                    <?php esc_html_e( 'Réinitialiser', 'licenceflow' ); ?>
                </a>
            </div>

        </div>

        <div id="lflow-table-container">
        <?php $table->display(); ?>
        </div>

    </form>

</div>

<!-- ── Import TXT modal ────────────────────────────────────────────────── -->
<div id="lflow-txt-import-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:100000;">
    <div id="lflow-txt-import-backdrop" style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.55);"></div>
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:4px; padding:20px 24px; width:520px; max-width:95vw; max-height:90vh; overflow-y:auto; box-shadow:0 4px 24px rgba(0,0,0,.25);">
        <h2 style="margin-top:0; display:flex; justify-content:space-between; align-items:center;">
            <?php esc_html_e( 'Importer des licences (TXT)', 'licenceflow' ); ?>
            <button type="button" class="lflow-txt-import-close" style="background:none; border:none; font-size:22px; cursor:pointer; line-height:1; padding:0; color:#646970;">&#x2715;</button>
        </h2>
        <p class="description" style="margin:0 0 14px;">
            <?php esc_html_e( 'Une licence par ligne. Le type de licence est lu depuis la config produit.', 'licenceflow' ); ?>
        </p>
        <table class="form-table" style="margin:0;">
            <tr>
                <th style="width:130px; padding:6px 10px 6px 0;"><?php esc_html_e( 'Produit', 'licenceflow' ); ?> <span style="color:#d63638;">*</span></th>
                <td style="padding:6px 0;">
                    <select id="lflow-txt-import-product" class="lflow-product-select" style="min-width:280px;">
                        <option value="0"><?php esc_html_e( '— Sélectionner un produit —', 'licenceflow' ); ?></option>
                        <?php foreach ( $licensed_products as $lpid => $lpname ) : ?>
                            <option value="<?php echo esc_attr( $lpid ); ?>"><?php echo esc_html( $lpname ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr id="lflow-txt-import-var-row" style="display:none;">
                <th style="padding:6px 10px 6px 0;"><?php esc_html_e( 'Variation', 'licenceflow' ); ?></th>
                <td style="padding:6px 0;">
                    <select id="lflow-txt-import-variation" style="min-width:200px;">
                        <option value="0"><?php esc_html_e( '— Produit principal —', 'licenceflow' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th style="padding:6px 10px 6px 0;"><?php esc_html_e( 'Nb livraisons', 'licenceflow' ); ?></th>
                <td style="padding:6px 0;">
                    <input type="number" id="lflow-txt-import-delivre" value="1" min="1" style="width:70px;">
                    <span style="color:#646970; font-size:.85em; margin-left:6px;"><?php esc_html_e( 'fois par licence', 'licenceflow' ); ?></span>
                </td>
            </tr>
            <tr>
                <th style="padding:6px 10px 6px 0;"><?php esc_html_e( 'Validité (jours)', 'licenceflow' ); ?></th>
                <td style="padding:6px 0;">
                    <input type="number" id="lflow-txt-import-valid" value="0" min="0" style="width:70px;">
                    <span style="color:#646970; font-size:.85em; margin-left:6px;"><?php esc_html_e( '0 = illimité', 'licenceflow' ); ?></span>
                </td>
            </tr>
            <tr>
                <th style="padding:6px 10px 6px 0;"><?php esc_html_e( 'Fichier .txt', 'licenceflow' ); ?></th>
                <td style="padding:6px 0;">
                    <input type="file" id="lflow-txt-import-file" accept=".txt,text/plain">
                    <p class="description" style="margin:3px 0 0;"><?php esc_html_e( 'Charge et remplit le champ ci-dessous.', 'licenceflow' ); ?></p>
                </td>
            </tr>
            <tr>
                <th style="padding:6px 10px 6px 0; vertical-align:top; padding-top:10px;"><?php esc_html_e( 'Licences', 'licenceflow' ); ?> <span style="color:#d63638;">*</span></th>
                <td style="padding:6px 0;">
                    <textarea id="lflow-txt-import-lines" rows="9"
                              style="width:100%; font-family:monospace; font-size:.82em; resize:vertical;"
                              placeholder="<?php esc_attr_e( 'Coller ou charger — une valeur par ligne…', 'licenceflow' ); ?>"></textarea>
                    <p class="description" id="lflow-txt-import-count" style="margin:3px 0 0;"></p>
                </td>
            </tr>
        </table>
        <input type="hidden" id="lflow-txt-import-type" value="key">
        <div style="margin-top:16px; display:flex; gap:8px; align-items:center;">
            <button type="button" id="lflow-txt-import-submit" class="button button-primary">
                <?php esc_html_e( 'Importer', 'licenceflow' ); ?>
            </button>
            <button type="button" class="button lflow-txt-import-close">
                <?php esc_html_e( 'Annuler', 'licenceflow' ); ?>
            </button>
            <span id="lflow-txt-import-status" style="font-size:.85em; color:#646970; margin-left:4px;"></span>
        </div>
    </div>
</div>
