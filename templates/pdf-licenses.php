<?php
/**
 * LicenceFlow — PDF Invoice license template
 *
 * Injected into WooCommerce PDF Invoices & Packing Slips via wpo_wcpdf_after_totals.
 * Uses inline styles only — PDF renderers do not support external stylesheets.
 * Licenses are grouped by product + variation: one card per product, keys listed inside.
 *
 * Variables: $licenses (array), $order (WC_Order)
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $licenses ) ) return;

$label_singular = LicenceFlow_Settings::get( 'lflow_meta_key_name', __( 'Licence', 'licenceflow' ) );
$label_plural   = LicenceFlow_Settings::get( 'lflow_meta_key_name_plural', __( 'Licences', 'licenceflow' ) );
$groups         = lflow_group_licenses_for_display( $licenses );
$heading        = count( $groups ) > 1 || count( $groups[0]['items'] ) > 1 ? $label_plural : $label_singular;
?>
<div style="margin-top:24px; font-family:Arial,sans-serif; font-size:12px;">

    <p style="font-weight:bold; font-size:13px; border-bottom:1px solid #000; padding-bottom:4px; margin-bottom:12px;">
        <?php echo esc_html( $heading ); ?>
    </p>

    <?php foreach ( $groups as $group ) :
        $type         = $group['license_type'];
        $items        = $group['items'];
        $product_id   = $group['product_id'];
        $variation_id = $group['variation_id'];

        // Product / variation names
        $product        = wc_get_product( $product_id );
        $pname          = $product ? $product->get_name() : '#' . $product_id;
        $variation_name = '';
        if ( $variation_id > 0 ) {
            $variation = wc_get_product( $variation_id );
            if ( $variation && $variation->is_type( 'variation' ) ) {
                $variation_name = wc_get_formatted_variation( $variation, true, false );
            }
        }

        $common_expiry = $group['common_expiry'];
        $common_times  = $group['common_times'];
        $common_note   = $group['common_note'];
    ?>
    <div style="border:1px solid #ddd; border-radius:4px; padding:10px 12px; margin-bottom:10px; background:#f9f9f9;">

        <!-- Product header -->
        <p style="margin:0 0 <?php echo $variation_name ? '2px' : '6px'; ?>; font-weight:bold; color:#1d2327;">
            <?php echo esc_html( $pname ); ?>
        </p>
        <?php if ( $variation_name ) : ?>
        <p style="margin:0 0 6px; font-size:11px; color:#555;">
            <?php echo esc_html( $variation_name ); ?>
        </p>
        <?php endif; ?>

        <!-- License items -->
        <?php foreach ( $items as $index => $license ) :
            $parsed = $license['parsed_value'] ?? array();
            $expiry = $license['customer_expiry'] ?? '';
            $times  = isset( $license['times'] ) ? (int) $license['times'] : 1;
            $note   = trim( $license['license_note'] ?? '' );

            $show_per_expiry = ( $common_expiry === null ) && $expiry;
            $show_per_times  = ( $common_times  === null ) && $times > 1;
            $show_per_note   = ( $common_note   === null ) && $note;
        ?>
        <?php if ( $index > 0 ) : ?>
        <div style="border-top:1px solid #e0e0e0; margin:8px 0;"></div>
        <?php endif; ?>

        <?php if ( $type === 'key' ) : ?>
            <p style="margin:0; font-family:monospace; font-size:13px; background:#fff; border:1px solid #ccc; padding:5px 8px; letter-spacing:.05em;">
                <?php echo esc_html( is_string( $parsed ) ? $parsed : ( $parsed['key'] ?? '' ) ); ?>
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
            <?php
            $link_url   = $parsed['url'] ?? '';
            $link_label = ! empty( $parsed['label'] ) ? $parsed['label'] : __( 'Cliquer pour activer', 'licenceflow' );
            ?>
            <p style="margin:0; font-size:12px;">
                <span style="color:#555;"><?php echo esc_html( $link_label ); ?> : </span>
                <?php echo esc_html( $link_url ); ?>
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

        <?php if ( $show_per_note ) : ?>
            <p style="margin:4px 0 0; font-size:11px; color:#3c434a;">
                <?php echo nl2br( esc_html( $note ) ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $show_per_times ) : ?>
            <p style="margin:4px 0 0; font-size:11px; color:#555;">
                <?php printf( esc_html__( 'Utilisable %d fois', 'licenceflow' ), $times ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $show_per_expiry ) : ?>
            <p style="margin:4px 0 0; font-size:11px; color:#555;">
                <?php printf( esc_html__( 'Valide jusqu\'au : %s', 'licenceflow' ), esc_html( $expiry ) ); ?>
            </p>
        <?php endif; ?>

        <?php endforeach; // items ?>

        <!-- Common metadata (shown once at the bottom of the card) -->
        <?php if ( $common_note ) : ?>
            <p style="margin:8px 0 0; font-size:11px; color:#3c434a;">
                <?php echo nl2br( esc_html( $common_note ) ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $common_times !== null && $common_times > 1 ) : ?>
            <p style="margin:6px 0 0; font-size:11px; color:#555;">
                <?php printf( esc_html__( 'Utilisable %d fois', 'licenceflow' ), $common_times ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $common_expiry ) : ?>
            <p style="margin:4px 0 0; font-size:11px; color:#555;">
                <?php printf( esc_html__( 'Valide jusqu\'au : %s', 'licenceflow' ), esc_html( $common_expiry ) ); ?>
            </p>
        <?php endif; ?>

    </div>
    <?php endforeach; // groups ?>

</div>
