<?php
/**
 * LicenceFlow — Edit license page
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

$security   = LicenceFlow_Security::get_instance();
$license_id = absint( $_GET['license_id'] ?? 0 );
$license    = $license_id ? LicenceFlow_License_DB::get( $license_id ) : null;

if ( ! $license ) {
    echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Licence introuvable.', 'licenceflow' ) . '</p></div></div>';
    return;
}

// Handle form submission
$notice = array();
if ( isset( $_POST['lflow_save_license_nonce'] ) ) {
    if ( ! $security->verify_nonce( sanitize_text_field( wp_unslash( $_POST['lflow_save_license_nonce'] ) ), 'save_license' ) ) {
        $notice = array( 'type' => 'error', 'msg' => __( 'Nonce invalide.', 'licenceflow' ) );
    } else {
        $type        = sanitize_key( $_POST['license_type'] ?? 'key' );
        $raw_value   = $_POST['license_value'] ?? '';
        $clean_value = $security->sanitize_license_field( $raw_value, $type );
        $serialized  = lflow_serialize_license_value( $clean_value, $type );

        $data = array(
            'product_id'      => absint( $_POST['product_id'] ?? $license['product_id'] ),
            'variation_id'    => absint( $_POST['variation_id'] ?? $license['variation_id'] ),
            'license_key'     => $serialized,
            'license_type'    => $type,
            'license_status'  => sanitize_key( $_POST['license_status'] ?? 'available' ),
            'expiration_date' => $security->sanitize_date( $_POST['expiration_date'] ?? '' ),
            'valid'           => $security->sanitize_int( $_POST['valid'] ?? 0 ),
            'license_note'    => sanitize_textarea_field( $_POST['license_note'] ?? '' ),
            'admin_notes'     => sanitize_textarea_field( $_POST['admin_notes'] ?? '' ),
            'delivre_x_times' => max( 1, $security->sanitize_int( $_POST['delivre_x_times'] ?? 1 ) ),
        );
        // Update remaining only if admin explicitly submitted it; cap at delivre_x_times
        $submitted_remaining = $security->sanitize_int( $_POST['remaining_delivre_x_times'] ?? -1 );
        if ( $submitted_remaining >= 0 ) {
            $data['remaining_delivre_x_times'] = min( $submitted_remaining, $data['delivre_x_times'] );
        }
        if ( $data['expiration_date'] === '' ) {
            $data['expiration_date'] = null;
        }

        $ok = LicenceFlow_License_DB::update( $license_id, $data );
        if ( $ok ) {
            $license = LicenceFlow_License_DB::get( $license_id );
            $notice  = array( 'type' => 'updated', 'msg' => __( 'Licence mise à jour.', 'licenceflow' ) );
        } else {
            $notice = array( 'type' => 'error', 'msg' => __( 'Erreur lors de la mise à jour.', 'licenceflow' ) );
        }
    }
}

// Added redirect notice
if ( isset( $_GET['added'] ) ) {
    $notice = array( 'type' => 'updated', 'msg' => __( 'Licence ajoutée avec succès.', 'licenceflow' ) );
}

// Parse current license value
$type        = $license['license_type'] ?? 'key';
$parsed      = lflow_parse_license_value( $license['license_key'] ?? '', $type );
$value_key   = $type === 'key' ? (string) $parsed : '';
$value_arr   = is_array( $parsed ) ? $parsed : array();

$licensed_products = LicenceFlow_Product_Config::get_licensed_products_for_select();
$variations        = LicenceFlow_Product_Config::get_variation_options( (int) $license['product_id'] );
?>
<div class="wrap lflow-wrap">

    <h1>
        <?php esc_html_e( 'Modifier la licence', 'licenceflow' ); ?>
        <span style="font-size:.7em; color:#646970; font-weight:400;">#<?php echo absint( $license_id ); ?></span>
    </h1>
    <a href="<?php echo esc_url( LicenceFlow_Admin::licenses_url() ); ?>">&larr; <?php esc_html_e( 'Retour à la liste', 'licenceflow' ); ?></a>

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible" style="margin-top:16px;">
            <p><?php echo esc_html( $notice['msg'] ); ?></p>
        </div>
    <?php endif; ?>

    <!-- Meta info -->
    <div style="margin:16px 0; padding:12px 16px; background:#f6f7f7; border-radius:4px; font-size:.875rem; color:#3c434a;">
        <?php if ( ! empty( $license['owner_email_address'] ) ) : ?>
            <strong><?php esc_html_e( 'Client :', 'licenceflow' ); ?></strong>
            <?php echo esc_html( trim( $license['owner_first_name'] . ' ' . $license['owner_last_name'] ) ); ?>
            (<?php echo esc_html( $license['owner_email_address'] ); ?>)
            <?php if ( ! empty( $license['order_id'] ) ) :
                $order_url = get_edit_post_link( absint( $license['order_id'] ) );
                if ( ! $order_url ) {
                    // HPOS
                    $order_url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . absint( $license['order_id'] ) );
                }
                ?>
                &mdash; <?php esc_html_e( 'Commande', 'licenceflow' ); ?>
                <a href="<?php echo esc_url( $order_url ); ?>">#<?php echo absint( $license['order_id'] ); ?></a>
            <?php endif; ?>
            &mdash; <?php esc_html_e( 'Vendu le', 'licenceflow' ); ?>
            <?php echo esc_html( lflow_format_date( $license['sold_date'] ?? '' ) ); ?>

            <?php if ( (int) ( $license['valid'] ?? 0 ) > 0 && ! empty( $license['sold_date'] ) ) :
                $customer_expiry = lflow_customer_expiry_date( $license['sold_date'], (int) $license['valid'] );
                if ( $customer_expiry ) : ?>
                    &mdash; <strong><?php esc_html_e( 'Expiration client :', 'licenceflow' ); ?></strong>
                    <?php echo esc_html( $customer_expiry ); ?>
                <?php endif;
            endif; ?>
        <?php else : ?>
            <em><?php esc_html_e( 'Licence non encore vendue.', 'licenceflow' ); ?></em>
        <?php endif; ?>
        &mdash; <?php esc_html_e( 'Créée le', 'licenceflow' ); ?> <?php echo esc_html( lflow_format_date( $license['creation_date'] ?? '' ) ); ?>
    </div>

    <div class="lflow-form-wrap">
        <form method="post" action="">
            <?php wp_nonce_field( 'lflow_save_license', 'lflow_save_license_nonce' ); ?>
            <input type="hidden" name="license_id" value="<?php echo absint( $license_id ); ?>">

            <table class="form-table lflow-form-table">
                <tbody>

                <!-- Product -->
                <tr>
                    <th><label for="lflow-product-id"><?php esc_html_e( 'Produit', 'licenceflow' ); ?></label></th>
                    <td>
                        <select id="lflow-product-id" name="product_id" class="lflow-product-select" style="min-width:280px;">
                            <option value=""><?php esc_html_e( '— Sélectionner —', 'licenceflow' ); ?></option>
                            <?php foreach ( $licensed_products as $pid => $pname ) : ?>
                                <option value="<?php echo esc_attr( $pid ); ?>" <?php selected( (int) $license['product_id'], $pid ); ?>>
                                    <?php echo esc_html( $pname ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <!-- Variation -->
                <tr id="lflow-variation-row" <?php echo empty( $variations ) ? 'style="display:none;"' : ''; ?>>
                    <th><label for="lflow-variation-id"><?php esc_html_e( 'Variation', 'licenceflow' ); ?></label></th>
                    <td>
                        <select id="lflow-variation-id" name="variation_id" style="min-width:280px;">
                            <option value="0"><?php esc_html_e( '— Toutes les variations —', 'licenceflow' ); ?></option>
                            <?php foreach ( $variations as $vid => $vname ) : ?>
                                <option value="<?php echo esc_attr( $vid ); ?>" <?php selected( (int) $license['variation_id'], $vid ); ?>>
                                    <?php echo esc_html( $vname ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <!-- License type -->
                <tr>
                    <th><?php esc_html_e( 'Type de licence', 'licenceflow' ); ?></th>
                    <td>
                        <input type="hidden" id="lflow-license-type" name="license_type" value="<?php echo esc_attr( $type ); ?>">
                        <span id="lflow-license-type-label" style="font-weight:600; color:#2271b1;">
                            <?php echo esc_html( lflow_license_type_label( $type ) ); ?>
                        </span>
                    </td>
                </tr>

                <!-- License value -->
                <tr>
                    <th><label><?php esc_html_e( 'Valeur de la licence', 'licenceflow' ); ?></label></th>
                    <td>
                        <!-- key -->
                        <div class="lflow-license-field-group <?php echo $type === 'key' ? 'lflow-active' : ''; ?>" data-type="key" id="lflow-field-key">
                            <textarea name="license_value[key]" rows="3" style="width:100%; font-family:monospace;"><?php echo esc_textarea( $value_key ); ?></textarea>
                        </div>

                        <!-- account -->
                        <div class="lflow-license-field-group <?php echo $type === 'account' ? 'lflow-active' : ''; ?>" data-type="account" id="lflow-field-account">
                            <table style="border:0; padding:0;">
                                <tr>
                                    <td style="padding:0 10px 8px 0;"><label><?php esc_html_e( 'Identifiant', 'licenceflow' ); ?></label></td>
                                    <td style="padding:0 0 8px;"><input type="text" name="license_value[username]" style="min-width:240px;" value="<?php echo esc_attr( $value_arr['username'] ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <td style="padding:0 10px 0 0;"><label><?php esc_html_e( 'Mot de passe', 'licenceflow' ); ?></label></td>
                                    <td><input type="text" name="license_value[password]" style="min-width:240px;" value="<?php echo esc_attr( $value_arr['password'] ?? '' ); ?>" autocomplete="off"></td>
                                </tr>
                            </table>
                        </div>

                        <!-- link -->
                        <div class="lflow-license-field-group <?php echo $type === 'link' ? 'lflow-active' : ''; ?>" data-type="link" id="lflow-field-link">
                            <table style="border:0; padding:0;">
                                <tr>
                                    <td style="padding:0 10px 8px 0;"><label><?php esc_html_e( 'URL', 'licenceflow' ); ?></label></td>
                                    <td style="padding:0 0 8px;"><input type="url" name="license_value[url]" style="min-width:300px;" value="<?php echo esc_attr( $value_arr['url'] ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <td style="padding:0 10px 0 0;"><label><?php esc_html_e( 'Libellé', 'licenceflow' ); ?></label></td>
                                    <td><input type="text" name="license_value[label]" style="min-width:240px;" value="<?php echo esc_attr( $value_arr['label'] ?? '' ); ?>"></td>
                                </tr>
                            </table>
                        </div>

                        <!-- code -->
                        <div class="lflow-license-field-group <?php echo $type === 'code' ? 'lflow-active' : ''; ?>" data-type="code" id="lflow-field-code">
                            <table style="border:0; padding:0;">
                                <tr>
                                    <td style="padding:0 10px 8px 0;"><label><?php esc_html_e( 'Code', 'licenceflow' ); ?></label></td>
                                    <td style="padding:0 0 8px;"><input type="text" name="license_value[code]" style="min-width:200px; font-family:monospace;" value="<?php echo esc_attr( $value_arr['code'] ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <td style="padding:0 10px 0 0;"><label><?php esc_html_e( 'Remarque', 'licenceflow' ); ?></label></td>
                                    <td><textarea name="license_value[note]" rows="2" style="min-width:300px;"><?php echo esc_textarea( $value_arr['note'] ?? '' ); ?></textarea></td>
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
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $license['license_status'], $slug ); ?>>
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
                        <input type="number" id="lflow-delivre-x-times" name="delivre_x_times" value="<?php echo absint( $license['delivre_x_times'] ?? 1 ); ?>" min="1" style="width:80px;">
                        <p class="description" style="margin-top:4px;"><?php esc_html_e( 'Nombre maximum de livraisons pour cette licence.', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                <!-- Remaining deliveries -->
                <tr>
                    <th><label for="lflow-remaining-delivre"><?php esc_html_e( 'Livraisons restantes', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="number" id="lflow-remaining-delivre" name="remaining_delivre_x_times" value="<?php echo absint( $license['remaining_delivre_x_times'] ?? 0 ); ?>" min="0" style="width:80px;">
                        <p class="description" style="margin-top:4px;"><?php esc_html_e( 'Réinitialisez à la valeur max pour rendre la licence de nouveau livrable.', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                <!-- Admin expiry date -->
                <tr>
                    <th><label for="lflow-expiration-date"><?php esc_html_e( 'Date d\'expiration admin', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="date" id="lflow-expiration-date" name="expiration_date"
                            value="<?php echo ( $license['expiration_date'] && $license['expiration_date'] !== '0000-00-00' ) ? esc_attr( $license['expiration_date'] ) : ''; ?>">
                        <p class="lflow-field-hint"><?php esc_html_e( 'Visible uniquement par l\'admin. Alerte envoyée X jours avant.', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                <!-- Customer validity -->
                <tr>
                    <th><label for="lflow-valid"><?php esc_html_e( 'Validité client (jours)', 'licenceflow' ); ?></label></th>
                    <td>
                        <input type="number" id="lflow-valid" name="valid" value="<?php echo absint( $license['valid'] ?? 0 ); ?>" min="0" style="width:80px;">
                        <?php if ( ! empty( $license['sold_date'] ) && (int)( $license['valid'] ?? 0 ) > 0 ) :
                            $ce = lflow_customer_expiry_date( $license['sold_date'], (int) $license['valid'] );
                            if ( $ce ) : ?>
                                <span style="color:#646970; margin-left:8px; font-size:.875rem;">→ <?php echo esc_html( $ce ); ?></span>
                            <?php endif;
                        endif; ?>
                    </td>
                </tr>

                <!-- Customer note -->
                <tr>
                    <th><label for="lflow-license-note"><?php esc_html_e( 'Note client', 'licenceflow' ); ?></label></th>
                    <td>
                        <textarea id="lflow-license-note" name="license_note" rows="2" style="width:100%;"><?php echo esc_textarea( $license['license_note'] ?? '' ); ?></textarea>
                        <p class="lflow-field-hint"><?php esc_html_e( 'Affiché sous la licence dans l\'email, la page de confirmation, l\'historique et le PDF. Visible par le client.', 'licenceflow' ); ?></p>
                    </td>
                </tr>

                <!-- Admin notes -->
                <tr>
                    <th><label for="lflow-admin-notes"><?php esc_html_e( 'Notes internes', 'licenceflow' ); ?></label></th>
                    <td>
                        <textarea id="lflow-admin-notes" name="admin_notes" rows="3" style="width:100%;"><?php echo esc_textarea( $license['admin_notes'] ?? '' ); ?></textarea>
                    </td>
                </tr>

                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'licenceflow' ); ?></button>
                <a href="<?php echo esc_url( LicenceFlow_Admin::licenses_url() ); ?>" class="button"><?php esc_html_e( 'Annuler', 'licenceflow' ); ?></a>
            </p>

        </form>
    </div>

</div>
