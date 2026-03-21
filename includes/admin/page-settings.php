<?php
/**
 * LicenceFlow — Settings page (tabbed)
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

// Handle "Force check for updates" action
if (
    isset( $_GET['lflow_action'] ) &&
    $_GET['lflow_action'] === 'force_update_check' &&
    isset( $_GET['_wpnonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'lflow_force_update_check' ) &&
    current_user_can( 'manage_options' )
) {
    LicenceFlow_Updater::force_check();
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-success is-dismissible"><p>';
        esc_html_e( 'Vérification des mises à jour forcée. WordPress consultera GitHub lors du prochain chargement de la page des mises à jour.', 'licenceflow' );
        echo '</p></div>';
    } );
}

$current_tab = sanitize_key( $_GET['tab'] ?? 'general' );
$tabs = array(
    'general'      => __( 'Général', 'licenceflow' ),
    'encryption'   => __( 'Chiffrement', 'licenceflow' ),
    'notifications' => __( 'Notifications', 'licenceflow' ),
    'order-status' => __( 'Statuts de commande', 'licenceflow' ),
);
$base_url = admin_url( 'admin.php?page=lflow-settings' );
?>
<div class="wrap lflow-wrap">

    <h1><?php esc_html_e( 'Réglages LicenceFlow', 'licenceflow' ); ?></h1>

    <!-- Tabs -->
    <nav class="lflow-settings-tabs">
        <?php foreach ( $tabs as $slug => $label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
               data-tab="<?php echo esc_attr( $slug ); ?>"
               class="<?php echo $current_tab === $slug ? 'active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="options.php">

        <?php if ( $current_tab === 'general' ) : ?>
            <?php settings_fields( 'lflow_settings_general' ); ?>

            <table class="form-table">
                <tr>
                    <th><label for="lflow_nb_rows_by_page"><?php esc_html_e( 'Lignes par page', 'licenceflow' ); ?></label></th>
                    <td><input type="number" id="lflow_nb_rows_by_page" name="lflow_nb_rows_by_page" value="<?php echo absint( LicenceFlow_Settings::get( 'lflow_nb_rows_by_page' ) ); ?>" min="5" max="200" style="width:80px;"></td>
                </tr>
                <tr>
                    <th><label for="lflow_meta_key_name"><?php esc_html_e( 'Label singulier', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="text" id="lflow_meta_key_name" name="lflow_meta_key_name" value="<?php echo esc_attr( LicenceFlow_Settings::get( 'lflow_meta_key_name' ) ); ?>" style="width:200px;">
                        <p class="description"><?php esc_html_e( 'Utilisé dans les emails et l\'interface client. Ex : Licence, Clé, Accès.', 'licenceflow' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lflow_meta_key_name_plural"><?php esc_html_e( 'Label pluriel', 'licenceflow' ); ?></label></th>
                    <td><input type="text" id="lflow_meta_key_name_plural" name="lflow_meta_key_name_plural" value="<?php echo esc_attr( LicenceFlow_Settings::get( 'lflow_meta_key_name_plural' ) ); ?>" style="width:200px;"></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Ordre de livraison', 'licenceflow' ); ?></label></th>
                    <td>
                        <select name="lflow_key_delivery">
                            <option value="fifo" <?php selected( LicenceFlow_Settings::get( 'lflow_key_delivery' ), 'fifo' ); ?>><?php esc_html_e( 'FIFO (premier entré, premier sorti)', 'licenceflow' ); ?></option>
                            <option value="lifo" <?php selected( LicenceFlow_Settings::get( 'lflow_key_delivery' ), 'lifo' ); ?>><?php esc_html_e( 'LIFO (dernier entré, premier sorti)', 'licenceflow' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Options', 'licenceflow' ); ?></th>
                    <td>
                        <?php
                        $toggles = array(
                            'lflow_guest_customer'         => __( 'Autoriser les clients invités (sans compte)', 'licenceflow' ),
                            'lflow_different_keys'         => __( 'Livrer des licences différentes pour chaque unité commandée', 'licenceflow' ),
                            'lflow_hide_keys_on_site'      => __( 'Masquer les licences sur le site (email uniquement)', 'licenceflow' ),
                            'lflow_enable_cart_validation' => __( 'Bloquer la commande si stock de licences insuffisant', 'licenceflow' ),
                            'lflow_stock_sync'             => __( 'Synchroniser le stock WooCommerce avec le nombre de licences disponibles', 'licenceflow' ),
                            'lflow_show_on_top'            => __( 'Afficher les licences avant le tableau de commande (emails)', 'licenceflow' ),
                            'lflow_show_adminbar_notifs'   => __( 'Afficher les alertes dans la barre d\'administration', 'licenceflow' ),
                        );
                        foreach ( $toggles as $key => $label ) : ?>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="on" <?php checked( LicenceFlow_Settings::get( $key ), 'on' ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Clé API MCP', 'licenceflow' ); ?></th>
                    <td>
                        <div class="lflow-api-key-display">
                            <input type="text" id="lflow-api-key-value" value="<?php echo esc_attr( LicenceFlow_Settings::get( 'lflow_api_key' ) ); ?>" readonly style="font-family:monospace; width:300px;">
                            <button type="button" id="lflow-regen-api-key" class="button"><?php esc_html_e( 'Régénérer', 'licenceflow' ); ?></button>
                        </div>
                        <p class="description"><?php esc_html_e( 'Utilisée pour authentifier les appels à l\'API MCP. Header : X-LicenceFlow-API-Key.', 'licenceflow' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Mises à jour', 'licenceflow' ); ?></th>
                    <td>
                        <?php
                        $force_check_url = wp_nonce_url(
                            add_query_arg( array( 'page' => 'lflow-settings', 'tab' => 'general', 'lflow_action' => 'force_update_check' ), admin_url( 'admin.php' ) ),
                            'lflow_force_update_check'
                        );
                        ?>
                        <a href="<?php echo esc_url( $force_check_url ); ?>" class="button">
                            <?php esc_html_e( 'Vérifier les mises à jour maintenant', 'licenceflow' ); ?>
                        </a>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: current plugin version */
                                esc_html__( 'Version installée : %s. Force WordPress à consulter GitHub immédiatement (sans attendre les 12h de cache).', 'licenceflow' ),
                                '<strong>' . esc_html( LFLOW_VERSION ) . '</strong>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>

        <?php elseif ( $current_tab === 'encryption' ) : ?>
            <?php settings_fields( 'lflow_settings_encryption' ); ?>

            <?php if ( LicenceFlow_Settings::has_default_encryption_keys() ) : ?>
            <div class="lflow-enc-warning">
                ⚠️ <strong><?php esc_html_e( 'Clés par défaut détectées.', 'licenceflow' ); ?></strong>
                <?php esc_html_e( 'Remplacez ces valeurs par des clés uniques AVANT d\'ajouter vos premières licences.', 'licenceflow' ); ?>
            </div>
            <?php endif; ?>

            <div class="notice notice-warning inline">
                <p><?php esc_html_e( '⚠️ Attention : changer les clés de chiffrement après avoir ajouté des licences rendra les données existantes illisibles. Effectuez cette modification uniquement sur une installation neuve ou après avoir exporté et réimporté vos données.', 'licenceflow' ); ?></p>
            </div>

            <table class="form-table">
                <tr>
                    <th><label for="lflow_enc_key"><?php esc_html_e( 'Clé de chiffrement (AES-256)', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="text" id="lflow_enc_key" name="lflow_enc_key" value="<?php echo esc_attr( LicenceFlow_Settings::get( 'lflow_enc_key' ) ); ?>" style="width:100%; max-width:400px; font-family:monospace;">
                        <p class="description"><?php esc_html_e( 'Chaîne aléatoire d\'au moins 32 caractères.', 'licenceflow' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lflow_enc_iv"><?php esc_html_e( 'Vecteur d\'initialisation (IV)', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="text" id="lflow_enc_iv" name="lflow_enc_iv" value="<?php echo esc_attr( LicenceFlow_Settings::get( 'lflow_enc_iv' ) ); ?>" style="width:100%; max-width:400px; font-family:monospace;">
                        <p class="description"><?php esc_html_e( 'Chaîne aléatoire d\'au moins 16 caractères.', 'licenceflow' ); ?></p>
                    </td>
                </tr>
            </table>

        <?php elseif ( $current_tab === 'notifications' ) : ?>
            <?php settings_fields( 'lflow_settings_notifications' ); ?>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Auto-expiration', 'licenceflow' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="lflow_auto_expire" value="on" <?php checked( LicenceFlow_Settings::get( 'lflow_auto_expire' ), 'on' ); ?>>
                            <?php esc_html_e( 'Marquer automatiquement les licences comme expirées lorsque la date d\'expiration admin est dépassée', 'licenceflow' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="lflow_alert_days_before"><?php esc_html_e( 'Alerte avant expiration', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="number" id="lflow_alert_days_before" name="lflow_alert_days_before" value="<?php echo absint( LicenceFlow_Settings::get( 'lflow_alert_days_before' ) ); ?>" min="1" max="365" style="width:80px;">
                        <?php esc_html_e( 'jours', 'licenceflow' ); ?>
                        <p class="description"><?php esc_html_e( 'Recevoir une alerte email X jours avant la date d\'expiration admin.', 'licenceflow' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lflow_alert_email"><?php esc_html_e( 'Email d\'alerte', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="email" id="lflow_alert_email" name="lflow_alert_email" value="<?php echo esc_attr( LicenceFlow_Settings::get( 'lflow_alert_email', get_option( 'admin_email' ) ) ); ?>" style="width:280px;">
                    </td>
                </tr>
            </table>

        <?php elseif ( $current_tab === 'order-status' ) : ?>
            <?php settings_fields( 'lflow_settings_order_status' ); ?>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Livrer les licences lorsque la commande est…', 'licenceflow' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="lflow_send_when_completed" value="on" <?php checked( LicenceFlow_Settings::get( 'lflow_send_when_completed' ), 'on' ); ?>>
                            <?php esc_html_e( 'Terminée (statut "completed")', 'licenceflow' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="lflow_send_when_processing" value="on" <?php checked( LicenceFlow_Settings::get( 'lflow_send_when_processing' ), 'on' ); ?>>
                            <?php esc_html_e( 'En cours de traitement (statut "processing")', 'licenceflow' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Recommandé : activer "Terminée" pour les paiements nécessitant une vérification, ou "En cours de traitement" pour les paiements instantanés (CB, PayPal).', 'licenceflow' ); ?></p>
                    </td>
                </tr>
            </table>

        <?php endif; ?>

        <?php submit_button(); ?>

    </form>

</div>
