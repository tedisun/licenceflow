<?php
/**
 * LicenceFlow — Audit logs page
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

$logs = get_option( 'lflow_audit_logs', array() );
?>
<div class="wrap lflow-wrap">

    <h1>
        <span class="dashicons dashicons-media-text" style="font-size: 23px; width:23px; height:23px; line-height:23px; margin-right: 6px;"></span>
        <?php esc_html_e( 'Historique des scans d\'audit', 'licenceflow' ); ?>
    </h1>
    
    <p class="description" style="margin-bottom: 20px;">
        <?php esc_html_e( 'Retrouvez ici le journal des audits automatiques effectués toutes les 8 heures (à 06h00, 14h00 et 22h00). Ce système vérifie la validité de vos clés Microsoft et retire automatiquement du stock celles qui ont été bloquées ou dont le quota d\'activation MAK est épuisé.', 'licenceflow' ); ?>
    </p>

    <div class="lflow-card">
        <h2 style="margin-bottom: 15px; font-size: 1.1rem; border-bottom: 1px solid #f0f0f1; padding-bottom: 10px;">
            <?php esc_html_e( 'Journaux d\'audit récents (50 derniers)', 'licenceflow' ); ?>
        </h2>
        
        <?php if ( empty( $logs ) ) : ?>
            <p style="color:#646970; font-style: italic; padding: 15px 0; text-align: center;">
                <?php esc_html_e( 'Aucun log d\'audit enregistré pour le moment. Le premier scan aura lieu au prochain créneau planifié (à 06h00, 14h00 ou 22h00).', 'licenceflow' ); ?>
            </p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped posts" style="margin-top: 10px; border: 1px solid #c3c4c7; box-shadow: none;">
                <thead>
                    <tr>
                        <th style="width: 22%; font-weight: 600; padding: 12px 10px;"><?php esc_html_e( 'Date & Heure', 'licenceflow' ); ?></th>
                        <th style="width: 13%; font-weight: 600; padding: 12px 10px;"><?php esc_html_e( 'Statut', 'licenceflow' ); ?></th>
                        <th style="width: 13%; font-weight: 600; padding: 12px 10px; text-align: right;"><?php esc_html_e( 'Clés vérifiées', 'licenceflow' ); ?></th>
                        <th style="width: 13%; font-weight: 600; padding: 12px 10px; text-align: right;"><?php esc_html_e( 'Bloquées / Désactivées', 'licenceflow' ); ?></th>
                        <th style="width: 39%; font-weight: 600; padding: 12px 10px;"><?php esc_html_e( 'Résultat / Message', 'licenceflow' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $index => $log ) : 
                        $date_time = esc_html( lflow_format_date( $log['date'] ?? '', true ) );
                        $status = esc_html( $log['status'] ?? 'completed' );
                        $checked = (int) ( $log['checked'] ?? 0 );
                        $blocked = (int) ( $log['blocked'] ?? 0 );
                        $message = esc_html( $log['message'] ?? '' );
                        $anomalies = $log['anomalies'] ?? array();
                        
                        $status_class = 'lflow-status-badge';
                        $status_label = __( 'Terminé', 'licenceflow' );
                        if ( $status === 'skipped' ) {
                            $status_class .= ' lflow-status-inactive';
                            $status_label = __( 'Ignoré', 'licenceflow' );
                        } elseif ( $status === 'error' ) {
                            $status_class .= ' lflow-status-expired';
                            $status_label = __( 'Échec', 'licenceflow' );
                        } else {
                            if ( $blocked > 0 ) {
                                $status_class .= ' lflow-status-returned';
                                $status_label = __( 'Alerte', 'licenceflow' );
                            } else {
                                $status_class .= ' lflow-status-available';
                                $status_label = __( 'Sain', 'licenceflow' );
                            }
                        }
                    ?>
                    <tr>
                        <td style="padding: 12px 10px; vertical-align: middle;">
                            <strong><?php echo $date_time; ?></strong>
                        </td>
                        <td style="padding: 12px 10px; vertical-align: middle;">
                            <span class="<?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                        </td>
                        <td style="padding: 12px 10px; vertical-align: middle; text-align: right; font-weight: 600;">
                            <?php echo number_format_i18n( $checked ); ?>
                        </td>
                        <td style="padding: 12px 10px; vertical-align: middle; text-align: right; font-weight: 600; color: <?php echo $blocked > 0 ? '#d63638' : '#007a46'; ?>;">
                            <?php echo number_format_i18n( $blocked ); ?>
                        </td>
                        <td style="padding: 12px 10px; vertical-align: middle;">
                            <?php if ( ! empty( $message ) ) : ?>
                                <span style="font-size: 0.9rem; display:block; font-weight: 500; color: #2c3338;"><?php echo $message; ?></span>
                            <?php endif; ?>
                            
                            <?php if ( ! empty( $anomalies ) ) : ?>
                                <details style="margin-top: 8px; cursor: pointer; font-size: 0.85rem; background: #fffcfc; border: 1px solid #f2e2e4; border-radius: 4px; padding: 8px 12px; outline: none; transition: background-color 0.2s;">
                                    <summary style="font-weight: 600; color: #b32124; list-style: none; display: flex; align-items: center; gap: 6px;">
                                        <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
                                        <?php printf( __( 'Voir les %d anomalie(s) détectée(s)', 'licenceflow' ), count( $anomalies ) ); ?>
                                    </summary>
                                    <ul style="margin: 10px 0 0 10px; list-style-type: none; padding: 0; line-height: 1.6; color: #1d2327;">
                                        <?php foreach ( $anomalies as $anomaly ) : 
                                            $prod_name = '#' . ( $anomaly['product_id'] ?? '' );
                                            $product = wc_get_product( $anomaly['product_id'] );
                                            if ( $product ) {
                                                $prod_name = $product->get_name();
                                                if ( ! empty( $anomaly['variation_id'] ) ) {
                                                    $variation = wc_get_product( $anomaly['variation_id'] );
                                                    if ( $variation && $variation->is_type( 'variation' ) ) {
                                                        $prod_name .= ' — ' . wc_get_formatted_variation( $variation, true, false );
                                                    }
                                                }
                                            }
                                            $trunc_key = substr( $anomaly['key'] ?? '', 0, 6 ) . '-XXXXX-...-' . substr( $anomaly['key'] ?? '', -5 );
                                            $edit_url = admin_url( 'admin.php?page=lflow-licenses&action=edit&license_id=' . absint( $anomaly['license_id'] ?? 0 ) );
                                        ?>
                                            <li style="margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px dashed #f0f0f1; display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                                                <a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" style="font-weight:600; text-decoration: none; color: #2271b1;">#<?php echo absint( $anomaly['license_id'] ?? 0 ); ?></a>
                                                <span style="color: #646970;">|</span>
                                                <span style="font-weight:500;"><?php echo esc_html( $prod_name ); ?></span>
                                                <span style="color: #646970;">|</span>
                                                <code style="background:#f0f0f1; color:#3c434a; padding:2px 6px; border-radius:3px; font-family: monospace; font-size: 0.85rem; font-weight: 500;"><?php echo esc_html( $trunc_key ); ?></code>
                                                <span style="color: #646970;">|</span>
                                                <span style="color:#d63638; font-weight:600;"><?php echo esc_html( $anomaly['message'] ?? '' ); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php elseif ( $checked > 0 && $blocked === 0 ) : ?>
                                <span style="color: #007a46; font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; gap: 4px; margin-top: 4px;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size: 16px; width: 16px; height: 16px; color: #007a46; line-height: 16px;"></span>
                                    <?php esc_html_e( 'Toutes les clés testées sont valides.', 'licenceflow' ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
