<?php
/**
 * LicenceFlow — Order metabox
 *
 * Displays delivered licenses on the WooCommerce order edit screen.
 * Compatible with both Classic and HPOS order screens.
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_Order_Metabox {

    /** @var self|null */
    private static $instance = null;

    private function __construct() {
        // Classic orders
        add_action( 'add_meta_boxes_shop_order', array( $this, 'register' ) );
        // HPOS orders
        add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( $this, 'register' ) );
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register(): void {
        add_meta_box(
            'lflow-order-metabox',
            __( 'LicenceFlow — Licences livrées', 'licenceflow' ),
            array( $this, 'render' ),
            wc_get_page_screen_id( 'shop-order' ),
            'normal',
            'default'
        );
    }

    public function render( $post_or_order ): void {
        // Support both classic (WP_Post) and HPOS (WC_Order)
        if ( $post_or_order instanceof WP_Post ) {
            $order_id = $post_or_order->ID;
        } elseif ( $post_or_order instanceof WC_Order ) {
            $order_id = $post_or_order->get_id();
        } else {
            return;
        }

        $licenses = LicenceFlow_License_DB::get_by_order( $order_id );

        if ( empty( $licenses ) ) {
            echo '<p style="color:#646970;">' . esc_html__( 'Aucune licence livrée pour cette commande.', 'licenceflow' ) . '</p>';
            return;
        }

        echo '<table class="widefat" style="font-size:.82rem;">';
        echo '<thead><tr>';
        echo '<th>#</th>';
        echo '<th>' . esc_html__( 'Produit', 'licenceflow' ) . '</th>';
        echo '<th>' . esc_html__( 'Type', 'licenceflow' ) . '</th>';
        echo '<th>' . esc_html__( 'Statut', 'licenceflow' ) . '</th>';
        echo '<th>' . esc_html__( 'Expiration admin', 'licenceflow' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'licenceflow' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $licenses as $license ) {
            $product    = wc_get_product( $license['product_id'] );
            $pname      = $product ? esc_html( $product->get_name() ) : '#' . absint( $license['product_id'] );
            $status     = $license['license_status'] ?? 'sold';
            $statuses   = lflow_license_statuses();
            $status_lbl = $statuses[ $status ] ?? $status;
            $exp_date   = ( ! empty( $license['expiration_date'] ) && $license['expiration_date'] !== '0000-00-00' )
                ? esc_html( lflow_format_date( $license['expiration_date'], true ) )
                : '<span style="color:#646970">—</span>';

            echo '<tr>';
            echo '<td><a href="' . esc_url( LicenceFlow_Admin::edit_license_url( (int) $license['license_id'] ) ) . '">#' . absint( $license['license_id'] ) . '</a></td>';
            echo '<td>' . wp_kses_post( $pname ) . '</td>';
            echo '<td>' . esc_html( lflow_license_type_label( $license['license_type'] ?? 'key' ) ) . '</td>';
            echo '<td><span class="lflow-status-badge lflow-status-' . esc_attr( $status ) . '">' . esc_html( $status_lbl ) . '</span></td>';
            echo '<td>' . wp_kses_post( $exp_date ) . '</td>';
            echo '<td><a href="' . esc_url( LicenceFlow_Admin::edit_license_url( (int) $license['license_id'] ) ) . '" class="button button-small">' . esc_html__( 'Voir', 'licenceflow' ) . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
