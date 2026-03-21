<?php
/**
 * LicenceFlow — Getting Started page
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

$settings_url    = admin_url( 'admin.php?page=lflow-settings&tab=encryption' );
$add_license_url = LicenceFlow_Admin::add_license_url();
$licenses_url    = LicenceFlow_Admin::licenses_url();
$enc_warning     = LicenceFlow_Settings::has_default_encryption_keys();
?>
<div class="wrap lflow-wrap">

    <h1><?php esc_html_e( 'LicenceFlow', 'licenceflow' ); ?></h1>

    <?php if ( $enc_warning ) : ?>
    <div class="notice notice-error inline">
        <p>
            <strong><?php esc_html_e( 'Action requise :', 'licenceflow' ); ?></strong>
            <?php printf(
                wp_kses(
                    /* translators: %s: link to encryption settings */
                    __( 'Vos clés de chiffrement sont encore aux valeurs par défaut. <a href="%s">Changez-les maintenant</a> pour sécuriser les données de vos clients.', 'licenceflow' ),
                    array( 'a' => array( 'href' => array() ) )
                ),
                esc_url( $settings_url )
            ); ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Hero -->
    <div class="lflow-gs-hero">
        <h2><?php esc_html_e( 'Bienvenue dans LicenceFlow', 'licenceflow' ); ?></h2>
        <p><?php esc_html_e( 'LicenceFlow automatise la livraison de vos licences numériques avec WooCommerce. Ajoutez vos licences une fois, laissez le plugin les distribuer à chaque achat.', 'licenceflow' ); ?></p>
    </div>

    <!-- Les 4 types de licences -->
    <h2><?php esc_html_e( 'Les 4 types de licences', 'licenceflow' ); ?></h2>
    <div class="lflow-gs-grid">

        <div class="lflow-gs-type-card">
            <span class="lflow-type-icon">🔑</span>
            <h3><?php esc_html_e( 'Clé de licence', 'licenceflow' ); ?></h3>
            <p><?php esc_html_e( 'Clé alphanumérique simple pour activer un logiciel ou un service.', 'licenceflow' ); ?></p>
            <code>AAAA-BBBB-CCCC-DDDD</code>
        </div>

        <div class="lflow-gs-type-card">
            <span class="lflow-type-icon">👤</span>
            <h3><?php esc_html_e( 'Compte (identifiants)', 'licenceflow' ); ?></h3>
            <p><?php esc_html_e( 'Identifiant + mot de passe pour un compte de service ou abonnement.', 'licenceflow' ); ?></p>
            <code>user@exemple.com / ••••••••</code>
        </div>

        <div class="lflow-gs-type-card">
            <span class="lflow-type-icon">🔗</span>
            <h3><?php esc_html_e( 'Lien d\'activation', 'licenceflow' ); ?></h3>
            <p><?php esc_html_e( 'URL unique pour rejoindre un service, télécharger un fichier ou activer un accès.', 'licenceflow' ); ?></p>
            <code>https://app.exemple.com/invite/xyz</code>
        </div>

        <div class="lflow-gs-type-card">
            <span class="lflow-type-icon">🎟️</span>
            <h3><?php esc_html_e( 'Code d\'accès', 'licenceflow' ); ?></h3>
            <p><?php esc_html_e( 'Code à saisir + note optionnelle (ex : code promo, carte cadeau).', 'licenceflow' ); ?></p>
            <code>PROMO2025</code>
        </div>

    </div>

    <!-- Comment ça fonctionne -->
    <div class="lflow-card">
        <h2><?php esc_html_e( 'Comment fonctionne la livraison', 'licenceflow' ); ?></h2>
        <ol class="lflow-gs-steps">
            <li>
                <div>
                    <strong><?php esc_html_e( 'Activez la licence sur un produit', 'licenceflow' ); ?></strong><br>
                    <?php esc_html_e( 'Dans la fiche produit WooCommerce, onglet LicenceFlow : cochez "Activer", choisissez le type, la quantité à livrer et le canal (email, site, les deux).', 'licenceflow' ); ?>
                </div>
            </li>
            <li>
                <div>
                    <strong><?php esc_html_e( 'Ajoutez vos licences', 'licenceflow' ); ?></strong><br>
                    <?php printf(
                        wp_kses(
                            /* translators: %s: link to add license */
                            __( 'Via <a href="%s">Licences → Ajouter</a> ou en important un fichier CSV, renseignez les licences disponibles pour ce produit.', 'licenceflow' ),
                            array( 'a' => array( 'href' => array() ) )
                        ),
                        esc_url( $add_license_url )
                    ); ?>
                </div>
            </li>
            <li>
                <div>
                    <strong><?php esc_html_e( 'Le client passe commande', 'licenceflow' ); ?></strong><br>
                    <?php esc_html_e( 'À la validation de la commande (statut "Terminé" ou "En cours de traitement" selon vos réglages), LicenceFlow attribue automatiquement les licences disponibles.', 'licenceflow' ); ?>
                </div>
            </li>
            <li>
                <div>
                    <strong><?php esc_html_e( 'La licence est livrée', 'licenceflow' ); ?></strong><br>
                    <?php esc_html_e( 'Le client reçoit ses licences par email (dans l\'email de commande) et/ou les voit sur la page de confirmation et dans son historique de commandes.', 'licenceflow' ); ?>
                </div>
            </li>
        </ol>
    </div>

    <!-- Checklist de démarrage -->
    <div class="lflow-card">
        <h2><?php esc_html_e( 'Checklist de mise en route', 'licenceflow' ); ?></h2>
        <ul class="lflow-checklist">
            <li>
                <a href="<?php echo esc_url( $settings_url ); ?>">
                    <?php esc_html_e( 'Changer les clés de chiffrement dans Réglages → Chiffrement', 'licenceflow' ); ?>
                </a>
                <?php if ( $enc_warning ) : ?>
                    <span style="color:#d63638; font-weight:600;"><?php esc_html_e( '← à faire maintenant', 'licenceflow' ); ?></span>
                <?php endif; ?>
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">
                    <?php esc_html_e( 'Activer LicenceFlow sur votre premier produit (onglet LicenceFlow dans la fiche produit)', 'licenceflow' ); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo esc_url( $add_license_url ); ?>">
                    <?php esc_html_e( 'Ajouter votre première licence', 'licenceflow' ); ?>
                </a>
            </li>
            <li>
                <?php esc_html_e( 'Passer une commande test (produit à 0 €) pour vérifier la livraison', 'licenceflow' ); ?>
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=lflow-settings&tab=order-status' ) ); ?>">
                    <?php esc_html_e( 'Vérifier les réglages de statut de commande (Terminé / En cours)', 'licenceflow' ); ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Concepts clés -->
    <div class="lflow-card">
        <h2><?php esc_html_e( 'Concepts clés', 'licenceflow' ); ?></h2>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'FIFO / LIFO', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'FIFO (premier entré, premier sorti) livre les licences dans l\'ordre où elles ont été ajoutées. LIFO fait l\'inverse. Configurez ce comportement dans Réglages → Général.', 'licenceflow' ); ?>
        </p>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'Double date d\'expiration', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'Chaque licence a deux dates :', 'licenceflow' ); ?><br>
            <?php esc_html_e( '• Date d\'expiration admin : quand vous devez renouveler/remplacer la licence. Vous recevez une alerte X jours avant.', 'licenceflow' ); ?><br>
            <?php esc_html_e( '• Validité client : calculée à partir de la date d\'achat + le nombre de jours de validité configuré. C\'est cette date que voit le client.', 'licenceflow' ); ?>
        </p>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'Synchronisation du stock', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'Si activée, LicenceFlow met à jour le stock WooCommerce du produit en fonction du nombre de licences disponibles. Utile pour afficher "En stock : 5" basé sur les licences réelles.', 'licenceflow' ); ?>
        </p>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'MCP (accès IA)', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'LicenceFlow expose une API REST sécurisée par clé API (', 'licenceflow' ); ?>
            <code>/wp-json/licenceflow/mcp/v1/</code><?php esc_html_e( ') permettant à des outils d\'automatisation ou des IA de créer, lire, mettre à jour et supprimer des licences. Configurez la clé dans Réglages → Général.', 'licenceflow' ); ?>
        </p>
    </div>

</div>
