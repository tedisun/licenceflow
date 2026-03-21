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

    <?php if ( $sent_to_admin ) : ?>
        <!-- Admin view: show a compact table with all license keys clearly visible -->
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#f0f6fc;">
                    <th style="padding:6px 10px; text-align:left; border:1px solid #ddd;"><?php esc_html_e( 'Produit', 'licenceflow' ); ?></th>
                    <th style="padding:6px 10px; text-align:left; border:1px solid #ddd;"><?php esc_html_e( 'Type', 'licenceflow' ); ?></th>
                    <th style="padding:6px 10px; text-align:left; border:1px solid #ddd;"><?php esc_html_e( 'Clé / Valeur', 'licenceflow' ); ?></th>
                    <th style="padding:6px 10px; text-align:left; border:1px solid #ddd;"><?php esc_html_e( 'Expiration client', 'licenceflow' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $licenses as $license ) :
                    $product = wc_get_product( $license['product_id'] ?? 0 );
                    $pname   = $product ? $product->get_name() : '#' . ( $license['product_id'] ?? '?' );
                    $type    = $license['license_type'] ?? 'key';
                    $parsed  = $license['parsed_value'] ?? array();

                    // Build a readable key string
                    if ( $type === 'key' ) {
                        $key_display = $parsed['key'] ?? '';
                    } elseif ( $type === 'account' ) {
                        $key_display = ( $parsed['username'] ?? '' ) . ' / ' . ( $parsed['password'] ?? '' );
                    } elseif ( $type === 'link' ) {
                        $key_display = $parsed['url'] ?? '';
                    } elseif ( $type === 'code' ) {
                        $key_display = $parsed['code'] ?? '';
                    } else {
                        $key_display = '';
                    }
                ?>
                <tr>
                    <td style="padding:6px 10px; border:1px solid #ddd;"><?php echo esc_html( $pname ); ?></td>
                    <td style="padding:6px 10px; border:1px solid #ddd;"><?php echo esc_html( lflow_license_type_label( $type ) ); ?></td>
                    <td style="padding:6px 10px; border:1px solid #ddd; font-family:monospace; font-size:12px;"><?php echo esc_html( $key_display ); ?></td>
                    <td style="padding:6px 10px; border:1px solid #ddd;"><?php echo esc_html( $license['customer_expiry'] ?: '—' ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else : ?>
        <!-- Customer view: full styled cards -->
        <?php foreach ( $licenses as $license ) :
            lflow_render_license_card( $license, 'email' );
        endforeach; ?>

    <?php endif; ?>

</div>
