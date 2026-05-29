<?php
/**
 * LicenceFlow — Import / Export page
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

$security = LicenceFlow_Security::get_instance();
$notice   = array();

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
        $result   = LicenceFlow_License_DB::get_list( $export_args );
        $licenses = $result['items'];

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="licenceflow-export-' . date( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'license_id', 'product_id', 'variation_id', 'license_type', 'license_status', 'license_value', 'expiration_date', 'valid', 'sold_date', 'order_id', 'owner_email', 'license_note', 'admin_notes' ) );

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
                $row['license_note'] ?? '',
                $row['admin_notes'] ?? '',
            ) );
        }
        fclose( $out );
        exit;
    }
}

// ── Handle TXT Import ──────────────────────────────────────────────────────────

if ( isset( $_POST['lflow_txt_import_nonce'] ) ) {
    if ( ! $security->verify_nonce( sanitize_text_field( wp_unslash( $_POST['lflow_txt_import_nonce'] ) ), 'txt_import_licenses' ) ) {
        $notice = array( 'type' => 'error', 'msg' => __( 'Nonce invalide.', 'licenceflow' ) );
    } elseif ( empty( $_FILES['import_txt']['tmp_name'] ) ) {
        $notice = array( 'type' => 'error', 'msg' => __( 'Veuillez sélectionner un fichier .txt.', 'licenceflow' ) );
    } else {
        $txt_product_id   = absint( $_POST['txt_product_id'] ?? 0 );
        $txt_variation_id = absint( $_POST['txt_variation_id'] ?? 0 );
        $txt_type         = sanitize_key( $_POST['txt_license_type'] ?? 'key' );
        $txt_delivre      = max( 1, absint( $_POST['txt_delivre_x_times'] ?? 1 ) );
        $txt_status       = sanitize_key( $_POST['txt_license_status'] ?? 'available' );
        $txt_valid        = absint( $_POST['txt_valid'] ?? 0 );
        $txt_expiry       = sanitize_text_field( $_POST['txt_expiration_date'] ?? '' );
        $txt_notes        = sanitize_textarea_field( $_POST['txt_admin_notes'] ?? '' );
        $txt_license_note = sanitize_textarea_field( $_POST['txt_license_note'] ?? '' );

        $valid_types    = array_keys( lflow_license_types() );
        $valid_statuses = array_keys( lflow_license_statuses() );

        if ( ! in_array( $txt_type, $valid_types, true ) ) { $txt_type = 'key'; }
        if ( ! in_array( $txt_status, $valid_statuses, true ) ) { $txt_status = 'available'; }

        $content = file_get_contents( $_FILES['import_txt']['tmp_name'] );
        $lines   = preg_split( '/\r?\n/', $content );

        $verify_online = ! empty( $_POST['txt_verify_online'] ) && $txt_type === 'key';
        $results_by_key = array();

        if ( $verify_online ) {
            $keys_to_verify = array();
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( $line === '' ) continue;
                if ( strpos( $line, '||' ) !== false ) {
                    $parts_note = explode( '||', $line, 2 );
                    $line = trim( $parts_note[0] );
                }
                if ( preg_match( '/^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/i', $line ) ) {
                    $keys_to_verify[] = $line;
                }
            }

            if ( ! empty( $keys_to_verify ) ) {
                $batch_size = 30;
                $chunks = array_chunk( $keys_to_verify, $batch_size );
                $url = 'https://licenceflow-checker.app.tedisun.com/v1/check';
                $api_key = 'sk_lf_7b58cde3e5e4fa6b51df2f6d2e8b6a382e71d3c05b8aef52968840c1d683a219';

                foreach ( $chunks as $chunk ) {
                    $response = wp_remote_post( $url, array(
                        'headers' => array(
                            'Content-Type'          => 'application/json',
                            'X-LicenceFlow-Api-Key' => $api_key,
                        ),
                        'body'    => wp_json_encode( array( 'keys' => $chunk ) ),
                        'timeout' => 30,
                    ) );

                    if ( ! is_wp_error( $response ) ) {
                        $body_res = wp_remote_retrieve_body( $response );
                        $data = json_decode( $body_res, true );
                        if ( ! empty( $data['success'] ) && ! empty( $data['results'] ) ) {
                            foreach ( $data['results'] as $res ) {
                                if ( ! empty( $res['key'] ) ) {
                                    $results_by_key[ strtoupper( $res['key'] ) ] = $res;
                                }
                            }
                        }
                    }
                }
            }
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = 0;
        $deactivated_count = 0;

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( $line === '' ) { $skipped++; continue; }

            // Parse inline note: "LICENSE_VALUE || note visible par le client"
            $inline_note = $txt_license_note; // default to form field
            if ( strpos( $line, '||' ) !== false ) {
                $parts_note  = explode( '||', $line, 2 );
                $line        = trim( $parts_note[0] );
                $inline_note = sanitize_textarea_field( trim( $parts_note[1] ) );
            }

            // Check online validation result if requested
            $orig_status = $txt_status;
            $status_msg = '';
            if ( $verify_online && preg_match( '/^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/i', $line ) ) {
                $up_key = strtoupper( $line );
                if ( isset( $results_by_key[ $up_key ] ) ) {
                    $res = $results_by_key[ $up_key ];
                    if ( empty( $res['valid'] ) ) {
                        $txt_status = 'inactive';
                        $status_msg = $res['message'] ?? 'Bloquée par Microsoft.';
                        $deactivated_count++;
                    }
                }
            }

            // Serialize based on type
            if ( $txt_type === 'key' ) {
                $serialized = sanitize_textarea_field( $line );
            } else {
                // For other types, expect pipe-separated values on each line
                $parts = explode( '|', $line );
                $keys_by_type = array(
                    'account' => array( 'username', 'password' ),
                    'link'    => array( 'url', 'label' ),
                    'code'    => array( 'code', 'note' ),
                );
                $field_keys = $keys_by_type[ $txt_type ] ?? array();
                $arr = array();
                foreach ( $field_keys as $i => $k ) {
                    $arr[ $k ] = sanitize_text_field( $parts[ $i ] ?? '' );
                }
                $serialized = lflow_serialize_license_value( $arr, $txt_type );
            }

            if ( $serialized === '' ) { $errors++; continue; }

            $data = array(
                'product_id'                => $txt_product_id,
                'variation_id'              => $txt_variation_id,
                'license_key'               => $serialized,
                'license_type'              => $txt_type,
                'license_status'            => $txt_status,
                'delivre_x_times'           => $txt_delivre,
                'remaining_delivre_x_times' => $txt_delivre,
                'valid'                     => $txt_valid,
                'license_note'              => $inline_note,
                'admin_notes'               => $status_msg ? trim( $txt_notes . "\n[Import] Clé détectée comme bloquée/invalide lors de l'import : " . $status_msg ) : $txt_notes,
            );
            
            // Restore status
            $txt_status = $orig_status;
            if ( $txt_expiry && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $txt_expiry ) ) {
                $data['expiration_date'] = $txt_expiry;
            }

            $id = LicenceFlow_License_DB::insert( $data );
            if ( $id ) {
                $imported++;
            } else {
                $errors++;
            }
        }

        // Sync stock if applicable
        if ( $imported > 0 && LicenceFlow_Settings::is_on( 'lflow_stock_sync' ) ) {
            LicenceFlow_Core::get_instance()->sync_product_stock( $txt_product_id, $txt_variation_id );
        }

        $msg = sprintf(
            /* translators: %1$d: imported, %2$d: skipped, %3$d: errors */
            __( 'Import TXT terminé : %1$d importée(s), %2$d ligne(s) vide(s) ignorée(s), %3$d erreur(s).', 'licenceflow' ),
            $imported, $skipped, $errors
        );
        if ( $deactivated_count > 0 ) {
            $msg .= ' ' . sprintf( __( '⚠️ %d clé(s) Microsoft bloquée(s) détectée(s) et désactivée(s) automatiquement.', 'licenceflow' ), $deactivated_count );
        }

        $notice = array(
            'type' => $errors || $deactivated_count ? 'warning' : 'updated',
            'msg'  => $msg,
        );
    }
}

