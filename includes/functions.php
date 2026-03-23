<?php
/**
 * LicenceFlow — Global helper functions
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

// ─── Encryption ──────────────────────────────────────────────────────────────

/**
 * Encrypt or decrypt a string using AES-256-CBC.
 *
 * @param string $action     'encrypt' or 'decrypt'
 * @param string $string     The string to process
 * @param string $secret_key Encryption key
 * @param string $secret_iv  Encryption IV
 *
 * @return string|false
 */
function lflow_encrypt_decrypt( string $action, string $string, string $secret_key, string $secret_iv ) {
    if ( $secret_key === '' && $secret_iv === '' ) {
        return $string;
    }

    if ( ! extension_loaded( 'openssl' ) ) {
        return $string;
    }

    $method = 'AES-256-CBC';
    $key    = hash( 'sha256', $secret_key );
    $iv     = substr( hash( 'sha256', $secret_iv ), 0, 16 );

    if ( $action === 'encrypt' ) {
        return base64_encode( openssl_encrypt( $string, $method, $key, 0, $iv ) );
    }

    return openssl_decrypt( base64_decode( $string ), $method, $key, 0, $iv );
}

/**
 * Convenience wrapper: encrypt a license value.
 *
 * @param string $value
 * @return string
 */
function lflow_encrypt( string $value ): string {
    $key = get_option( 'lflow_enc_key', LFLOW_DEFAULT_ENC_KEY );
    $iv  = get_option( 'lflow_enc_iv',  LFLOW_DEFAULT_ENC_IV );
    return lflow_encrypt_decrypt( 'encrypt', $value, $key, $iv );
}

/**
 * Convenience wrapper: decrypt a license value.
 *
 * @param string $value
 * @return string
 */
function lflow_decrypt( string $value ): string {
    $key = get_option( 'lflow_enc_key', LFLOW_DEFAULT_ENC_KEY );
    $iv  = get_option( 'lflow_enc_iv',  LFLOW_DEFAULT_ENC_IV );
    return lflow_encrypt_decrypt( 'decrypt', $value, $key, $iv );
}

// ─── Encryption key persistence ──────────────────────────────────────────────

/**
 * Save encryption keys to a protected PHP file in wp-uploads.
 *
 * @param string $key
 * @param string $iv
 */
function lflow_save_encryption_key( string $key, string $iv ) {
    $upload = wp_upload_dir();
    $dir    = $upload['basedir'] . '/lflow_files/';

    wp_mkdir_p( $dir );

    // Protect directory
    if ( ! file_exists( $dir . '.htaccess' ) ) {
        file_put_contents( $dir . '.htaccess', 'deny from all' );
        file_put_contents( $dir . 'index.php', '<?php // Silence is golden.' );
    }

    file_put_contents(
        $dir . 'encryption_key.php',
        '<?php define("LFLOW_ENC_KEY", ' . var_export( $key, true ) . '); define("LFLOW_ENC_IV", ' . var_export( $iv, true ) . ');'
    );

    update_option( 'lflow_enc_key', $key );
    update_option( 'lflow_enc_iv',  $iv );
}

// ─── License type helpers ─────────────────────────────────────────────────────

/**
 * Returns the 4 supported license types.
 *
 * @return array<string, string>  slug => label
 */
function lflow_license_types(): array {
    return array(
        'key'     => __( 'Clé de licence', 'licenceflow' ),
        'account' => __( 'Compte (identifiants)', 'licenceflow' ),
        'link'    => __( "Lien d'activation", 'licenceflow' ),
        'code'    => __( "Code d'accès", 'licenceflow' ),
    );
}

/**
 * Returns the human-readable label for a license type.
 *
 * @param string $type
 * @return string
 */
function lflow_license_type_label( string $type ): string {
    $types = lflow_license_types();
    return $types[ $type ] ?? $types['key'];
}

/**
 * Parse a stored license_key value.
 * - For type 'key' : returns the raw string
 * - For other types: JSON-decodes to array
 *
 * @param string $raw_value  Decrypted license_key column value
 * @param string $type       license_type column value
 * @return string|array
 */
function lflow_parse_license_value( string $raw_value, string $type ) {
    if ( $type === 'key' || $type === '' ) {
        return $raw_value;
    }
    $decoded = json_decode( $raw_value, true );
    return is_array( $decoded ) ? $decoded : $raw_value;
}

/**
 * Serialize a license value for storage.
 *
 * @param array|string $data
 * @param string       $type
 * @return string
 */
function lflow_serialize_license_value( $data, string $type ): string {
    if ( $type === 'key' ) {
        return (string) $data;
    }
    return wp_json_encode( $data );
}

// ─── Date helpers ─────────────────────────────────────────────────────────────

/**
 * Format a date for display. Returns 'N/A' for empty/zero dates.
 *
 * @param string $date
 * @param bool   $is_expiration  If true, '0000-00-00' returns 'Does not expire'
 * @return string
 */
