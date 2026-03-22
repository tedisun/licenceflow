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
$import_url      = admin_url( 'admin.php?page=lflow-import-export' );
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
                    __( 'Vos clés de chiffrement sont encore aux valeurs par défaut. <a href="%s">Changez-les maintenant</a> avant d\'ajouter vos licences.', 'licenceflow' ),
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
            <p><?php esc_html_e( 'Clé alphanumérique pour activer un logiciel ou un service.', 'licenceflow' ); ?></p>
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
                    <?php esc_html_e( 'Dans la fiche produit WooCommerce, onglet LicenceFlow : cochez "Activer", choisissez le type, la quantité à livrer par unité commandée, et le canal de diffusion (email, site, les deux).', 'licenceflow' ); ?>
                </div>
            </li>
            <li>
                <div>
                    <strong><?php esc_html_e( 'Ajoutez vos licences', 'licenceflow' ); ?></strong><br>
                    <?php printf(
                        wp_kses(
                            /* translators: %1$s: link to add license, %2$s: link to import */
                            __( 'Via <a href="%1$s">Licences → Ajouter</a> (une par une) ou via <a href="%2$s">Import / Export → Importer TXT</a> (un fichier texte, une ligne par licence — méthode recommandée pour les lots). Chaque licence peut être configurée comme livrable plusieurs fois.', 'licenceflow' ),
                            array( 'a' => array( 'href' => array() ) )
                        ),
                        esc_url( $add_license_url ),
                        esc_url( $import_url )
                    ); ?>
                </div>
            </li>
            <li>
                <div>
                    <strong><?php esc_html_e( 'Le client passe commande', 'licenceflow' ); ?></strong><br>
                    <?php esc_html_e( 'À la validation de la commande (statut "Terminé" ou "En cours de traitement" selon vos réglages), LicenceFlow attribue automatiquement les licences disponibles selon la règle FIFO ou LIFO.', 'licenceflow' ); ?>
                </div>
            </li>
            <li>
                <div>
                    <strong><?php esc_html_e( 'La licence est livrée', 'licenceflow' ); ?></strong><br>
                    <?php esc_html_e( 'Le client reçoit ses licences par email (dans l\'email de commande WooCommerce et sur la facture PDF si vous utilisez WooCommerce PDF Invoices & Packing Slips) et/ou les voit sur la page de confirmation de commande et dans son historique client.', 'licenceflow' ); ?>
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
                    <span style="color:#d63638; font-weight:600;"><?php esc_html_e( '← à faire en premier', 'licenceflow' ); ?></span>
                <?php endif; ?>
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">
                    <?php esc_html_e( 'Activer LicenceFlow sur votre premier produit (onglet LicenceFlow dans la fiche produit)', 'licenceflow' ); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo esc_url( $import_url ); ?>">
                    <?php esc_html_e( 'Importer vos licences via un fichier TXT (Import / Export → Importer TXT)', 'licenceflow' ); ?>
                </a>
            </li>
            <li>
                <?php esc_html_e( 'Passer une commande test (produit à 0 €) pour vérifier la livraison par email', 'licenceflow' ); ?>
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=lflow-settings&tab=order-status' ) ); ?>">
                    <?php esc_html_e( 'Vérifier les réglages de statut de commande (Terminé / En cours de traitement)', 'licenceflow' ); ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Concepts clés -->
    <div class="lflow-card">
        <h2><?php esc_html_e( 'Concepts clés', 'licenceflow' ); ?></h2>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'FIFO / LIFO', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'FIFO (premier entré, premier sorti) livre les licences dans l\'ordre où elles ont été ajoutées — idéal pour utiliser les licences par lot d\'achat chronologique. LIFO fait l\'inverse (dernière ajoutée, première livrée). Configurez ce comportement dans Réglages → Général.', 'licenceflow' ); ?>
        </p>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'Livrable X fois', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'Une même licence peut être livrée à plusieurs clients différents. Exemple : une clé Windows 11 Pro utilisable sur 5 appareils — configurez "Livrable 5 fois" sur cette licence. Elle sera livrée au 1er client, puis au 2e, etc., jusqu\'à 5 livraisons. Le compteur décrémente à chaque livraison et la licence ne devient "Vendue" qu\'à épuisement. Le client voit la mention "Utilisable X fois" sur sa carte de licence.', 'licenceflow' ); ?>
        </p>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'Double date d\'expiration', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'Chaque licence a deux dates distinctes :', 'licenceflow' ); ?><br>
            <?php esc_html_e( '• Date d\'expiration admin : quand vous devez renouveler ou remplacer la licence côté fournisseur. Visible uniquement en admin. Vous recevez une alerte email X jours avant (configurable dans Réglages → Notifications).', 'licenceflow' ); ?><br>
            <?php esc_html_e( '• Validité client : calculée dynamiquement à partir de la date d\'achat + le nombre de jours de validité défini sur la licence. C\'est l\'unique date visible par le client dans ses emails et son espace compte.', 'licenceflow' ); ?>
        </p>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'Synchronisation du stock', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'Si activée (Réglages → Général), LicenceFlow met à jour automatiquement la quantité en stock WooCommerce à chaque livraison ou ajout de licence. Prérequis : "Suivre la quantité en stock" doit déjà être activé sur le produit WooCommerce — LicenceFlow ne force jamais cette option, c\'est vous qui décidez. Utile pour afficher "En stock : 5" basé sur les licences réellement disponibles et bloquer les achats quand le stock est épuisé.', 'licenceflow' ); ?>
        </p>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'Facture PDF', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'Si vous utilisez le plugin WooCommerce PDF Invoices & Packing Slips (par Ewout Fernhout), LicenceFlow injecte automatiquement les licences de la commande dans la facture PDF générée — sans configuration supplémentaire. Les licences apparaissent sous les articles de la commande, avec le même affichage par type (clé, identifiants, lien, code).', 'licenceflow' ); ?>
        </p>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'Import TXT', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'La méthode d\'import recommandée pour les lots de licences : un fichier texte brut, une ligne = une licence. Pas d\'en-tête, pas de formatage particulier. Tous les paramètres (produit, type, livrable X fois, statut, validité) sont définis une seule fois dans le formulaire d\'import et s\'appliquent à chaque ligne du fichier.', 'licenceflow' ); ?>
        </p>

        <h3 style="font-size:.9rem; margin-bottom:4px;"><?php esc_html_e( 'MCP (accès IA et automatisation)', 'licenceflow' ); ?></h3>
        <p style="margin-top:0; font-size:.875rem; color:#3c434a;">
            <?php esc_html_e( 'LicenceFlow expose une API REST sécurisée par clé API (', 'licenceflow' ); ?>
            <code>/wp-json/licenceflow/mcp/v1/</code><?php esc_html_e( '). Elle permet à des outils d\'automatisation, des scripts ou des IA (Claude, ChatGPT, n8n, Zapier…) de créer, lire, mettre à jour et supprimer des licences sans passer par l\'interface. Authentification via l\'en-tête HTTP ', 'licenceflow' ); ?>
            <code>X-LicenceFlow-API-Key</code><?php esc_html_e( '. Clé configurable dans Réglages → Général.', 'licenceflow' ); ?>
        </p>

    </div>

</div>
