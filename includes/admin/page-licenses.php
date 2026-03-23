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
                <select id="lflow-filter-product" name="product_id" style="min-width:180px;">
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
                <?php if ( $cur_search || $cur_product || $cur_type || $cur_status || $cur_variation ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=lflow-licenses' ) ); ?>"
                       class="button" style="margin-left:4px;">
                        <?php esc_html_e( 'Réinitialiser', 'licenceflow' ); ?>
                    </a>
                <?php endif; ?>
            </div>

        </div>

        <?php $table->display(); ?>

    </form>

</div>