function lflow_format_date( string $date, bool $is_expiration = false ): string {
    if ( $date === '0000-00-00' || $date === '' || $date === null ) {
        return $is_expiration
            ? __( "N'expire pas", 'licenceflow' )
            : __( 'N/A', 'licenceflow' );
    }
    $ts = strtotime( $date );
    if ( ! $ts ) {
        return __( 'N/A', 'licenceflow' );
    }
    return date_i18n( get_option( 'date_format', 'j F Y' ), $ts );
}

/**
 * Calculate the customer-visible "valid until" date:
 * sold_date + valid_days
 *
 * @param string $sold_date  MySQL date string (Y-m-d)
 * @param int    $valid_days
 * @return string  Formatted date, or empty string if not applicable
 */
function lflow_customer_expiry_date( string $sold_date, int $valid_days ): string {
    if ( $valid_days <= 0 || empty( $sold_date ) || $sold_date === '0000-00-00' ) {
        return '';
    }
    $ts = strtotime( $sold_date . ' + ' . $valid_days . ' days' );
    return $ts ? date_i18n( get_option( 'date_format', 'j F Y' ), $ts ) : '';
}

// ─── Status helpers ───────────────────────────────────────────────────────────

/**
 * All supported license statuses.
 *
 * @return array<string, string>  slug => label
 */
function lflow_license_statuses(): array {
    return array(
        'available'    => __( 'Disponible', 'licenceflow' ),
        'sold'         => __( 'Vendu', 'licenceflow' ),
        'active'       => __( 'Actif', 'licenceflow' ),
        'inactive'     => __( 'Inactif', 'licenceflow' ),
        'expired'      => __( 'Expiré', 'licenceflow' ),
        'returned'     => __( 'Retourné', 'licenceflow' ),
        'redeemed'     => __( 'Échangé', 'licenceflow' ),
    );
}

// ─── Permission helpers ───────────────────────────────────────────────────────

/**
 * Check if the current user can manage LicenceFlow.
 *
 * @return bool
 */
function lflow_current_user_can(): bool {
    return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
}

// ─── String utilities ─────────────────────────────────────────────────────────

/**
 * Convert <br> tags to newlines.
 *
 * @param string $str
 * @return string
 */
function lflow_br_to_newline( string $str ): string {
    return str_replace( array( '<br>', '<br />', '<br/>' ), "\n", $str );
}

/**
 * Strip <br> tags from a string.
 *
 * @param string $str
 * @return string
 */
function lflow_strip_br( string $str ): string {
    return str_replace( array( '<br>', '<br />', '<br/>' ), '', $str );
}

/**
 * Detect if a string is a URL.
 *
 * @param string $str
 * @return bool
 */
function lflow_is_url( string $str ): bool {
    return (bool) filter_var( trim( $str ), FILTER_VALIDATE_URL );
}

// ─── Customer-facing license card renderer ────────────────────────────────────

/**
 * Render a type-aware license card for the customer.
 *
 * @param array  $license  Decrypted, parsed license row (no expiration_date).
 *                         Must include: license_type, parsed_value, customer_expiry (optional).
 * @param string $context  'email' or 'website'
 */
