<?php
/**
 * LicenceFlow — Import / Export page
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

$security   = LicenceFlow_Security::get_instance();
$notice     = array();

// ── Handle Export ──────────────────────────────────────────────────────────────

if ( isset( $_POST['lflow_export_nonce'] ) ) {
    if ( ! $security->verify_nonce( sanitize_text_field( wp_unslash( $_POST['lflow_export_nonce'] ) ), 'export_licenses' ) ) {
        $notice = array( 'type' => 'error', 'msg' => __( 'Nonce invalide.', 'licenceflow' ) );
    } else {
        $export_args = array(
            'status'     => sanitize_key( $_POST['export_status'] ?? '' ),
            'product_id' => absint( $_POST['export_product_id'] ?? 0 ),
            'type'       => sanitize_key( $_POST['export_type'] ?? '' ),
            'per_page'   => 5000,
        );
        $result  = LicenceFlow_License_DB::get_list( $export_args );
        $licenses = $result['items'];

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="licenceflow-export-' . date( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'license_id', 'product_id', 'variation_id', 'license_type', 'license_status', 'license_value', 'expiration_date', 'valid', 'sold_date', 'order_id', 'owner_email', 'admin_notes' ) );

        foreach ( $licenses as $row ) {
            $type   = $row['license_type'] ?? 'key';
            $parsed = lflow_parse_license_value( $row['license_key'] ?? '', $type );

            if ( is_array( $parsed ) ) {
                $value = implode( '|', array_values( $parsed ) );
            } else {
                $value = (string) $parsed;
            }

            fputcsv( $out, array(
                $row['license_id'],
                $row['product_id'],
                $row['variation_id'],
                $type,
                $row['license_status'],
                $value,
                $row['expiration_date'] ?? '',
                $row['valid'] ?? 0,
                $row['sold_date'] ?? '',
                $row['order_id'] ?? '',
                $row['owner_email_address'] ?? '',
                $row['admin_notes'] ?? '',
            ) );
        }
        fclose( $out );
        exit;
    }
}

// ── Handle Import ──────────────────────────────────────────────────────────────

if ( isset( $_POST['lflow_import_nonce'] ) ) {
    if ( ! $security->verify_nonce( sanitize_text_field( wp_unslash( $_POST['lflow_import_nonce'] ) ), 'import_licenses' ) ) {
        $notice = array( 'type' => 'error', 'msg' => __( 'Nonce invalide.', 'licenceflow' ) );
    } elseif ( empty( $_FILES['import_csv']['tmp_name'] ) ) {
        $notice = array( 'type' => 'error', 'msg' => __( 'Veuillez sélectionner un fichier CSV.', 'licenceflow' ) );
    } else {
        $import_product_id = absint( $_POST['import_product_id'] ?? 0 );
        $file              = $_FILES['import_csv']['tmp_name'];
        $handle            = fopen( $file, 'r' );

        if ( ! $handle ) {
            $notice = array( 'type' => 'error', 'msg' => __( 'Impossible de lire le fichier.', 'licenceflow' ) );
        } else {
            $imported = 0;
            $skipped  = 0;
            $errors   = 0;
            $line     = 0;
            $valid_types    = array_keys( lflow_license_types() );
            $valid_statuses = array_keys( lflow_license_statuses() );

            while ( ( $row = fgetcsv( $handle ) ) !== false ) {
                $line++;
                if ( $line === 1 ) continue; // Skip header

                // Expected columns: license_type, license_value, [expiration_date], [valid], [admin_notes]
                // Product ID comes from the import form field
                $type   = sanitize_key( $row[0] ?? 'key' );
                $value  = $row[1] ?? '';
                $expiry = sanitize_text_field( $row[2] ?? '' );
                $valid  = absint( $row[3] ?? 0 );
                $notes  = sanitize_textarea_field( $row[4] ?? '' );

                if ( empty( $value ) || ! in_array( $type, $valid_types, true ) ) {
                    $errors++;
                    continue;
                }

                // For non-key types, assume pipe-delimited value
                if ( $type !== 'key' ) {
                    $parts   = explode( '|', $value );
                    $keys_by_type = array(
                        'account' => array( 'username', 'password' ),
                        'link'    => array( 'url', 'label' ),
                        'code'    => array( 'code', 'note' ),
                    );
                    $field_keys = $keys_by_type[ $type ] ?? array();
                    $arr = array();
                    foreach ( $field_keys as $i => $k ) {
                        $arr[ $k ] = sanitize_text_field( $parts[ $i ] ?? '' );
                    }
                    $serialized = lflow_serialize_license_value( $arr, $type );
                } else {
                    $serialized = sanitize_textarea_field( $value );
                }

                $data = array(
                    'product_id'   => $import_product_id ?: 0,
                    'license_key'  => $serialized,
                    'license_type' => $type,
                    'admin_notes'  => $notes,
                    'valid'        => $valid,
                );
                if ( $expiry && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expiry ) ) {
                    $data['expiration_date'] = $expiry;
                }

                $id = LicenceFlow_License_DB::insert( $data );
                if ( $id ) {
                    $imported++;
                } else {
                    $errors++;
                }
            }
            fclose( $handle );

            $notice = array(
                'type' => 'updated',
                'msg'  => sprintf(
                    /* translators: %1$d: imported, %2$d: skipped, %3$d: errors */
                    __( 'Import terminé : %1$d importée(s), %2$d ignorée(s), %3$d erreur(s).', 'licenceflow' ),
                    $imported, $skipped, $errors
                ),
            );
        }
    }
}

