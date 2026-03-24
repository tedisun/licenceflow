<?php
/**
 * LicenceFlow — Add license page
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

$security = LicenceFlow_Security::get_instance();

// Handle form submission (non-AJAX fallback)
$notice = array();
if ( isset( $_POST['lflow_save_license_nonce'] ) ) {
    if ( ! $security->verify_nonce( sanitize_text_field( wp_unslash( $_POST['lflow_save_license_nonce'] ) ), 'save_license' ) ) {
        $notice = array( 'type' => 'error', 'msg' => __( 'Nonce invalide.', 'licenceflow' ) );
    } else {
        $type      = sanitize_key( $_POST['license_type'] ?? 'key' );
        $raw_value = $_POST['license_value'] ?? '';

        // Parse || note syntax for single-value types: KEY || note visible client
        $inline_note = '';
        if ( in_array( $type, array( 'key', 'code' ), true ) ) {
            $raw_text = is_array( $raw_value ) ? (string) ( $raw_value['key'] ?? '' ) : (string) $raw_value;
            if ( strpos( $raw_text, '||' ) !== false ) {
                $parts = explode( '||', $raw_text, 2 );
                if ( is_array( $raw_value ) ) {
                    $raw_value['key'] = trim( $parts[0] );
                } else {
                    $raw_value = trim( $parts[0] );
                }
                $inline_note = trim( $parts[1] );
            }
        }

        $clean_value = $security->sanitize_license_field( $raw_value, $type );
        $serialized  = lflow_serialize_license_value( $clean_value, $type );

        $delivre_x_times = max( 1, $security->sanitize_int( $_POST['delivre_x_times'] ?? 1 ) );
        $data = array(
            'product_id'                => absint( $_POST['product_id'] ?? 0 ),
            'variation_id'              => absint( $_POST['variation_id'] ?? 0 ),
            'license_key'               => $serialized,
            'license_type'              => $type,
            'license_status'            => sanitize_key( $_POST['license_status'] ?? 'available' ),
            'expiration_date'           => $security->sanitize_date( $_POST['expiration_date'] ?? '' ),
            'valid'                     => $security->sanitize_int( $_POST['valid'] ?? 0 ),
            'license_note'              => sanitize_textarea_field( ! empty( $_POST['license_note'] ) ? $_POST['license_note'] : $inline_note ),
            'admin_notes'               => sanitize_textarea_field( $_POST['admin_notes'] ?? '' ),
            'delivre_x_times'           => $delivre_x_times,
            'remaining_delivre_x_times' => $delivre_x_times,
        );
        if ( $data['expiration_date'] === '' ) unset( $data['expiration_date'] );

        $id = LicenceFlow_License_DB::insert( $data );
        if ( $id ) {
            LicenceFlow_Core::get_instance()->sync_product_stock( $data['product_id'], $data['variation_id'] );
            wp_redirect( LicenceFlow_Admin::licenses_url( array( 'added' => 1 ) ) );
            exit;
        }
        $notice = array( 'type' => 'error', 'msg' => __( 'Erreur lors de l\'enregistrement.', 'licenceflow' ) );
    }
}

// Licensed products for dropdown
$licensed_products = LicenceFlow_Product_Config::get_licensed_products_for_select();
?>
<div class="wrap lflow-wrap">

    <h1><?php esc_html_e( 'Ajouter une licence', 'licenceflow' ); ?></h1>

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['msg'] ); ?></p></div>
    <?php endif; ?>

    <div class="lflow-form-wrap">
        <form method="post" action="" id="lflow-add-license-form">
            <?php wp_nonce_field( 'lflow_save_license', 'lflow_save_license_nonce' ); ?>
            <input type="hidden" name="license_id" value="0">

            <table class="form-table lflow-form-table">
                <tbody>

                <!-- Product -->
                <tr>
                    <th><label for="lflow-product-id"><?php esc_html_e( 'Produit', 'licenceflow' ); ?> <span class="required">*</span></label></th>
                    <td>
                        <select id="lflow-product-id" name="product_id" required style="min-width:280px;">
                            <option value=""><?php esc_html_e( '— Sélectionner un produit —', 'licenceflow' ); ?></option>
                            <?php foreach ( $licensed_products as $pid => $pname ) : ?>
                                <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pname ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="lflow-field-hint"><?php esc_html_e( 'Seuls les produits avec LicenceFlow activé apparaissent ici.', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                <!-- Variation -->
                <tr id="lflow-variation-row" style="display:none;">
                    <th><label for="lflow-variation-id"><?php esc_html_e( 'Variation', 'licenceflow' ); ?></label></th>
                    <td>
                        <select id="lflow-variation-id" name="variation_id" style="min-width:280px;">
                            <option value="0"><?php esc_html_e( '— Toutes les variations —', 'licenceflow' ); ?></option>
                        </select>
                    </td>
                </tr>

                <!-- License type (read-only display — set by product config) -->
                <tr>
                    <th><?php esc_html_e( 'Type de licence', 'licenceflow' ); ?></th>
                    <td>
                        <input type="hidden" id="lflow-license-type" name="license_type" value="key">
                        <span id="lflow-license-type-label" style="font-weight:600; color:#2271b1;">
                            🔑 <?php esc_html_e( 'Clé de licence', 'licenceflow' ); ?>
                        </span>
                        <p class="lflow-field-hint"><?php esc_html_e( 'Défini par la configuration du produit.', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                <!-- License value — dynamic fields -->
                <tr>
                    <th><label><?php esc_html_e( 'Valeur de la licence', 'licenceflow' ); ?> <span class="required">*</span></label></th>
                    <td>
                        <!-- key -->
                        <div class="lflow-license-field-group lflow-active" data-type="key" id="lflow-field-key">
                            <textarea name="license_value[key]" rows="3" style="width:100%; font-family:monospace;" placeholder="AAAA-BBBB-CCCC-DDDD"></textarea>
                            <p class="lflow-field-hint"><?php esc_html_e( 'Clé de licence ou code texte.', 'licenceflow' ); ?> <?php esc_html_e( 'Astuce : saisissez', 'licenceflow' ); ?> <code>valeur || note client</code> <?php esc_html_e( 'pour renseigner la note visible en même temps.', 'licenceflow' ); ?></p>
                        </div>

                        <!-- account -->
                        <div class="lflow-license-field-group" data-type="account" id="lflow-field-account">
                            <table style="border:0; padding:0;">
                                <tr>
                                    <td style="padding:0 10px 8px 0;"><label><?php esc_html_e( 'Identifiant', 'licenceflow' ); ?></label></td>
                                    <td style="padding:0 0 8px;"><input type="text" name="license_value[username]" style="min-width:240px;" placeholder="user@exemple.com"></td>
                                </tr>
                                <tr>
                                    <td style="padding:0 10px 0 0;"><label><?php esc_html_e( 'Mot de passe', 'licenceflow' ); ?></label></td>
                                    <td><input type="text" name="license_value[password]" style="min-width:240px;" placeholder="••••••••" autocomplete="off"></td>
                                </tr>
                            </table>
                        </div>

                        <!-- link -->
                        <div class="lflow-license-field-group" data-type="link" id="lflow-field-link">
                            <table style="border:0; padding:0;">
                                <tr>
                                    <td style="padding:0 10px 8px 0;"><label><?php esc_html_e( 'URL', 'licenceflow' ); ?></label></td>
                                    <td style="padding:0 0 8px;"><input type="url" name="license_value[url]" style="min-width:300px;" placeholder="https://app.exemple.com/invite/xyz"></td>
                                </tr>
                                <tr>
                                    <td style="padding:0 10px 0 0;"><label><?php esc_html_e( 'Libellé', 'licenceflow' ); ?></label></td>
                                    <td><input type="text" name="license_value[label]" style="min-width:240px;" placeholder="<?php esc_attr_e( 'Cliquez pour activer', 'licenceflow' ); ?>"></td>
                                </tr>
                            </table>
                        </div>

                        <!-- code -->
                        <div class="lflow-license-field-group" data-type="code" id="lflow-field-code">
                            <table style="border:0; padding:0;">
                                <tr>
                                    <td style="padding:0 10px 8px 0;"><label><?php esc_html_e( 'Code', 'licenceflow' ); ?></label></td>
                                    <td style="padding:0 0 8px;"><input type="text" name="license_value[code]" style="min-width:200px; font-family:monospace;" placeholder="PROMO2025"></td>
                                </tr>
                                <tr>
                                    <td style="padding:0 10px 0 0;"><label><?php esc_html_e( 'Remarque', 'licenceflow' ); ?></label></td>
                                    <td><textarea name="license_value[note]" rows="2" style="min-width:300px;" placeholder="<?php esc_attr_e( 'Ex : valable 30 jours après utilisation', 'licenceflow' ); ?>"></textarea></td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>

                <!-- Status -->
                <tr>
                    <th><label for="lflow-license-status"><?php esc_html_e( 'Statut', 'licenceflow' ); ?></label></th>
                    <td>
                        <select id="lflow-license-status" name="license_status">
                            <?php foreach ( lflow_license_statuses() as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, 'available' ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <!-- Delivery times -->
                <tr>
                    <th><label for="lflow-delivre-x-times"><?php esc_html_e( 'Livrable X fois', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="number" id="lflow-delivre-x-times" name="delivre_x_times" value="1" min="1" style="width:80px;">
                        <p class="lflow-field-hint"><?php esc_html_e( 'Nombre de fois que cette licence peut être livrée à un client (par ex. re-livraison après remboursement).', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                <!-- Admin expiry date -->
                <tr>
                    <th><label for="lflow-expiration-date"><?php esc_html_e( 'Date d\'expiration admin', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="date" id="lflow-expiration-date" name="expiration_date" value="">
                        <p class="lflow-field-hint"><?php esc_html_e( 'Date réelle d\'expiration de la licence (visible seulement par l\'admin). Vous recevrez une alerte avant cette date.', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                <!-- Customer validity (days) -->
                <tr>
                    <th><label for="lflow-valid"><?php esc_html_e( 'Validité client (jours)', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="number" id="lflow-valid" name="valid" value="0" min="0" style="width:80px;">
                        <p class="lflow-field-hint"><?php esc_html_e( 'Nombre de jours de validité à compter de la date d\'achat. Affiché au client comme "À utiliser avant le [date d\'achat + X jours]". Laisser 0 pour aucune limite.', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                <!-- Customer note (license_note) -->
                <tr>
                    <th><label for="lflow-license-note"><?php esc_html_e( 'Note client', 'licenceflow' ); ?></label></th>
                    <td>
                        <textarea id="lflow-license-note" name="license_note" rows="2" style="width:100%;" placeholder="<?php esc_attr_e( 'Ex : Code Antivirus et VPN (réf: I37)', 'licenceflow' ); ?>"></textarea>
                        <p class="lflow-field-hint"><?php esc_html_e( 'Texte affiché sous la licence dans l\'email, la page de confirmation, l\'historique et le PDF. Visible par le client.', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                <!-- Admin notes -->
                <tr>
                    <th><label for="lflow-admin-notes"><?php esc_html_e( 'Notes internes', 'licenceflow' ); ?></label></th>
                    <td>
                        <textarea id="lflow-admin-notes" name="admin_notes" rows="3" style="width:100%;"></textarea>
                        <p class="lflow-field-hint"><?php esc_html_e( 'Notes visibles uniquement par l\'admin. Jamais transmises au client.', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Ajouter la licence', 'licenceflow' ); ?></button>
                <a href="<?php echo esc_url( LicenceFlow_Admin::licenses_url() ); ?>" class="button"><?php esc_html_e( 'Annuler', 'licenceflow' ); ?></a>
            </p>

        </form>
    </div>

</div>