function lflow_render_license_card( array $license, string $context = 'website' ): void {
    $type    = $license['license_type'] ?? 'key';
    $value   = $license['parsed_value'] ?? '';
    $expiry  = $license['customer_expiry'] ?? '';

    $product_name   = '';
    $variation_name = '';
    if ( ! empty( $license['product_id'] ) ) {
        $product = wc_get_product( (int) $license['product_id'] );
        if ( $product ) {
            $product_name = $product->get_name();
        }
    }
    if ( ! empty( $license['variation_id'] ) && (int) $license['variation_id'] > 0 ) {
        $variation = wc_get_product( (int) $license['variation_id'] );
        if ( $variation && $variation->is_type( 'variation' ) ) {
            $variation_name = wc_get_formatted_variation( $variation, true, false );
        }
    }

    $is_email = $context === 'email';

    // Card wrapper styles
    $card_style  = $is_email
        ? 'border:1px solid #e0e0e0; border-radius:6px; padding:16px; margin-bottom:16px; background:#f9f9f9;'
        : '';
    $card_class  = 'lflow-license-card lflow-license-card--' . esc_attr( $type );

    echo '<div class="' . $card_class . '" style="' . esc_attr( $card_style ) . '">';

    // Product name + variation name
    if ( $product_name ) {
        echo '<p style="margin:0 0 ' . ( $variation_name ? '2px' : '10px' ) . '; font-weight:600; color:#1d2327;">' . esc_html( $product_name ) . '</p>';
    }
    if ( $variation_name ) {
        echo '<p style="margin:0 0 10px; font-size:.85em; color:#646970;">' . esc_html( $variation_name ) . '</p>';
    }

    // Content by type
    switch ( $type ) {

        case 'account':
            $username = is_array( $value ) ? ( $value['username'] ?? '' ) : '';
            $password = is_array( $value ) ? ( $value['password'] ?? '' ) : '';
            echo '<table style="border-collapse:collapse; font-size:.9em;">';
            echo '<tr><td style="padding:4px 12px 4px 0; color:#646970;">' . esc_html__( 'Identifiant', 'licenceflow' ) . '</td>';
            echo '<td><code style="background:#f0f0f1; padding:2px 6px; border-radius:3px;">' . esc_html( $username ) . '</code></td></tr>';
            echo '<tr><td style="padding:4px 12px 4px 0; color:#646970;">' . esc_html__( 'Mot de passe', 'licenceflow' ) . '</td>';
            if ( $is_email ) {
                echo '<td><code style="background:#f0f0f1; padding:2px 6px; border-radius:3px;">' . esc_html( $password ) . '</code></td></tr>';
            } else {
                // On website: show/hide toggle
                $unique_id = 'lflow-pass-' . absint( $license['license_id'] ?? rand() );
                echo '<td>';
                echo '<span id="' . esc_attr( $unique_id ) . '-val" style="display:none;"><code style="background:#f0f0f1; padding:2px 6px; border-radius:3px;">' . esc_html( $password ) . '</code></span>';
                echo '<span id="' . esc_attr( $unique_id ) . '-mask">••••••••</span>';
                echo ' <button type="button" onclick="document.getElementById(\'' . esc_js( $unique_id ) . '-val\').style.display=\'inline\';document.getElementById(\'' . esc_js( $unique_id ) . '-mask\').style.display=\'none\';this.style.display=\'none\';" style="font-size:.8em; padding:1px 6px; cursor:pointer;">' . esc_html__( 'Afficher', 'licenceflow' ) . '</button>';
                echo '</td></tr>';
            }
            echo '</table>';
            break;

        case 'link':
            $url   = is_array( $value ) ? ( $value['url'] ?? '' ) : '';
            $label = is_array( $value ) ? ( $value['label'] ?? __( 'Cliquez pour activer', 'licenceflow' ) ) : __( 'Cliquez pour activer', 'licenceflow' );
            if ( $url ) {
                echo '<a href="' . esc_url( $url ) . '" style="display:inline-block; padding:8px 16px; background:#2271b1; color:#fff; text-decoration:none; border-radius:4px; font-weight:600;">';
                echo esc_html( $label );
                echo '</a>';
                echo '<br><small style="color:#646970; font-size:.8em;">' . esc_html( $url ) . '</small>';
            }
            break;

        case 'code':
            $code = is_array( $value ) ? ( $value['code'] ?? '' ) : '';
            $note = is_array( $value ) ? ( $value['note'] ?? '' ) : '';
            echo '<code style="font-size:1.1em; background:#f0f0f1; padding:6px 10px; border-radius:4px; letter-spacing:.05em;">';
            echo esc_html( $code );
            echo '</code>';
            if ( $note ) {
                echo '<p style="margin:8px 0 0; font-size:.85em; color:#646970;">' . esc_html( $note ) . '</p>';
            }
            break;

        case 'key':
        default:
            $key_str = is_string( $value ) ? $value : '';
            echo '<code style="font-size:1.05em; background:#f0f0f1; padding:6px 10px; border-radius:4px; word-break:break-all;">';
            echo esc_html( $key_str );
            echo '</code>';
            if ( ! $is_email ) {
                // Copy button
                $unique_id = 'lflow-key-' . absint( $license['license_id'] ?? rand() );
                echo ' <button type="button" onclick="navigator.clipboard.writeText(\'' . esc_js( $key_str ) . '\').then(function(){this.textContent=\'' . esc_js( __( 'Copié !', 'licenceflow' ) ) . '\';}.bind(this));" style="font-size:.8em; padding:2px 8px; cursor:pointer; margin-left:4px;">';
                echo esc_html__( 'Copier', 'licenceflow' );
                echo '</button>';
            }
            break;
    }

    // "Utilisable X fois" badge for multi-delivery licenses
    $times = isset( $license['times'] ) ? (int) $license['times'] : 1;
    if ( $times > 1 ) {
        echo '<p style="margin:10px 0 0; font-size:.85em; color:#646970;">';
        printf(
            /* translators: %d: number of times the license can be used */
            esc_html__( 'Utilisable %d fois', 'licenceflow' ),
            $times
        );
        echo '</p>';
    }

    // Customer expiry
    if ( $expiry ) {
        echo '<p style="margin:' . ( $times > 1 ? '4px' : '10px' ) . ' 0 0; font-size:.85em; color:#646970;">';
        printf(
            esc_html__( 'Valide jusqu\'au : %s', 'licenceflow' ),
            '<strong>' . esc_html( $expiry ) . '</strong>'
        );
        echo '</p>';
    }

    echo '</div>';
}
