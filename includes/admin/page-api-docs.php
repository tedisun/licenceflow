<?php
/**
 * LicenceFlow — API Documentation page
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

$api_key  = LicenceFlow_Settings::get( 'lflow_mcp_api_key', '' );
$base_url = trailingslashit( get_rest_url( null, 'licenceflow/mcp/v1' ) );
?>
<style>
.lflow-api-method {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: .75rem;
    font-weight: 700;
    font-family: monospace;
    min-width: 52px;
    text-align: center;
    vertical-align: middle;
}
.lflow-api-method.get    { background: #e8f4fd; color: #0073aa; }
.lflow-api-method.post   { background: #edfaf1; color: #00a32a; }
.lflow-api-method.put    { background: #fff8e5; color: #996800; }
.lflow-api-method.delete { background: #fceaea; color: #d63638; }

.lflow-api-endpoint {
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin-bottom: 12px;
    overflow: hidden;
}
.lflow-api-endpoint-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: #f6f7f7;
    cursor: pointer;
    user-select: none;
    border-bottom: 1px solid transparent;
}
.lflow-api-endpoint-header:hover { background: #f0f0f1; }
.lflow-api-endpoint-header.open  { border-bottom-color: #c3c4c7; }
.lflow-api-endpoint-title {
    font-weight: 600;
    font-size: .875rem;
    color: #1d2327;
    font-family: monospace;
    flex: 1;
}
.lflow-api-endpoint-desc {
    font-size: .8rem;
    color: #646970;
}
.lflow-api-endpoint-body {
    padding: 16px;
    display: none;
    background: #fff;
}
.lflow-api-endpoint-body.open { display: block; }

.lflow-api-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .8125rem;
    margin-bottom: 12px;
}
.lflow-api-table th {
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    padding: 6px 10px;
    text-align: left;
    font-weight: 600;
    color: #1d2327;
}
.lflow-api-table td {
    border: 1px solid #c3c4c7;
    padding: 6px 10px;
    vertical-align: top;
    color: #3c434a;
}
.lflow-api-table td code {
    font-size: .8rem;
    background: #f6f7f7;
    padding: 1px 4px;
    border-radius: 2px;
}
.lflow-api-required {
    color: #d63638;
    font-weight: 700;
}
.lflow-api-code {
    background: #1d2327;
    color: #f0f0f1;
    border-radius: 4px;
    padding: 12px 14px;
    font-family: monospace;
    font-size: .8rem;
    line-height: 1.6;
    overflow-x: auto;
    margin: 8px 0 0;
    position: relative;
}
.lflow-api-copy {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255,255,255,.15);
    border: none;
    color: #fff;
    font-size: .7rem;
    padding: 3px 8px;
    border-radius: 3px;
    cursor: pointer;
}
.lflow-api-copy:hover { background: rgba(255,255,255,.3); }

.lflow-api-info-box {
    background: #e8f4fd;
    border-left: 4px solid #2271b1;
    padding: 10px 14px;
    border-radius: 0 4px 4px 0;
    font-size: .8125rem;
    color: #1d2327;
    margin-bottom: 16px;
}
.lflow-api-info-box code {
    background: rgba(0,0,0,.07);
    padding: 1px 4px;
    border-radius: 2px;
}
.lflow-api-key-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
}
.lflow-api-key-value {
    font-family: monospace;
    font-size: .875rem;
    background: #fff;
    border: 1px solid #c3c4c7;
    padding: 5px 10px;
    border-radius: 3px;
    flex: 1;
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.lflow-api-section-label {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #646970;
    margin: 14px 0 6px;
}
.lflow-response-badge {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 3px;
    font-size: .75rem;
    font-weight: 700;
    margin-right: 4px;
}
.lflow-response-badge.ok  { background: #edfaf1; color: #00a32a; }
.lflow-response-badge.err { background: #fceaea; color: #d63638; }
</style>

<div class="wrap lflow-wrap">

    <h1><?php esc_html_e( 'Documentation API', 'licenceflow' ); ?></h1>

    <!-- Auth block -->
    <div class="lflow-card">
        <h2><?php esc_html_e( 'Authentification', 'licenceflow' ); ?></h2>

        <div class="lflow-api-info-box">
            <?php esc_html_e( 'Toutes les requêtes doivent inclure votre clé API dans l\'en-tête HTTP :', 'licenceflow' ); ?><br>
            <code>X-LicenceFlow-API-Key: votre_clé</code>
        </div>

        <table class="lflow-api-table" style="max-width:680px;">
            <tr>
                <th style="width:140px;"><?php esc_html_e( 'URL de base', 'licenceflow' ); ?></th>
                <td>
                    <div class="lflow-api-code" style="margin:0; display:inline-block; padding:6px 36px 6px 10px;">
                        <?php echo esc_html( rtrim( $base_url, '/' ) ); ?>
                        <button class="lflow-api-copy" data-copy="<?php echo esc_attr( rtrim( $base_url, '/' ) ); ?>">Copier</button>
                    </div>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Header', 'licenceflow' ); ?></th>
                <td><code>X-LicenceFlow-API-Key</code></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Votre clé API', 'licenceflow' ); ?></th>
                <td>
                    <div class="lflow-api-key-row">
                        <span class="lflow-api-key-value" id="lflow-api-key-display">
                            <?php echo esc_html( $api_key ?: '— non définie —' ); ?>
                        </span>
                        <?php if ( $api_key ) : ?>
                        <button class="button button-secondary lflow-api-copy" data-copy="<?php echo esc_attr( $api_key ); ?>" style="position:static; background:#1d2327; color:#fff; border-color:#1d2327;">
                            <?php esc_html_e( 'Copier', 'licenceflow' ); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <p class="description" style="margin-top:4px;">
                        <?php printf(
                            wp_kses( __( 'Modifiable dans <a href="%s">Réglages → Général</a>.', 'licenceflow' ), array( 'a' => array( 'href' => array() ) ) ),
                            esc_url( admin_url( 'admin.php?page=lflow-settings' ) )
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <!-- Quick cURL example -->
        <p class="lflow-api-section-label"><?php esc_html_e( 'Exemple cURL', 'licenceflow' ); ?></p>
        <div class="lflow-api-code">
            curl -X GET "<?php echo esc_html( rtrim( $base_url, '/' ) ); ?>/licenses" \<br>
            &nbsp;&nbsp;-H "X-LicenceFlow-API-Key: <?php echo esc_html( $api_key ?: 'votre_clé' ); ?>"
            <button class="lflow-api-copy" data-copy="curl -X GET &quot;<?php echo esc_attr( rtrim( $base_url, '/' ) ); ?>/licenses&quot; -H &quot;X-LicenceFlow-API-Key: <?php echo esc_attr( $api_key ?: 'votre_clé' ); ?>&quot;">Copier</button>
        </div>

        <!-- N8N tip -->
        <div class="lflow-api-info-box" style="margin-top:16px; background:#fff8e5; border-left-color:#996800;">
            <strong>N8N / Zapier :</strong>
            <?php esc_html_e( 'Dans le nœud "HTTP Request", ajoutez un header personnalisé :', 'licenceflow' ); ?>
            <br><code>Nom : X-LicenceFlow-API-Key</code> &nbsp;|&nbsp; <code>Valeur : votre_clé</code>
        </div>
    </div>

    <!-- Endpoints -->
    <div class="lflow-card">
        <h2><?php esc_html_e( 'Endpoints', 'licenceflow' ); ?></h2>

        <?php
        $base = rtrim( $base_url, '/' );

        $endpoints = array(

            // ── GET /licenses ────────────────────────────────────────────────
            array(
                'method' => 'GET',
                'path'   => '/licenses',
                'desc'   => __( 'Lister les licences (paginé)', 'licenceflow' ),
                'params_label' => __( 'Paramètres de requête (query string)', 'licenceflow' ),
                'params' => array(
                    array( 'status',      'string',  '',      __( 'Filtrer par statut : <code>available</code>, <code>sold</code>, <code>expired</code>', 'licenceflow' ) ),
                    array( 'product_id',  'integer', '',      __( 'Filtrer par ID produit WooCommerce', 'licenceflow' ) ),
                    array( 'type',        'string',  '',      __( 'Filtrer par type : <code>key</code>, <code>account</code>, <code>link</code>, <code>code</code>', 'licenceflow' ) ),
                    array( 'search',      'string',  '',      __( 'Recherche par nom, email ou numéro de commande', 'licenceflow' ) ),
                    array( 'page',        'integer', '1',     __( 'Numéro de page', 'licenceflow' ) ),
                    array( 'per_page',    'integer', '20',    __( 'Licences par page (max 100)', 'licenceflow' ) ),
                    array( 'orderby',     'string',  'license_id', __( 'Colonne de tri', 'licenceflow' ) ),
                    array( 'order',       'string',  'DESC',  __( '<code>ASC</code> ou <code>DESC</code>', 'licenceflow' ) ),
                    array( 'include_key', 'boolean', 'false', __( 'Inclure la valeur chiffrée de la clé dans la réponse', 'licenceflow' ) ),
                ),
                'example_curl' => "curl -X GET \"{$base}/licenses?status=available&per_page=10\" \\\n  -H \"X-LicenceFlow-API-Key: {$api_key}\"",
                'example_response' => '{
  "licenses": [ { "license_id": 42, "product_id": 123, "license_type": "key", "license_status": "available", ... } ],
  "total": 58,
  "page": 1,
  "per_page": 10,
  "total_pages": 6
}',
                'responses' => array(
                    array( '200', 'ok', __( 'Liste paginée', 'licenceflow' ) ),
                ),
            ),

            // ── POST /licenses ───────────────────────────────────────────────
            array(
                'method' => 'POST',
                'path'   => '/licenses',
                'desc'   => __( 'Créer une nouvelle licence', 'licenceflow' ),
                'params_label' => __( 'Corps de la requête (JSON)', 'licenceflow' ),
                'params' => array(
                    array( 'product_id',      'integer', '',           __( 'ID produit WooCommerce', 'licenceflow' ), true ),
                    array( 'license_key',     'string',  '',           __( 'Valeur de la licence (clé, login/mdp, URL, code). Pour <code>account</code> : <code>login||mdp</code>', 'licenceflow' ), true ),
                    array( 'license_type',    'string',  'key',        __( '<code>key</code> | <code>account</code> | <code>link</code> | <code>code</code>', 'licenceflow' ) ),
                    array( 'variation_id',    'integer', '0',          __( 'ID de la variation WooCommerce (0 = produit principal)', 'licenceflow' ) ),
                    array( 'license_status',  'string',  'available',  __( '<code>available</code> | <code>sold</code> | <code>expired</code>', 'licenceflow' ) ),
                    array( 'valid',           'integer', '0',          __( 'Validité client en jours (0 = illimité)', 'licenceflow' ) ),
                    array( 'delivre_x_times', 'integer', '1',          __( 'Nombre de livraisons autorisées pour cette licence (min 1)', 'licenceflow' ) ),
                    array( 'expiration_date', 'string',  '',           __( 'Date d\'expiration admin : format <code>YYYY-MM-DD</code>', 'licenceflow' ) ),
                    array( 'license_note',    'string',  '',           __( 'Note visible par le client', 'licenceflow' ) ),
                    array( 'admin_notes',     'string',  '',           __( 'Note interne (admin uniquement)', 'licenceflow' ) ),
                ),
                'example_curl' => "curl -X POST \"{$base}/licenses\" \\\n  -H \"X-LicenceFlow-API-Key: {$api_key}\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"product_id\": 123,\n    \"license_key\": \"AAAA-BBBB-CCCC-DDDD\",\n    \"license_type\": \"key\",\n    \"valid\": 365,\n    \"delivre_x_times\": 1\n  }'",
                'example_response' => '{ "license_id": 99, "product_id": 123, "license_type": "key", "license_status": "available", "valid": 365, ... }',
                'responses' => array(
                    array( '201', 'ok',  __( 'Licence créée', 'licenceflow' ) ),
                    array( '500', 'err', __( 'Échec d\'insertion en base de données', 'licenceflow' ) ),
                ),
            ),

            // ── GET /licenses/{id} ───────────────────────────────────────────
            array(
                'method' => 'GET',
                'path'   => '/licenses/{id}',
                'desc'   => __( 'Lire une licence par son ID', 'licenceflow' ),
                'params_label' => __( 'Paramètres de requête', 'licenceflow' ),
                'params' => array(
                    array( 'id',          'integer', '',      __( 'ID de la licence <strong>(dans l\'URL)</strong>', 'licenceflow' ), true ),
                    array( 'include_key', 'boolean', 'false', __( 'Inclure la valeur chiffrée de la clé', 'licenceflow' ) ),
                ),
                'example_curl' => "curl -X GET \"{$base}/licenses/42?include_key=true\" \\\n  -H \"X-LicenceFlow-API-Key: {$api_key}\"",
                'example_response' => '{ "license_id": 42, "product_id": 123, "license_type": "key", "license_status": "sold", "license_key": "...", "parsed_value": { "key": "AAAA-BBBB-CCCC-DDDD" }, ... }',
                'responses' => array(
                    array( '200', 'ok',  __( 'Données de la licence', 'licenceflow' ) ),
                    array( '404', 'err', __( 'Licence introuvable', 'licenceflow' ) ),
                ),
            ),

            // ── PUT /licenses/{id} ───────────────────────────────────────────
            array(
                'method' => 'PUT',
                'path'   => '/licenses/{id}',
                'desc'   => __( 'Modifier une licence existante (seuls les champs envoyés sont mis à jour)', 'licenceflow' ),
                'params_label' => __( 'Corps de la requête (JSON) — tous optionnels', 'licenceflow' ),
                'params' => array(
                    array( 'id',              'integer', '', __( 'ID de la licence <strong>(dans l\'URL)</strong>', 'licenceflow' ), true ),
                    array( 'product_id',      'integer', '', __( 'Nouvel ID produit', 'licenceflow' ) ),
                    array( 'variation_id',    'integer', '', __( 'Nouvel ID variation', 'licenceflow' ) ),
                    array( 'license_key',     'string',  '', __( 'Nouvelle valeur de licence', 'licenceflow' ) ),
                    array( 'license_type',    'string',  '', __( 'Nouveau type (requis si <code>license_key</code> est fourni)', 'licenceflow' ) ),
                    array( 'license_status',  'string',  '', __( 'Nouveau statut', 'licenceflow' ) ),
                    array( 'valid',           'integer', '', __( 'Nouvelle validité en jours', 'licenceflow' ) ),
                    array( 'expiration_date', 'string',  '', __( 'Nouvelle date d\'expiration admin (<code>YYYY-MM-DD</code> ou vide pour supprimer)', 'licenceflow' ) ),
                    array( 'license_note',    'string',  '', __( 'Note visible client', 'licenceflow' ) ),
                    array( 'admin_notes',     'string',  '', __( 'Note interne', 'licenceflow' ) ),
                ),
                'example_curl' => "curl -X PUT \"{$base}/licenses/42\" \\\n  -H \"X-LicenceFlow-API-Key: {$api_key}\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"license_status\": \"available\", \"valid\": 180}'",
                'example_response' => '{ "license_id": 42, "license_status": "available", "valid": 180, ... }',
                'responses' => array(
                    array( '200', 'ok',  __( 'Licence mise à jour', 'licenceflow' ) ),
                    array( '400', 'err', __( 'Aucun champ à mettre à jour', 'licenceflow' ) ),
                    array( '404', 'err', __( 'Licence introuvable', 'licenceflow' ) ),
                ),
            ),

            // ── DELETE /licenses/{id} ────────────────────────────────────────
            array(
                'method' => 'DELETE',
                'path'   => '/licenses/{id}',
                'desc'   => __( 'Supprimer définitivement une licence', 'licenceflow' ),
                'params_label' => __( 'Paramètre URL', 'licenceflow' ),
                'params' => array(
                    array( 'id', 'integer', '', __( 'ID de la licence <strong>(dans l\'URL)</strong>', 'licenceflow' ), true ),
                ),
                'example_curl' => "curl -X DELETE \"{$base}/licenses/42\" \\\n  -H \"X-LicenceFlow-API-Key: {$api_key}\"",
                'example_response' => '{ "deleted": true, "id": 42 }',
                'responses' => array(
                    array( '200', 'ok',  __( 'Suppression confirmée', 'licenceflow' ) ),
                    array( '404', 'err', __( 'Licence introuvable', 'licenceflow' ) ),
                    array( '500', 'err', __( 'Échec de suppression', 'licenceflow' ) ),
                ),
            ),

            // ── POST /licenses/{id}/deliver ──────────────────────────────────
            array(
                'method' => 'POST',
                'path'   => '/licenses/{id}/deliver',
                'desc'   => __( 'Livrer une licence à une commande WooCommerce (décrémente le compteur de livraisons restantes)', 'licenceflow' ),
                'params_label' => __( 'Corps de la requête (JSON)', 'licenceflow' ),
                'params' => array(
                    array( 'id',       'integer', '', __( 'ID de la licence <strong>(dans l\'URL)</strong>', 'licenceflow' ), true ),
                    array( 'order_id', 'integer', '', __( 'ID de la commande WooCommerce destinataire', 'licenceflow' ), true ),
                ),
                'example_curl' => "curl -X POST \"{$base}/licenses/42/deliver\" \\\n  -H \"X-LicenceFlow-API-Key: {$api_key}\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"order_id\": 500}'",
                'example_response' => '{ "delivered": true, "license_id": 42, "order_id": 500 }',
                'responses' => array(
                    array( '200', 'ok',  __( 'Livraison effectuée', 'licenceflow' ) ),
                    array( '404', 'err', __( 'Licence ou commande introuvable', 'licenceflow' ) ),
                    array( '409', 'err', __( 'Licence indisponible (épuisée ou expirée)', 'licenceflow' ) ),
                ),
            ),

            // ── GET /stats ───────────────────────────────────────────────────
            array(
                'method' => 'GET',
                'path'   => '/stats',
                'desc'   => __( 'Statistiques globales du stock de licences', 'licenceflow' ),
                'params_label' => '',
                'params' => array(),
                'example_curl' => "curl -X GET \"{$base}/stats\" \\\n  -H \"X-LicenceFlow-API-Key: {$api_key}\"",
                'example_response' => '{
  "by_status":  { "available": 120, "sold": 85, "expired": 3 },
  "by_type":    { "key": 150, "account": 40, "link": 15, "code": 3 },
  "by_product": [ { "product_id": 123, "product_name": "Windows 11 Pro", "available": 50 }, ... ],
  "low_stock":  [ ... ],
  "expiring_soon": [ ... ],
  "recent_deliveries": [ ... ]
}',
                'responses' => array(
                    array( '200', 'ok', __( 'Objet statistiques', 'licenceflow' ) ),
                ),
            ),

        );

        foreach ( $endpoints as $i => $ep ) :
            $method_lower = strtolower( $ep['method'] );
            $endpoint_id  = 'lflow-ep-' . $i;
        ?>
        <div class="lflow-api-endpoint">
            <div class="lflow-api-endpoint-header" onclick="lflowToggleEp('<?php echo esc_js( $endpoint_id ); ?>', this)">
                <span class="lflow-api-method <?php echo esc_attr( $method_lower ); ?>"><?php echo esc_html( $ep['method'] ); ?></span>
                <span class="lflow-api-endpoint-title"><?php echo esc_html( $ep['path'] ); ?></span>
                <span class="lflow-api-endpoint-desc"><?php echo esc_html( $ep['desc'] ); ?></span>
                <span class="dashicons dashicons-arrow-down-alt2" style="color:#646970; margin-left:auto;"></span>
            </div>
            <div class="lflow-api-endpoint-body" id="<?php echo esc_attr( $endpoint_id ); ?>">

                <?php if ( ! empty( $ep['params'] ) ) : ?>
                <p class="lflow-api-section-label"><?php echo esc_html( $ep['params_label'] ); ?></p>
                <table class="lflow-api-table">
                    <thead>
                        <tr>
                            <th style="width:160px;"><?php esc_html_e( 'Paramètre', 'licenceflow' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Type', 'licenceflow' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Défaut', 'licenceflow' ); ?></th>
                            <th><?php esc_html_e( 'Description', 'licenceflow' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $ep['params'] as $p ) :
                            $required = ! empty( $p[4] );
                        ?>
                        <tr>
                            <td>
                                <code><?php echo esc_html( $p[0] ); ?></code>
                                <?php if ( $required ) : ?><span class="lflow-api-required" title="<?php esc_attr_e( 'Obligatoire', 'licenceflow' ); ?>"> *</span><?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $p[1] ); ?></td>
                            <td><?php echo $p[2] !== '' ? '<code>' . esc_html( $p[2] ) . '</code>' : '<span style="color:#646970">—</span>'; ?></td>
                            <td><?php echo wp_kses( $p[3], array( 'code' => array(), 'strong' => array() ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Responses -->
                <p class="lflow-api-section-label"><?php esc_html_e( 'Codes de réponse', 'licenceflow' ); ?></p>
                <?php foreach ( $ep['responses'] as $r ) : ?>
                    <span class="lflow-response-badge <?php echo esc_attr( $r[1] ); ?>"><?php echo esc_html( $r[0] ); ?></span>
                    <?php echo esc_html( $r[2] ); ?><br>
                <?php endforeach; ?>

                <!-- cURL example -->
                <p class="lflow-api-section-label"><?php esc_html_e( 'Exemple cURL', 'licenceflow' ); ?></p>
                <div class="lflow-api-code">
                    <?php echo nl2br( esc_html( $ep['example_curl'] ) ); ?>
                    <button class="lflow-api-copy" data-copy="<?php echo esc_attr( $ep['example_curl'] ); ?>">Copier</button>
                </div>

                <!-- Example response -->
                <p class="lflow-api-section-label"><?php esc_html_e( 'Exemple de réponse', 'licenceflow' ); ?></p>
                <div class="lflow-api-code">
                    <?php echo nl2br( esc_html( $ep['example_response'] ) ); ?>
                </div>

            </div>
        </div>
        <?php endforeach; ?>

    </div>

    <!-- Response object reference -->
    <div class="lflow-card">
        <h2><?php esc_html_e( 'Objet Licence — champs retournés', 'licenceflow' ); ?></h2>
        <table class="lflow-api-table">
            <thead>
                <tr>
                    <th style="width:220px;"><?php esc_html_e( 'Champ', 'licenceflow' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Type', 'licenceflow' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'licenceflow' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $fields = array(
                    array( 'license_id',                'integer', __( 'Identifiant unique de la licence', 'licenceflow' ) ),
                    array( 'product_id',                'integer', __( 'ID produit WooCommerce', 'licenceflow' ) ),
                    array( 'variation_id',              'integer', __( 'ID variation (0 = produit principal)', 'licenceflow' ) ),
                    array( 'license_type',              'string',  __( '<code>key</code> | <code>account</code> | <code>link</code> | <code>code</code>', 'licenceflow' ) ),
                    array( 'license_status',            'string',  __( '<code>available</code> | <code>sold</code> | <code>expired</code>', 'licenceflow' ) ),
                    array( 'valid',                     'integer', __( 'Validité client en jours (0 = illimité)', 'licenceflow' ) ),
                    array( 'delivre_x_times',           'integer', __( 'Nombre total de livraisons autorisées', 'licenceflow' ) ),
                    array( 'remaining_delivre_x_times', 'integer', __( 'Livraisons restantes', 'licenceflow' ) ),
                    array( 'order_id',                  'integer|null', __( 'ID de la dernière commande liée', 'licenceflow' ) ),
                    array( 'owner_email_address',       'string|null',  __( 'Email du dernier acheteur', 'licenceflow' ) ),
                    array( 'owner_first_name',          'string|null',  __( 'Prénom du dernier acheteur', 'licenceflow' ) ),
                    array( 'owner_last_name',           'string|null',  __( 'Nom du dernier acheteur', 'licenceflow' ) ),
                    array( 'sold_date',                 'string|null',  __( 'Date de la dernière livraison (YYYY-MM-DD)', 'licenceflow' ) ),
                    array( 'creation_date',             'string|null',  __( 'Date de création', 'licenceflow' ) ),
                    array( 'expiration_date',           'string|null',  __( 'Date d\'expiration admin (YYYY-MM-DD)', 'licenceflow' ) ),
                    array( 'license_note',              'string|null',  __( 'Note visible par le client', 'licenceflow' ) ),
                    array( 'admin_notes',               'string|null',  __( 'Note interne admin', 'licenceflow' ) ),
                    array( 'license_key',               'string',       __( 'Valeur chiffrée (uniquement si <code>include_key=true</code>)', 'licenceflow' ) ),
                    array( 'parsed_value',              'object',       __( 'Valeur déchiffrée et parsée (uniquement si <code>include_key=true</code>)', 'licenceflow' ) ),
                );
                foreach ( $fields as $f ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $f[0] ); ?></code></td>
                    <td style="color:#646970;"><?php echo esc_html( $f[1] ); ?></td>
                    <td><?php echo wp_kses( $f[2], array( 'code' => array() ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function lflowToggleEp(id, header) {
    var body = document.getElementById(id);
    var isOpen = body.classList.toggle('open');
    header.classList.toggle('open', isOpen);
    var icon = header.querySelector('.dashicons');
    if (icon) {
        icon.className = isOpen
            ? 'dashicons dashicons-arrow-up-alt2'
            : 'dashicons dashicons-arrow-down-alt2';
    }
}

document.querySelectorAll('.lflow-api-copy').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var text = this.getAttribute('data-copy');
        navigator.clipboard.writeText(text).then(function() {
            btn.textContent = '✓';
            setTimeout(function() { btn.textContent = 'Copier'; }, 1500);
        }).catch(function() {
            btn.textContent = '✗';
            setTimeout(function() { btn.textContent = 'Copier'; }, 1500);
        });
    });
});
</script>