// Get products for dropdowns
$licensed_products = LicenceFlow_Product_Config::get_licensed_products_for_select();
?>
<div class="wrap lflow-wrap">

    <h1><?php esc_html_e( 'Import / Export', 'licenceflow' ); ?></h1>

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
            <p><?php echo esc_html( $notice['msg'] ); ?></p>
        </div>
    <?php endif; ?>

    <div class="lflow-ie-grid">

        <!-- Export -->
        <div class="lflow-card">
            <h2><?php esc_html_e( 'Exporter les licences (CSV)', 'licenceflow' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'lflow_export_licenses', 'lflow_export_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Produit', 'licenceflow' ); ?></label></th>
                        <td>
                            <select name="export_product_id">
                                <option value="0"><?php esc_html_e( 'Tous', 'licenceflow' ); ?></option>
                                <?php foreach ( $licensed_products as $pid => $pname ) : ?>
                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pname ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Statut', 'licenceflow' ); ?></label></th>
                        <td>
                            <select name="export_status">
                                <option value=""><?php esc_html_e( 'Tous', 'licenceflow' ); ?></option>
                                <?php foreach ( lflow_license_statuses() as $slug => $label ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Type', 'licenceflow' ); ?></label></th>
                        <td>
                            <select name="export_type">
                                <option value=""><?php esc_html_e( 'Tous', 'licenceflow' ); ?></option>
                                <?php foreach ( lflow_license_types() as $slug => $label ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="description"><?php esc_html_e( 'Colonnes : license_id, product_id, variation_id, license_type, license_status, license_value, expiration_date, valid, sold_date, order_id, owner_email, admin_notes.', 'licenceflow' ); ?></p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Télécharger le CSV', 'licenceflow' ); ?></button></p>
            </form>
        </div>

        <!-- Import -->
        <div class="lflow-card">
            <h2><?php esc_html_e( 'Importer des licences (CSV)', 'licenceflow' ); ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'lflow_import_licenses', 'lflow_import_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Produit cible', 'licenceflow' ); ?></label></th>
                        <td>
                            <select name="import_product_id">
                                <option value="0"><?php esc_html_e( '— Aucun (voir CSV) —', 'licenceflow' ); ?></option>
                                <?php foreach ( $licensed_products as $pid => $pname ) : ?>
                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pname ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Fichier CSV', 'licenceflow' ); ?></label></th>
                        <td><input type="file" name="import_csv" accept=".csv"></td>
                    </tr>
                </table>

                <p class="description">
                    <?php esc_html_e( 'Format CSV (sans en-tête) : license_type | license_value | expiration_date (Y-m-d) | valid (jours) | admin_notes.', 'licenceflow' ); ?><br>
                    <?php esc_html_e( 'Pour le type "account" : username|password. Pour "link" : url|label. Pour "code" : code|note.', 'licenceflow' ); ?>
                </p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Importer', 'licenceflow' ); ?></button></p>
            </form>
        </div>

    </div>

</div>
