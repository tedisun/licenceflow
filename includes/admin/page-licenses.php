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
?>
<div class="wrap lflow-wrap">

    <h1 class="wp-heading-inline"><?php esc_html_e( 'Licences', 'licenceflow' ); ?></h1>
    <a href="<?php echo esc_url( LicenceFlow_Admin::add_license_url() ); ?>" class="page-title-action">
        <?php esc_html_e( 'Ajouter une licence', 'licenceflow' ); ?>
    </a>
    <hr class="wp-header-end">

    <div id="lflow-inline-notice" class="lflow-notice-inline" style="display:none;"></div>

    <!-- Bulk action toolbar -->
    <form method="get" id="lflow-licenses-form">
        <input type="hidden" name="page" value="lflow-licenses">
        <?php
        $table->search_box( __( 'Rechercher un client…', 'licenceflow' ), 'lflow-search' );
        $table->views();
        ?>

        <!-- Bulk action controls -->
        <div class="tablenav top" style="display:flex; align-items:center; gap:8px; margin-bottom:0;">
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

            <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
                <?php $table->extra_tablenav( 'top' ); ?>
            </div>
        </div>

        <?php $table->display(); ?>
    </form>

</div>
