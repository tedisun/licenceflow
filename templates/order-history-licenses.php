<?php
/**
 * LicenceFlow — Order history license display template
 *
 * Variables: $licenses (array), $order (WC_Order)
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $licenses ) ) return;

wp_enqueue_style( 'lflow-frontend', LFLOW_URL . 'assets/css/frontend.css', array(), LFLOW_VERSION );

$label_plural = LicenceFlow_Settings::get( 'lflow_meta_key_name_plural', __( 'Licences', 'licenceflow' ) );
?>
<section class="lflow-customer-licenses lflow-order-history">

    <h2 class="lflow-licenses-title"><?php echo esc_html( $label_plural ); ?></h2>

    <div class="lflow-license-cards">
        <?php foreach ( lflow_group_licenses_for_display( $licenses ) as $group ) :
            lflow_render_license_group( $group, 'website' );
        endforeach; ?>
    </div>

</section>
