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
<div style="margin-top:24px; font-family:Arial,sans-serif; font-size:12px;">

    <p style="font-weight:bold; font-size:13px; border-bottom:1px solid #000; padding-bottom:4px; margin-bottom:12px;">
        <?php echo esc_html( $heading ); ?>
    </p>

    <?php foreach ( $licenses as $license ) :
        $type         = $license['license_type'] ?? 'key';
        $parsed       = $license['parsed_value']  ?? array();
        $expiry       = $license['customer_expiry'] ?? '';
        $times        = isset( $license['times'] ) ? (int) $license['times'] : 1;
        $license_note = trim( $license['license_note'] ?? '' );

        // Product name + variation name
        $product        = wc_get_product( $license['product_id'] ?? 0 );
        $pname          = $product ? $product->get_name() : '#' . ( $license['product_id'] ?? '?' );
        $variation_name = '';
        if ( ! empty( $license['variation_id'] ) && (int) $license['variation_id'] > 0 ) {
            $variation = wc_get_product( (int) $license['variation_id'] );
            if ( $variation && $variation->is_type( 'variation' ) ) {
                $variation_name = wc_get_formatted_variation( $variation, true, false );
            }
        }
    ?>
    <div style="border:1px solid #ddd; border-radius:4px; padding:10px 12px; margin-bottom:10px; background:#f9f9f9;">

        <p style="margin:0 <?php echo $variation_name ? '2px' : '6px'; ?> 0; font-weight:bold; color:#1d2327;">
            <?php echo esc_html( $pname ); ?>
        </p>
        <?php if ( $variation_name ) : ?>
        <p style="margin:0 0 6px; font-size:11px; color:#555;">
            <?php echo esc_html( $variation_name ); ?>
        </p>
        <?php endif; ?>

        <?php if ( $type === 'key' ) : ?>
            <p style="margin:0; font-family:monospace; font-size:13px; background:#fff; border:1px solid #ccc; padding:5px 8px; letter-spacing:.05em;">
                <?php echo esc_html( $parsed['key'] ?? '' ); ?>
            </p>

        <?php elseif ( $type === 'account' ) : ?>
            <table style="border:0; padding:0; font-size:12px;">
                <tr>
                    <td style="padding:2px 10px 2px 0; color:#555;"><?php esc_html_e( 'Identifiant', 'licenceflow' ); ?> :</td>
                    <td style="font-family:monospace;"><?php echo esc_html( $parsed['username'] ?? '' ); ?></td>
                </tr>
                <tr>
                    <td style="padding:2px 10px 0 0; color:#555;"><?php esc_html_e( 'Mot de passe', 'licenceflow' ); ?> :</td>
                    <td style="font-family:monospace;"><?php echo esc_html( $parsed['password'] ?? '' ); ?></td>
                </tr>
            </table>

        <?php elseif ( $type === 'link' ) : ?>
            <p style="margin:0; font-size:12px;">
                <span style="color:#555;"><?php esc_html_e( 'Lien', 'licenceflow' ); ?> : </span>
                <?php echo esc_html( $parsed['url'] ?? '' ); ?>
                <?php if ( ! empty( $parsed['label'] ) ) : ?>
                    <span style="color:#555;"> (<?php echo esc_html( $parsed['label'] ); ?>)</span>
                <?php endif; ?>
            </p>

        <?php elseif ( $type === 'code' ) : ?>
            <p style="margin:0; font-family:monospace; font-size:13px; background:#fff; border:1px solid #ccc; padding:5px 8px;">
                <?php echo esc_html( $parsed['code'] ?? '' ); ?>
            </p>
            <?php if ( ! empty( $parsed['note'] ) ) : ?>
                <p style="margin:4px 0 0; font-size:11px; color:#555; font-style:italic;">
                    <?php echo esc_html( $parsed['note'] ); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ( $license_note ) : ?>
            <p style="margin:6px 0 0; font-size:11px; color:#3c434a;">
                <?php echo nl2br( esc_html( $license_note ) ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $times > 1 ) : ?>
            <p style="margin:6px 0 0; font-size:11px; color:#555;">
                <?php
                printf(
                    /* translators: %d: number of times the license can be used */
                    esc_html__( 'Utilisable %d fois', 'licenceflow' ),
                    $times
                );
                ?>
            </p>
        <?php endif; ?>

        <?php if ( $expiry ) : ?>
            <p style="margin:4px 0 0; font-size:11px; color:#555;">
                <?php
                printf(
                    /* translators: %s: expiry date */
                    esc_html__( 'Valide jusqu\'au : %s', 'licenceflow' ),
                    esc_html( $expiry )
                );
                ?>
            </p>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>

</div>
