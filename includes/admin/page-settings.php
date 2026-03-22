<?php
/**
 * LicenceFlow — Settings page (tabbed)
 *
 * All tab panes are rendered at once and toggled by JS — no page reload on tab switch.
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

    <!-- Tabs nav -->
    <nav class="lflow-settings-tabs" data-active-tab="<?php echo esc_attr( $current_tab ); ?>">
        <?php foreach ( $tabs as $slug => $label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
               data-tab="<?php echo esc_attr( $slug ); ?>"
               class="<?php echo $current_tab === $slug ? 'active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- ── Tab: Général ─────────────────────────────────────────────────── -->
    <div class="lflow-settings-tab-pane" id="lflow-tab-general">
        <form method="post" action="options.php">
            <?php settings_fields( 'lflow_settings_general' ); ?>

            <table class="form-table">
                <tr>
                    <th><label for="lflow_nb_rows_by_page"><?php esc_html_e( 'Lignes par page', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="number" id="lflow_nb_rows_by_page" name="lflow_nb_rows_by_page" value="<?php echo absint( LicenceFlow_Settings::get( 'lflow_nb_rows_by_page' ) ); ?>" min="5" max="200" style="width:80px;">
                        <button type="button" class="lflow-help-btn" aria-label="<?php esc_attr_e( 'Aide', 'licenceflow' ); ?>">?</button>
                        <span class="lflow-help-text"><?php esc_html_e( 'Nombre de licences affichées par page dans la liste principale. Augmenter cette valeur peut ralentir le chargement si vous avez beaucoup de licences.', 'licenceflow' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><label for="lflow_meta_key_name"><?php esc_html_e( 'Label singulier', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="text" id="lflow_meta_key_name" name="lflow_meta_key_name" value="<?php echo esc_attr( LicenceFlow_Settings::get( 'lflow_meta_key_name' ) ); ?>" style="width:200px;">
                        <button type="button" class="lflow-help-btn" aria-label="<?php esc_attr_e( 'Aide', 'licenceflow' ); ?>">?</button>
                        <span class="lflow-help-text"><?php esc_html_e( 'Mot utilisé au singulier dans les emails et l\'espace client pour désigner ce que vous livrez. Exemples : "Licence", "Clé", "Accès", "Code".', 'licenceflow' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><label for="lflow_meta_key_name_plural"><?php esc_html_e( 'Label pluriel', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="text" id="lflow_meta_key_name_plural" name="lflow_meta_key_name_plural" value="<?php echo esc_attr( LicenceFlow_Settings::get( 'lflow_meta_key_name_plural' ) ); ?>" style="width:200px;">
                        <button type="button" class="lflow-help-btn" aria-label="<?php esc_attr_e( 'Aide', 'licenceflow' ); ?>">?</button>
                        <span class="lflow-help-text"><?php esc_html_e( 'Version plurielle du label. Exemples : "Licences", "Clés", "Accès", "Codes". Utilisé quand un client reçoit plusieurs éléments.', 'licenceflow' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th>
                        <?php esc_html_e( 'Ordre de livraison', 'licenceflow' ); ?>
                        <button type="button" class="lflow-help-btn" aria-label="<?php esc_attr_e( 'Aide', 'licenceflow' ); ?>">?</button>
                        <span class="lflow-help-text"><?php esc_html_e( 'FIFO (recommandé) : la première licence ajoutée est la première livrée. Idéal pour écouler les licences dans l\'ordre d\'ajout. LIFO : la dernière licence ajoutée est livrée en premier. Utile si vous ajoutez des lots et voulez utiliser les plus récents d\'abord.', 'licenceflow' ); ?></span>
                    </th>
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
                            'lflow_guest_customer' => array(
                                'label' => __( 'Autoriser les clients invités (sans compte)', 'licenceflow' ),
                                'help'  => __( 'Si activé, un client qui achète sans créer de compte peut quand même recevoir ses licences par email. Si désactivé, seuls les clients connectés voient leurs licences dans leur espace client.', 'licenceflow' ),
                            ),
                            'lflow_different_keys' => array(
                                'label' => __( 'Livrer des licences différentes pour chaque unité commandée', 'licenceflow' ),
                                'help'  => __( 'Si un client commande 3 unités, il recevra 3 licences distinctes. Si cette option est désactivée, la même licence sera envoyée pour chaque unité — déconseillé sauf cas très spécifiques.', 'licenceflow' ),
                            ),
                            'lflow_hide_keys_on_site' => array(
                                'label' => __( 'Masquer les licences sur le site (email uniquement)', 'licenceflow' ),
                                'help'  => __( 'Les licences n\'apparaîtront ni sur la page "Merci pour votre commande" ni dans l\'historique des commandes du compte client. Elles seront uniquement envoyées par email. Utile si vous ne voulez pas que les licences soient visibles en ligne.', 'licenceflow' ),
                            ),
                            'lflow_enable_cart_validation' => array(
                                'label' => __( 'Bloquer la commande si stock de licences insuffisant', 'licenceflow' ),
                                'help'  => __( 'Si activé, WooCommerce refusera le passage en caisse si le nombre de licences disponibles est inférieur à la quantité commandée. Recommandé pour éviter de vendre des produits que vous ne pouvez pas livrer.', 'licenceflow' ),
                            ),
                            'lflow_stock_sync' => array(
                                'label' => __( 'Synchroniser le stock WooCommerce avec le nombre de licences disponibles', 'licenceflow' ),
                                'help'  => __( 'Si activé, la quantité en stock WooCommerce de chaque produit est automatiquement mise à jour à chaque livraison de licence. Fonctionne uniquement si "Suivre la quantité en stock" est déjà activé sur le produit WooCommerce.', 'licenceflow' ),
                            ),
                            'lflow_show_on_top' => array(
                                'label' => __( 'Afficher les licences avant le tableau de commande (emails)', 'licenceflow' ),
                                'help'  => __( 'Par défaut, le bloc de licences apparaît après le récapitulatif de commande dans l\'email. Activez cette option si vous préférez que vos clients voient leurs licences en premier, sans avoir à faire défiler.', 'licenceflow' ),
                            ),
                            'lflow_show_adminbar_notifs' => array(
                                'label' => __( 'Afficher les alertes dans la barre d\'administration', 'licenceflow' ),
                                'help'  => __( 'Affiche une pastille rouge dans la barre d\'administration WordPress quand le stock d\'un produit licencié est bas (moins de 5 licences disponibles). Pratique pour être alerté sans aller dans LicenceFlow.', 'licenceflow' ),
                            ),
                        );
                        foreach ( $toggles as $key => $data ) : ?>
                        <div style="margin-bottom:6px;">
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="on" <?php checked( LicenceFlow_Settings::get( $key ), 'on' ); ?>>
                                <?php echo esc_html( $data['label'] ); ?>
                            </label>
                            <button type="button" class="lflow-help-btn" aria-label="<?php esc_attr_e( 'Aide', 'licenceflow' ); ?>">?</button>
                            <span class="lflow-help-text"><?php echo esc_html( $data['help'] ); ?></span>
                        </div>
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

            <?php submit_button(); ?>
        </form>
    </div>

    <!-- ── Tab: Chiffrement ──────────────────────────────────────────────── -->
    <div class="lflow-settings-tab-pane" id="lflow-tab-encryption">
        <form method="post" action="options.php">
            <?php settings_fields( 'lflow_settings_encryption' ); ?>

            <!-- Pourquoi changer les clés ? -->
            <div style="background:#fff; border:1px solid #ddd; border-left:4px solid #2271b1; border-radius:3px; padding:16px 20px; margin:16px 0; max-width:700px;">
                <h3 style="margin:0 0 10px; font-size:14px; color:#1d2327;"><?php esc_html_e( 'Pourquoi configurer les clés de chiffrement ?', 'licenceflow' ); ?></h3>
                <p style="margin:0 0 10px;"><?php esc_html_e( 'Toutes vos licences (clés, identifiants, liens, codes) sont stockées chiffrées dans la base de données avec l\'algorithme AES-256-CBC. La clé de chiffrement et le vecteur d\'initialisation (IV) sont les "mots de passe" qui permettent de déchiffrer ces données.', 'licenceflow' ); ?></p>
                <p style="margin:0 0 10px;"><strong><?php esc_html_e( 'Les valeurs par défaut sont publiques', 'licenceflow' ); ?></strong> — <?php esc_html_e( 'elles sont visibles dans le code source du plugin et ne protègent rien. Si vous conservez les valeurs par défaut, n\'importe qui ayant accès à votre base de données peut déchiffrer vos licences.', 'licenceflow' ); ?></p>
                <p style="margin:0 0 10px; color:#d63638;"><strong>⚠️ <?php esc_html_e( 'Important :', 'licenceflow' ); ?></strong> <?php esc_html_e( 'Changer les clés après avoir ajouté des licences rendra les données existantes illisibles. Effectuez ce changement uniquement sur une installation neuve, ou après avoir exporté et réimporté toutes vos licences.', 'licenceflow' ); ?></p>
                <p style="margin:0;"><strong>🔐 <?php esc_html_e( 'Conservez vos clés en sécurité', 'licenceflow' ); ?></strong> — <?php esc_html_e( 'notez-les dans un gestionnaire de mots de passe (Bitwarden, 1Password, etc.). Sans elles, vos licences deviennent illisibles. WooCommerce ne peut pas les récupérer si vous les perdez.', 'licenceflow' ); ?></p>
            </div>

            <?php if ( LicenceFlow_Settings::has_default_encryption_keys() ) : ?>
            <div class="lflow-enc-warning">
                ⚠️ <strong><?php esc_html_e( 'Clés par défaut détectées.', 'licenceflow' ); ?></strong>
                <?php esc_html_e( 'Remplacez ces valeurs par des clés uniques AVANT d\'ajouter vos premières licences.', 'licenceflow' ); ?>
            </div>
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><label for="lflow_enc_key"><?php esc_html_e( 'Clé de chiffrement (AES-256)', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="text" id="lflow_enc_key" name="lflow_enc_key" value="<?php echo esc_attr( LicenceFlow_Settings::get( 'lflow_enc_key' ) ); ?>" style="width:100%; max-width:400px; font-family:monospace;">
                        <p class="description"><?php esc_html_e( 'Chaîne aléatoire d\'au moins 32 caractères. Exemple : utilisez un générateur de mots de passe en choisissant 40 caractères alphanumériques.', 'licenceflow' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lflow_enc_iv"><?php esc_html_e( 'Vecteur d\'initialisation (IV)', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="text" id="lflow_enc_iv" name="lflow_enc_iv" value="<?php echo esc_attr( LicenceFlow_Settings::get( 'lflow_enc_iv' ) ); ?>" style="width:100%; max-width:400px; font-family:monospace;">
                        <p class="description"><?php esc_html_e( 'Chaîne aléatoire d\'exactement 16 caractères (le chiffrement AES-CBC requiert 16 octets d\'IV).', 'licenceflow' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>

    <!-- ── Tab: Notifications ────────────────────────────────────────────── -->
    <div class="lflow-settings-tab-pane" id="lflow-tab-notifications">
        <form method="post" action="options.php">
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

            <?php submit_button(); ?>
        </form>
    </div>

    <!-- ── Tab: Statuts de commande ──────────────────────────────────────── -->
    <div class="lflow-settings-tab-pane" id="lflow-tab-order-status">
        <form method="post" action="options.php">
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

            <?php submit_button(); ?>
        </form>
    </div>

</div>
