<?php
/**
 * LicenceFlow — Statistics dashboard
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

// Fetch data
$by_status   = LicenceFlow_License_DB::count_by_status();
$by_type     = LicenceFlow_License_DB::count_by_type();
$by_product  = LicenceFlow_License_DB::count_by_product( 10 );
$low_stock   = LicenceFlow_License_DB::get_low_stock_products( 5 );
$days_before = (int) LicenceFlow_Settings::get( 'lflow_alert_days_before', 7 );
$expiring    = LicenceFlow_License_DB::get_expiring_soon( $days_before );
$recent      = LicenceFlow_License_DB::get_recent_deliveries( 10 );

$total    = array_sum( $by_status );
$avail    = $by_status['available'] ?? 0;
$sold     = $by_status['sold'] ?? 0;
$expired  = $by_status['expired'] ?? 0;

// Helper: bar fill %
function lflow_bar_pct( int $val, int $max ): int {
    return $max > 0 ? (int) min( 100, round( $val / $max * 100 ) ) : 0;
}
?>
<div class="wrap lflow-wrap">

    <h1><?php esc_html_e( 'Statistiques', 'licenceflow' ); ?></h1>

    <!-- Summary cards -->
    <div class="lflow-stat-cards">
        <div class="lflow-stat-card">
            <div class="lflow-stat-number"><?php echo number_format_i18n( $total ); ?></div>
            <div class="lflow-stat-label"><?php esc_html_e( 'Total licences', 'licenceflow' ); ?></div>
        </div>
        <div class="lflow-stat-card lflow-stat-available">
            <div class="lflow-stat-number"><?php echo number_format_i18n( $avail ); ?></div>
            <div class="lflow-stat-label"><?php esc_html_e( 'Disponibles', 'licenceflow' ); ?></div>
        </div>
        <div class="lflow-stat-card lflow-stat-sold">
            <div class="lflow-stat-number"><?php echo number_format_i18n( $sold ); ?></div>
            <div class="lflow-stat-label"><?php esc_html_e( 'Vendues / Livrées', 'licenceflow' ); ?></div>
        </div>
        <div class="lflow-stat-card lflow-stat-expired">
            <div class="lflow-stat-number"><?php echo number_format_i18n( $expired ); ?></div>
            <div class="lflow-stat-label"><?php esc_html_e( 'Expirées', 'licenceflow' ); ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">

        <!-- By status -->
        <div class="lflow-card">
            <h2><?php esc_html_e( 'Par statut', 'licenceflow' ); ?></h2>
            <div class="lflow-bar-chart">
                <?php foreach ( lflow_license_statuses() as $slug => $label ) :
                    $count = $by_status[ $slug ] ?? 0;
                    $pct   = lflow_bar_pct( $count, $total );
                ?>
                <div class="lflow-bar-row">
                    <div class="lflow-bar-label"><?php echo esc_html( $label ); ?></div>
                    <div class="lflow-bar-track">
                        <div class="lflow-bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                    </div>
                    <div class="lflow-bar-count"><?php echo number_format_i18n( $count ); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- By type -->
        <div class="lflow-card">
            <h2><?php esc_html_e( 'Par type', 'licenceflow' ); ?></h2>
            <div class="lflow-bar-chart">
                <?php foreach ( lflow_license_types() as $slug => $label ) :
                    $count = $by_type[ $slug ] ?? 0;
                    $pct   = lflow_bar_pct( $count, $total );
                ?>
                <div class="lflow-bar-row">
                    <div class="lflow-bar-label"><?php echo esc_html( $label ); ?></div>
                    <div class="lflow-bar-track">
                        <div class="lflow-bar-fill" style="width:<?php echo $pct; ?>%; background:#00a32a;"></div>
                    </div>
                    <div class="lflow-bar-count"><?php echo number_format_i18n( $count ); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Alerts -->
    <?php if ( ! empty( $low_stock ) || ! empty( $expiring ) ) : ?>
    <div class="lflow-card" style="margin-bottom:20px;">
        <h2><?php esc_html_e( 'Alertes', 'licenceflow' ); ?></h2>
        <ul class="lflow-alert-list">
            <?php foreach ( $low_stock as $row ) :
                $product    = wc_get_product( $row['product_id'] );
                $pname      = $product ? $product->get_name() : '#' . $row['product_id'];
                $filter_url = LicenceFlow_Admin::licenses_url( array( 'product_id' => $row['product_id'], 'license_status' => 'available' ) );
            ?>
            <li class="lflow-alert-warn">
                ⚠️ <strong><?php esc_html_e( 'Stock faible :', 'licenceflow' ); ?></strong>
                <a href="<?php echo esc_url( $filter_url ); ?>"><?php echo esc_html( $pname ); ?></a>
                — <?php printf(
                    /* translators: %d: number available */
                    esc_html__( '%d disponible(s)', 'licenceflow' ),
                    (int) $row['available']
                ); ?>
            </li>
            <?php endforeach; ?>

            <?php foreach ( $expiring as $l ) :
                $product = wc_get_product( $l['product_id'] );
                $pname   = $product ? $product->get_name() : '#' . $l['product_id'];
                $edit_url = LicenceFlow_Admin::edit_license_url( (int) $l['license_id'] );
            ?>
            <li class="lflow-alert-error">
                🔴 <strong><?php esc_html_e( 'Expire bientôt :', 'licenceflow' ); ?></strong>
                <a href="<?php echo esc_url( $edit_url ); ?>">#<?php echo absint( $l['license_id'] ); ?> — <?php echo esc_html( $pname ); ?></a>
                — <?php echo esc_html( lflow_format_date( $l['expiration_date'], true ) ); ?>
                <?php if ( ! empty( $l['owner_email_address'] ) ) : ?>
                    (<?php echo esc_html( $l['owner_email_address'] ); ?>)
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

        <!-- Top products -->
        <div class="lflow-card">
            <h2><?php esc_html_e( 'Top 10 produits', 'licenceflow' ); ?></h2>
            <?php if ( empty( $by_product ) ) : ?>
                <p style="color:#646970;"><?php esc_html_e( 'Aucune donnée.', 'licenceflow' ); ?></p>
            <?php else : ?>
            <table class="widefat" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Produit', 'licenceflow' ); ?></th>
                        <th style="text-align:right;"><?php esc_html_e( 'Dispo', 'licenceflow' ); ?></th>
                        <th style="text-align:right;"><?php esc_html_e( 'Vendues', 'licenceflow' ); ?></th>
                        <th style="text-align:right;"><?php esc_html_e( 'Total', 'licenceflow' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $by_product as $row ) :
                        $product = wc_get_product( $row['product_id'] );
                        $pname   = $product ? $product->get_name() : '#' . $row['product_id'];
                        $url     = LicenceFlow_Admin::licenses_url( array( 'product_id' => $row['product_id'] ) );
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $pname ); ?></a></td>
                        <td style="text-align:right; color:#00a32a;"><?php echo number_format_i18n( (int) $row['available'] ); ?></td>
                        <td style="text-align:right; color:#2271b1;"><?php echo number_format_i18n( (int) $row['sold'] ); ?></td>
                        <td style="text-align:right;"><?php echo number_format_i18n( (int) $row['total'] ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent deliveries -->
        <div class="lflow-card">
            <h2><?php esc_html_e( 'Activité récente (10 dernières livraisons)', 'licenceflow' ); ?></h2>
            <?php if ( empty( $recent ) ) : ?>
                <p style="color:#646970;"><?php esc_html_e( 'Aucune livraison.', 'licenceflow' ); ?></p>
            <?php else : ?>
            <table class="widefat" style="font-size:.82rem;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php esc_html_e( 'Client', 'licenceflow' ); ?></th>
                        <th><?php esc_html_e( 'Produit', 'licenceflow' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'licenceflow' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent as $row ) :
                        $product  = wc_get_product( $row['product_id'] );
                        $pname    = $product ? $product->get_name() : '#' . $row['product_id'];
                        $edit_url = LicenceFlow_Admin::edit_license_url( (int) $row['license_id'] );
                        $ord_url  = ! empty( $row['order_id'] )
                            ? admin_url( 'post.php?post=' . absint( $row['order_id'] ) . '&action=edit' )
                            : '';
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $edit_url ); ?>">#<?php echo absint( $row['license_id'] ); ?></a></td>
                        <td>
                            <?php echo esc_html( $row['owner_email_address'] ?? '—' ); ?>
                            <?php if ( $ord_url ) : ?>
                                <br><small><a href="<?php echo esc_url( $ord_url ); ?>"><?php echo esc_html__( 'Commande', 'licenceflow' ); ?> #<?php echo absint( $row['order_id'] ); ?></a></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $pname ); ?></td>
                        <td><?php echo esc_html( lflow_format_date( $row['sold_date'] ?? '' ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>

</div>