// ── Handle CSV Import ──────────────────────────────────────────────────────────

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
            $deactivated_count = 0;
            $valid_types    = array_keys( lflow_license_types() );
            $valid_statuses = array_keys( lflow_license_statuses() );

            $verify_online = ! empty( $_POST['csv_verify_online'] );
            $results_by_key = array();
            $rows_data = array();

            while ( ( $row = fgetcsv( $handle ) ) !== false ) {
                $line++;
                if ( $line === 1 ) continue; // Skip header
                $rows_data[] = $row;
            }

            if ( $verify_online ) {
                $keys_to_verify = array();
                foreach ( $rows_data as $row_item ) {
                    $type = sanitize_key( $row_item[0] ?? 'key' );
                    $value = $row_item[1] ?? '';
                    if ( $type === 'key' && preg_match( '/^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/i', $value ) ) {
                        $keys_to_verify[] = $value;
                    }
                }

                if ( ! empty( $keys_to_verify ) ) {
                    $batch_size = 30;
                    $chunks = array_chunk( $keys_to_verify, $batch_size );
                    $url = 'https://licenceflow-checker.app.tedisun.com/v1/check';
                    $api_key = 'sk_lf_7b58cde3e5e4fa6b51df2f6d2e8b6a382e71d3c05b8aef52968840c1d683a219';

                    foreach ( $chunks as $chunk ) {
                        $response = wp_remote_post( $url, array(
                            'headers' => array(
                                'Content-Type'          => 'application/json',
                                'X-LicenceFlow-Api-Key' => $api_key,
                            ),
                            'body'    => wp_json_encode( array( 'keys' => $chunk ) ),
                            'timeout' => 30,
                        ) );

                        if ( ! is_wp_error( $response ) ) {
                            $body_res = wp_remote_retrieve_body( $response );
                            $data = json_decode( $body_res, true );
                            if ( ! empty( $data['success'] ) && ! empty( $data['results'] ) ) {
                                foreach ( $data['results'] as $res ) {
                                    if ( ! empty( $res['key'] ) ) {
                                        $results_by_key[ strtoupper( $res['key'] ) ] = $res;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            foreach ( $rows_data as $row ) {

                $type         = sanitize_key( $row[0] ?? 'key' );
                $value        = $row[1] ?? '';
                $expiry       = sanitize_text_field( $row[2] ?? '' );
                $valid        = absint( $row[3] ?? 0 );
                $license_note = sanitize_textarea_field( $row[4] ?? '' );
                $notes        = sanitize_textarea_field( $row[5] ?? '' );

                if ( empty( $value ) || ! in_array( $type, $valid_types, true ) ) {
                    $errors++;
                    continue;
                }

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

                $license_status = 'available';
                $status_msg = '';
                if ( $verify_online && $type === 'key' && preg_match( '/^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/i', $value ) ) {
                    $up_key = strtoupper( $value );
                    if ( isset( $results_by_key[ $up_key ] ) ) {
                        $res = $results_by_key[ $up_key ];
                        if ( empty( $res['valid'] ) ) {
                            $license_status = 'inactive';
                            $status_msg = $res['message'] ?? 'Bloquée par Microsoft.';
                            $deactivated_count++;
                        }
                    }
                }

                $data = array(
                    'product_id'     => $import_product_id ?: 0,
                    'license_key'    => $serialized,
                    'license_type'   => $type,
                    'license_status' => $license_status,
                    'license_note'   => $license_note,
                    'admin_notes'    => $status_msg ? trim( $notes . "\n[Import] Clé détectée comme bloquée/invalide lors de l'import : " . $status_msg ) : $notes,
                    'valid'          => $valid,
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

            // Sync stock if applicable (same pattern as TXT import)
            if ( $imported > 0 && LicenceFlow_Settings::is_on( 'lflow_stock_sync' ) ) {
                LicenceFlow_Core::get_instance()->sync_product_stock( $import_product_id, 0 );
            }

            $msg = sprintf(
                /* translators: %1$d: imported, %2$d: skipped, %3$d: errors */
                __( 'Import CSV terminé : %1$d importée(s), %2$d ignorée(s), %3$d erreur(s).', 'licenceflow' ),
                $imported, $skipped, $errors
            );
            if ( $deactivated_count > 0 ) {
                $msg .= ' ' . sprintf( __( '⚠️ %d clé(s) Microsoft bloquée(s) détectée(s) et désactivée(s) automatiquement.', 'licenceflow' ), $deactivated_count );
            }

            $notice = array(
                'type' => $errors || $deactivated_count ? 'warning' : 'updated',
                'msg'  => $msg,
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

        <!-- ── Import TXT (recommended) ── -->
        <div class="lflow-card">
            <h2><?php esc_html_e( 'Importer des licences (TXT — recommandé)', 'licenceflow' ); ?></h2>
            <p class="description" style="margin-bottom:12px;">
                <?php esc_html_e( 'Méthode la plus simple : un fichier texte brut avec une licence par ligne. Pas de mise en forme, pas d\'en-tête. Tous les paramètres sont définis dans le formulaire ci-dessous et s\'appliquent à chaque ligne du fichier.', 'licenceflow' ); ?><br>
                <?php esc_html_e( 'Pour les types "Compte", "Lien" ou "Code", séparez les champs par | (ex. : identifiant|motdepasse).', 'licenceflow' ); ?>
            </p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'lflow_txt_import_licenses', 'lflow_txt_import_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Fichier .txt', 'licenceflow' ); ?></label></th>
                        <td><input type="file" name="import_txt" accept=".txt,text/plain"></td>
                    </tr>
                    <tr>
                        <th><label for="txt_product_id"><?php esc_html_e( 'Produit', 'licenceflow' ); ?></label></th>
                        <td>
                            <select id="txt_product_id" name="txt_product_id" class="lflow-product-select" required>
                                <option value="0"><?php esc_html_e( '— Sélectionner un produit —', 'licenceflow' ); ?></option>
                                <?php foreach ( $licensed_products as $pid => $pname ) : ?>
                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pname ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="txt_variation_id"><?php esc_html_e( 'Variation', 'licenceflow' ); ?></label></th>
                        <td>
                            <select id="txt_variation_id" name="txt_variation_id" disabled>
                                <option value="0"><?php esc_html_e( '— Aucune variation —', 'licenceflow' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Chargé automatiquement après sélection du produit.', 'licenceflow' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="txt_license_type"><?php esc_html_e( 'Type de licence', 'licenceflow' ); ?></label></th>
                        <td>
                            <select id="txt_license_type" name="txt_license_type">
                                <?php foreach ( lflow_license_types() as $slug => $label ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="txt_delivre_x_times"><?php esc_html_e( 'Livrable X fois', 'licenceflow' ); ?></label>
                            <button type="button" class="lflow-help-btn">?</button>
                            <span class="lflow-help-text"><?php esc_html_e( 'Nombre de fois que chaque licence de ce fichier peut être livrée à des clients différents. 1 = usage unique (standard). 5 = la même licence peut être commandée et livrée jusqu\'à 5 fois.', 'licenceflow' ); ?></span>
                        </th>
                        <td><input type="number" id="txt_delivre_x_times" name="txt_delivre_x_times" value="1" min="1" style="width:80px;"></td>
                    </tr>
                    <tr>
                        <th><label for="txt_license_status"><?php esc_html_e( 'Statut initial', 'licenceflow' ); ?></label></th>
                        <td>
                            <select id="txt_license_status" name="txt_license_status">
                                <?php foreach ( lflow_license_statuses() as $slug => $label ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, 'available' ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="txt_expiration_date"><?php esc_html_e( 'Date d\'expiration admin', 'licenceflow' ); ?></label></th>
                        <td>
                            <input type="date" id="txt_expiration_date" name="txt_expiration_date" placeholder="YYYY-MM-DD">
                            <p class="description"><?php esc_html_e( 'Optionnel. Date à laquelle le lot de licences expire pour vous (renouvellement fournisseur). Laissez vide si pas d\'expiration.', 'licenceflow' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="txt_valid"><?php esc_html_e( 'Validité client (jours)', 'licenceflow' ); ?></label></th>
                        <td>
                            <input type="number" id="txt_valid" name="txt_valid" value="0" min="0" style="width:80px;">
                            <p class="description"><?php esc_html_e( '0 = pas de limite. Sinon, le client verra "À utiliser avant le [date d\'achat + N jours]".', 'licenceflow' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="txt_license_note"><?php esc_html_e( 'Note client (défaut)', 'licenceflow' ); ?></label></th>
                        <td>
                            <textarea id="txt_license_note" name="txt_license_note" rows="2" style="width:300px;" placeholder="<?php esc_attr_e( 'Ex : Code Antivirus et VPN (réf: I37)', 'licenceflow' ); ?>"></textarea>
                            <p class="description"><?php esc_html_e( 'Visible par le client. Peut être surchargée ligne par ligne avec la syntaxe : CLE || note spécifique.', 'licenceflow' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="txt_admin_notes"><?php esc_html_e( 'Notes internes', 'licenceflow' ); ?></label></th>
                        <td>
                            <textarea id="txt_admin_notes" name="txt_admin_notes" rows="2" style="width:300px;" placeholder="<?php esc_attr_e( 'Ex. : Lot acheté le 22/03/2026 chez le fournisseur X', 'licenceflow' ); ?>"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="txt_verify_online"><?php esc_html_e( 'Vérifier en ligne', 'licenceflow' ); ?></label>
                            <button type="button" class="lflow-help-btn">?</button>
                            <span class="lflow-help-text"><?php esc_html_e( 'Interroge l\'API en arrière-plan pour valider les clés Microsoft 5x5. Les clés bloquées seront automatiquement importées avec le statut "Inactif" et annotées.', 'licenceflow' ); ?></span>
                        </th>
                        <td>
                            <input type="checkbox" id="txt_verify_online" name="txt_verify_online" value="1" checked>
                            <span class="description"><?php esc_html_e( 'Vérifier la validité des clés en ligne (Microsoft 5x5)', 'licenceflow' ); ?></span>
                        </td>
                    </tr>
                </table>

                <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Importer le fichier TXT', 'licenceflow' ); ?></button></p>
            </form>
        </div>

        <!-- ── Export ── -->
        <div class="lflow-card">
            <h2><?php esc_html_e( 'Exporter les licences (CSV)', 'licenceflow' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'lflow_export_licenses', 'lflow_export_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Produit', 'licenceflow' ); ?></label></th>
                        <td>
                            <select name="export_product_id" class="lflow-product-select">
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

                <p class="description"><?php esc_html_e( 'Colonnes : license_id, product_id, variation_id, license_type, license_status, license_value, expiration_date, valid, sold_date, order_id, owner_email, license_note, admin_notes.', 'licenceflow' ); ?></p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Télécharger le CSV', 'licenceflow' ); ?></button></p>
            </form>
        </div>

        <!-- ── Import CSV (advanced) ── -->
        <div class="lflow-card">
            <h2><?php esc_html_e( 'Importer des licences (CSV — avancé)', 'licenceflow' ); ?></h2>
            <p class="description" style="margin-bottom:12px;">
                <?php esc_html_e( 'Pour importer des licences avec des paramètres différents par ligne. Format sans en-tête.', 'licenceflow' ); ?>
            </p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'lflow_import_licenses', 'lflow_import_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Produit cible', 'licenceflow' ); ?></label></th>
                        <td>
                            <select name="import_product_id" class="lflow-product-select">
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
                    <tr>
                        <th>
                            <label for="csv_verify_online"><?php esc_html_e( 'Vérifier en ligne', 'licenceflow' ); ?></label>
                            <button type="button" class="lflow-help-btn">?</button>
                            <span class="lflow-help-text"><?php esc_html_e( 'Interroge l\'API en arrière-plan pour valider les clés Microsoft 5x5. Les clés bloquées seront automatiquement importées avec le statut "Inactif" et annotées.', 'licenceflow' ); ?></span>
                        </th>
                        <td>
                            <input type="checkbox" id="csv_verify_online" name="csv_verify_online" value="1" checked>
                            <span class="description"><?php esc_html_e( 'Vérifier la validité des clés en ligne (Microsoft 5x5)', 'licenceflow' ); ?></span>
                        </td>
                    </tr>
                </table>

                <p class="description">
                    <?php esc_html_e( 'Colonnes (sans en-tête) : license_type | license_value | expiration_date (Y-m-d) | valid (jours) | license_note | admin_notes.', 'licenceflow' ); ?><br>
                    <?php esc_html_e( 'Pour "account" : username|password. Pour "link" : url|label. Pour "code" : code|note.', 'licenceflow' ); ?>
                </p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Importer le CSV', 'licenceflow' ); ?></button></p>
            </form>
        </div>

    </div>

</div>
<?php
// Add JS to handle variation loading in the TXT import form
?>
<script>
(function($){
    $('#txt_product_id').on('change', function(){
        var pid = $(this).val();
        var $var = $('#txt_variation_id');
        $var.find('option:not(:first)').remove();
        if (!pid || pid === '0') { $var.prop('disabled', true); return; }
        $.post(lflow_admin.ajax_url, {
            action: 'lflow_get_variations', nonce: lflow_admin.nonce, product_id: pid
        }, function(r){
            if (r.success && r.data.variations && r.data.variations.length) {
                r.data.variations.forEach(function(v){ $var.append('<option value="'+v.id+'">'+v.label+'</option>'); });
                $var.prop('disabled', false);
            } else { $var.prop('disabled', true); }
        });
    });
}(jQuery));
</script>
