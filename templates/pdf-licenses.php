<?php
/**
 * LicenceFlow — PDF Invoice license template
 *
 * Injected into WooCommerce PDF Invoices & Packing Slips via wpo_wcpdf_after_totals.
 * Uses inline styles only — PDF renderers do not support external stylesheets.
 *
 * Variables: $licenses (array), $order (WC_Order)
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $licenses ) ) return;

$label_singular = LicenceFlow_Settings::get( 'lflow_meta_key_name', __( 'Licence', 'licenceflow' ) );
$label_plural   = LicenceFlow_Settings::get( 'lflow_meta_key_name_plural', __( 'Licences', 'licenceflow' ) );
$heading        = count( $licenses ) > 1 ? $label_plural : $label_singular;
?>
<table style="width:100%; border-collapse:collapse; margin-top:20px; font-family:Arial,sans-serif; font-size:12px;">

    <!-- Section heading row -->
    <tr>
        <td colspan="2" style="padding:6px 0 4px; font-weight:bold; font-size:13px; border-bottom:1px solid #000;">
            <?php echo esc_html( $heading ); ?>
        </td>
    </tr>

    <!-- Column headers -->
    <tr style="background:#1d2327; color:#ffffff;">
        <th style="padding:5px 8px; text-align:left; width:35%;"><?php esc_html_e( 'Produit', 'licenceflow' ); ?></th>
        <th style="padding:5px 8px; text-align:left;"><?php echo esc_html( $heading ); ?></th>
    </tr>

    <!-- License rows -->
    <?php
    $row_bg = false;
    foreach ( $licenses as $license ) :
        $row_bg = ! $row_bg;
        $type   = $license['license_type'] ?? 'key';
        $parsed = $license['parsed_value']  ?? array();

        // Build the key display string based on type
        switch ( $type ) {
            case 'account':
                $key_display = ( $parsed['username'] ?? '' ) . ' / ' . ( $parsed['password'] ?? '' );
                break;
            case 'link':
                $key_display = $parsed['url'] ?? '';
                break;
            case 'code':
                $key_display = $parsed['code'] ?? '';
                if ( ! empty( $parsed['note'] ) ) {
                    $key_display .= ' (' . $parsed['note'] . ')';
                }
                break;
            default: // key
                $key_display = $parsed['key'] ?? '';
        }

        // Product name
        $product = wc_get_product( $license['product_id'] ?? 0 );
        $pname   = $product ? $product->get_name() : '#' . ( $license['product_id'] ?? '?' );

        // Customer expiry line
        $expiry = $license['customer_expiry'] ?? '';
    ?>
    <tr style="background:<?php echo $row_bg ? '#f9f9f9' : '#ffffff'; ?>;">
        <td style="padding:5px 8px; border-bottom:1px solid #e0e0e0; vertical-align:top;">
            <?php echo esc_html( $pname ); ?>
        </td>
        <td style="padding:5px 8px; border-bottom:1px solid #e0e0e0; font-family:monospace; word-break:break-all;">
            <?php echo esc_html( $key_display ); ?>
            <?php if ( $expiry ) : ?>
                <br><span style="font-family:Arial,sans-serif; font-size:10px; color:#555;">
                    <?php
                    printf(
                        /* translators: %s: expiry date */
                        esc_html__( 'Valide jusqu\'au : %s', 'licenceflow' ),
                        esc_html( $expiry )
                    );
                    ?>
                </span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>

</table>
