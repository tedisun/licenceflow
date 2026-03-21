<?php
/**
 * LicenceFlow — Security helpers
 *
 * Nonces, sanitization, capability checks, and MCP API key verification.
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_Security {

    /** @var self|null */
    private static $instance = null;

    private function __construct() {}

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Nonces ───────────────────────────────────────────────────────────────

    /**
     * Create a WordPress nonce.
     */
    public function create_nonce( string $action ): string {
        return wp_create_nonce( 'lflow_' . $action );
    }

    /**
     * Verify a WordPress nonce. Returns true/false.
     */
    public function verify_nonce( string $nonce, string $action ): bool {
        return (bool) wp_verify_nonce( $nonce, 'lflow_' . $action );
    }

    /**
     * Verify nonce for an AJAX request. Dies on failure.
     */
    public function check_ajax_nonce( string $action ): void {
        check_ajax_referer( 'lflow_' . $action, 'nonce' );
    }

    // ── Capability ───────────────────────────────────────────────────────────

    /**
     * Die if the current user cannot manage LicenceFlow.
     */
    public function require_capability(): void {
        if ( ! lflow_current_user_can() ) {
            wp_die(
                esc_html__( 'Vous n\'avez pas les droits nécessaires pour effectuer cette action.', 'licenceflow' ),
                esc_html__( 'Accès refusé', 'licenceflow' ),
                array( 'response' => 403 )
            );
        }
    }

    // ── Sanitization ─────────────────────────────────────────────────────────

    /**
     * Sanitize a raw form value for a license field based on its type.
     *
     * Returns:
     *  - 'key'     : sanitized string
     *  - 'account' : array { username, password }
     *  - 'link'    : array { url, label }
     *  - 'code'    : array { code, note }
     *
     * @param mixed  $data Raw POST data (string or array)
     * @param string $type License type: key|account|link|code
     * @return string|array
     */
    public function sanitize_license_field( $data, string $type ) {
        switch ( $type ) {
            case 'account':
                return array(
                    'username' => sanitize_text_field( $data['username'] ?? '' ),
                    'password' => sanitize_text_field( $data['password'] ?? '' ),
                );

            case 'link':
                return array(
                    'url'   => esc_url_raw( $data['url'] ?? '' ),
                    'label' => sanitize_text_field( $data['label'] ?? '' ),
                );

            case 'code':
                return array(
                    'code' => sanitize_text_field( $data['code'] ?? '' ),
                    'note' => sanitize_textarea_field( $data['note'] ?? '' ),
                );

            case 'key':
            default:
                return sanitize_textarea_field( is_array( $data ) ? ( $data['key'] ?? '' ) : (string) $data );
        }
    }

    /**
     * Sanitize an integer value with a default fallback.
     */
    public function sanitize_int( $val, int $default = 0 ): int {
        $val = filter_var( $val, FILTER_VALIDATE_INT );
        return ( false === $val ) ? $default : (int) $val;
    }

    /**
     * Validate and sanitize a Y-m-d date string. Returns empty string on failure.
     */
    public function sanitize_date( string $val ): string {
        $val = sanitize_text_field( $val );
        if ( $val === '' ) {
            return '';
        }
        $d = DateTime::createFromFormat( 'Y-m-d', $val );
        return ( $d && $d->format( 'Y-m-d' ) === $val ) ? $val : '';
    }

    // ── MCP API Key ───────────────────────────────────────────────────────────

    /**
     * Verify the MCP API key from a REST request header.
     *
     * Uses hash_equals() for timing-safe comparison.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function verify_mcp_api_key( WP_REST_Request $request ): bool {
        $provided = $request->get_header( 'X-LicenceFlow-API-Key' );
        if ( empty( $provided ) ) {
            return false;
        }
        $stored = get_option( 'lflow_api_key', '' );
        if ( empty( $stored ) ) {
            return false;
        }
        return hash_equals( $stored, $provided );
    }
}
