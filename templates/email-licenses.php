<?php
/**
 * LicenceFlow — Email license delivery template
 *
 * Variables: $licenses (array), $order (WC_Order), $sent_to_admin (bool)
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $licenses ) ) return;

$sent_to_admin = $sent_to_admin ?? false;
$label_plural  = LicenceFlow_Settings::get( 'lflow_meta_key_name_plural', __( 'Licences', 'licenceflow' ) );
?>
<div style="margin-top:20px; margin-bottom:20px; font-family:Arial,sans-serif;">

    <h2 style="font-size:18px; margin-bottom:12px; color:#1d2327; border-bottom:2px solid #2271b1; padding-bottom:6px;">
        <?php echo esc_html( $label_plural ); ?>
        <?php if ( $sent_to_admin ) : ?>
            <span style="font-size:13px; font-weight:normal; color:#646970; margin-left:8px;">
                (<?php esc_html_e( 'commande #', 'licenceflow' ); ?><?php echo absint( $order->get_id() ); ?>)
            </span>
        <?php endif; ?>
    </h2>

    <?php foreach ( lflow_group_licenses_for_display( $licenses ) as $group ) :
        lflow_render_license_group( $group, 'email' );
    endforeach; ?>

</div>
