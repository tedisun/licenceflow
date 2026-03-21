<?php
/**
 * LicenceFlow — Email license delivery template
 *
 * Variables: $licenses (array), $order (WC_Order)
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $licenses ) ) return;

$label_plural = LicenceFlow_Settings::get( 'lflow_meta_key_name_plural', __( 'Licences', 'licenceflow' ) );
?>
<div style="margin-top:20px; margin-bottom:20px; font-family:Arial,sans-serif;">

    <h2 style="font-size:18px; margin-bottom:12px; color:#1d2327; border-bottom:2px solid #2271b1; padding-bottom:6px;">
        <?php echo esc_html( $label_plural ); ?>
    </h2>

    <?php foreach ( $licenses as $license ) :
        lflow_render_license_card( $license, 'email' );
    endforeach; ?>

</div>
