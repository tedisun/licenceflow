<?php
/**
 * LicenceFlow — MCP REST API v1
 *
 * Exposes CRUD endpoints for licenses under /wp-json/licenceflow/mcp/v1/
 * Authentication: X-LicenceFlow-API-Key header (hash_equals comparison).
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_API_V1 {

    const NAMESPACE = 'licenceflow/mcp/v1';

    public function register_routes(): void {
        // GET /licenses, POST /licenses
        register_rest_route( self::NAMESPACE, '/licenses', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'list_licenses' ),
                'permission_callback' => array( $this, 'check_api_key' ),
                'args'                => $this->list_args(),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_license' ),
                'permission_callback' => array( $this, 'check_api_key' ),
                'args'                => $this->create_args(),
            ),
        ) );

        // GET /licenses/{id}, PUT /licenses/{id}, DELETE /licenses/{id}
        register_rest_route( self::NAMESPACE, '/licenses/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_license' ),
                'permission_callback' => array( $this, 'check_api_key' ),
                'args'                => array(
                    'id'          => array( 'required' => true, 'validate_callback' => 'is_numeric' ),
                    'include_key' => array( 'default' => false, 'type' => 'boolean' ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_license' ),
                'permission_callback' => array( $this, 'check_api_key' ),
                'args'                => array_merge(
                    array( 'id' => array( 'required' => true, 'validate_callback' => 'is_numeric' ) ),
                    $this->create_args( false )
                ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_license' ),
                'permission_callback' => array( $this, 'check_api_key' ),
                'args'                => array(
                    'id' => array( 'required' => true, 'validate_callback' => 'is_numeric' ),
                ),
            ),
        ) );

        // POST /licenses/{id}/deliver
        register_rest_route( self::NAMESPACE, '/licenses/(?P<id>\d+)/deliver', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'deliver_license' ),
            'permission_callback' => array( $this, 'check_api_key' ),
            'args'                => array(
                'id'       => array( 'required' => true, 'validate_callback' => 'is_numeric' ),
                'order_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
            ),
        ) );

        // GET /stats
        register_rest_route( self::NAMESPACE, '/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_stats' ),
            'permission_callback' => array( $this, 'check_api_key' ),
        ) );
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function check_api_key( WP_REST_Request $request ): bool {
        return LicenceFlow_Security::get_instance()->verify_mcp_api_key( $request );
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function list_licenses( WP_REST_Request $request ): WP_REST_Response {
        $per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
        $page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );

        $args = array(
            'status'      => sanitize_key( $request->get_param( 'status' ) ?: '' ),
            'product_id'  => absint( $request->get_param( 'product_id' ) ?: 0 ),
            'type'        => sanitize_key( $request->get_param( 'type' ) ?: '' ),
            'search'      => sanitize_text_field( $request->get_param( 'search' ) ?: '' ),
            'page'        => $page,
            'per_page'    => $per_page,
            'orderby'     => sanitize_key( $request->get_param( 'orderby' ) ?: 'license_id' ),
            'order'       => strtoupper( sanitize_key( $request->get_param( 'order' ) ?: 'DESC' ) ),
        );

        $result   = LicenceFlow_License_DB::get_list( $args );
        $include_key = (bool) $request->get_param( 'include_key' );

        $licenses = array_map( function ( $l ) use ( $include_key ) {
            return $this->format_license( $l, $include_key );
        }, $result['items'] );

        return new WP_REST_Response( array(
            'licenses'    => $licenses,
            'total'       => $result['total'],
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $result['total'] / $per_page ),
        ), 200 );
    }

    // ── Get single ────────────────────────────────────────────────────────────

    public function get_license( WP_REST_Request $request ): WP_REST_Response {
        $id      = absint( $request->get_param( 'id' ) );
        $license = LicenceFlow_License_DB::get( $id );

        if ( ! $license ) {
            return new WP_REST_Response( array( 'code' => 'not_found', 'message' => 'License not found.' ), 404 );
        }

        $include_key = (bool) $request->get_param( 'include_key' );
        return new WP_REST_Response( $this->format_license( $license, $include_key ), 200 );
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create_license( WP_REST_Request $request ): WP_REST_Response {
        $type  = sanitize_key( $request->get_param( 'license_type' ) ?: 'key' );
        $raw   = $request->get_param( 'license_key' );
        $clean = LicenceFlow_Security::get_instance()->sanitize_license_field( $raw, $type );
        $serialized = lflow_serialize_license_value( $clean, $type );

        $data = array(
            'product_id'      => absint( $request->get_param( 'product_id' ) ?: 0 ),
            'variation_id'    => absint( $request->get_param( 'variation_id' ) ?: 0 ),
            'license_key'     => $serialized,
            'license_type'    => $type,
            'license_status'  => sanitize_key( $request->get_param( 'license_status' ) ?: 'available' ),
            'valid'           => absint( $request->get_param( 'valid' ) ?: 0 ),
            'delivre_x_times' => max( 1, absint( $request->get_param( 'delivre_x_times' ) ?: 1 ) ),
            'admin_notes'     => sanitize_textarea_field( $request->get_param( 'admin_notes' ) ?: '' ),
        );

        $expiry = sanitize_text_field( $request->get_param( 'expiration_date' ) ?: '' );
        if ( $expiry && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expiry ) ) {
            $data['expiration_date'] = $expiry;
        }

        $id = LicenceFlow_License_DB::insert( $data );
        if ( ! $id ) {
            return new WP_REST_Response( array( 'code' => 'insert_failed', 'message' => 'Failed to create license.' ), 500 );
        }

        $license = LicenceFlow_License_DB::get( $id );
        return new WP_REST_Response( $this->format_license( $license, false ), 201 );
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update_license( WP_REST_Request $request ): WP_REST_Response {
        $id      = absint( $request->get_param( 'id' ) );
        $license = LicenceFlow_License_DB::get( $id );
        if ( ! $license ) {
            return new WP_REST_Response( array( 'code' => 'not_found', 'message' => 'License not found.' ), 404 );
        }

        $data = array();

        if ( null !== $request->get_param( 'product_id' ) ) {
            $data['product_id'] = absint( $request->get_param( 'product_id' ) );
        }
        if ( null !== $request->get_param( 'variation_id' ) ) {
            $data['variation_id'] = absint( $request->get_param( 'variation_id' ) );
        }
        if ( null !== $request->get_param( 'license_status' ) ) {
            $data['license_status'] = sanitize_key( $request->get_param( 'license_status' ) );
        }
        if ( null !== $request->get_param( 'valid' ) ) {
            $data['valid'] = absint( $request->get_param( 'valid' ) );
        }
        if ( null !== $request->get_param( 'admin_notes' ) ) {
            $data['admin_notes'] = sanitize_textarea_field( $request->get_param( 'admin_notes' ) );
        }
        if ( null !== $request->get_param( 'expiration_date' ) ) {
            $expiry = sanitize_text_field( $request->get_param( 'expiration_date' ) );
            $data['expiration_date'] = ( $expiry && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expiry ) ) ? $expiry : null;
        }
        if ( null !== $request->get_param( 'license_key' ) ) {
            $type  = sanitize_key( $request->get_param( 'license_type' ) ?: ( $license['license_type'] ?? 'key' ) );
            $clean = LicenceFlow_Security::get_instance()->sanitize_license_field( $request->get_param( 'license_key' ), $type );
            $data['license_key']  = lflow_serialize_license_value( $clean, $type );
            $data['license_type'] = $type;
        }

        if ( empty( $data ) ) {
            return new WP_REST_Response( array( 'code' => 'no_data', 'message' => 'No fields to update.' ), 400 );
        }

        $ok = LicenceFlow_License_DB::update( $id, $data );
        if ( ! $ok ) {
            return new WP_REST_Response( array( 'code' => 'update_failed', 'message' => 'Failed to update license.' ), 500 );
        }

        return new WP_REST_Response( $this->format_license( LicenceFlow_License_DB::get( $id ), false ), 200 );
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete_license( WP_REST_Request $request ): WP_REST_Response {
        $id = absint( $request->get_param( 'id' ) );
        if ( ! LicenceFlow_License_DB::get( $id ) ) {
            return new WP_REST_Response( array( 'code' => 'not_found', 'message' => 'License not found.' ), 404 );
        }

        $ok = LicenceFlow_License_DB::delete( $id );
        if ( ! $ok ) {
            return new WP_REST_Response( array( 'code' => 'delete_failed', 'message' => 'Failed to delete license.' ), 500 );
        }

        return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
    }

    // ── Deliver ───────────────────────────────────────────────────────────────

    public function deliver_license( WP_REST_Request $request ): WP_REST_Response {
        $license_id = absint( $request->get_param( 'id' ) );
        $order_id   = absint( $request->get_param( 'order_id' ) );

        $license = LicenceFlow_License_DB::get( $license_id );
        if ( ! $license ) {
            return new WP_REST_Response( array( 'code' => 'not_found', 'message' => 'License not found.' ), 404 );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_REST_Response( array( 'code' => 'order_not_found', 'message' => 'Order not found.' ), 404 );
        }

        // Assign the license to the order
        LicenceFlow_License_DB::update( $license_id, array(
            'license_status'            => 'sold',
            'sold_date'                 => current_time( 'Y-m-d' ),
            'owner_email_address'       => $order->get_billing_email(),
            'owner_first_name'          => $order->get_billing_first_name(),
            'owner_last_name'           => $order->get_billing_last_name(),
            'order_id'                  => $order_id,
        ) );

        // Append to order meta
        $existing_ids   = (array) $order->get_meta( '_lflow_licenses' );
        $existing_ids[] = $license_id;
        $order->update_meta_data( '_lflow_licenses', array_unique( $existing_ids ) );
        $order->save();

        return new WP_REST_Response( array(
            'delivered'  => true,
            'license_id' => $license_id,
            'order_id'   => $order_id,
        ), 200 );
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function get_stats( WP_REST_Request $request ): WP_REST_Response {
        $days_before = (int) LicenceFlow_Settings::get( 'lflow_alert_days_before', 7 );

        return new WP_REST_Response( array(
            'by_status'        => LicenceFlow_License_DB::count_by_status(),
            'by_type'          => LicenceFlow_License_DB::count_by_type(),
            'by_product'       => LicenceFlow_License_DB::count_by_product( 10 ),
            'low_stock'        => LicenceFlow_License_DB::get_low_stock_products( 5 ),
            'expiring_soon'    => LicenceFlow_License_DB::get_expiring_soon( $days_before ),
            'recent_deliveries' => LicenceFlow_License_DB::get_recent_deliveries( 10 ),
        ), 200 );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Format a license row for API output.
     * Never exposes expiration_date to the public response unless $include_key is true.
     */
    private function format_license( array $license, bool $include_key ): array {
        $out = array(
            'license_id'                => (int) $license['license_id'],
            'product_id'                => (int) $license['product_id'],
            'variation_id'              => (int) $license['variation_id'],
            'license_type'              => $license['license_type'] ?? 'key',
            'license_status'            => $license['license_status'] ?? 'available',
            'owner_email_address'       => $license['owner_email_address'] ?? null,
            'owner_first_name'          => $license['owner_first_name'] ?? null,
            'owner_last_name'           => $license['owner_last_name'] ?? null,
            'sold_date'                 => $license['sold_date'] ?? null,
            'creation_date'             => $license['creation_date'] ?? null,
            'expiration_date'           => $license['expiration_date'] ?? null,
            'valid'                     => (int) ( $license['valid'] ?? 0 ),
            'order_id'                  => $license['order_id'] ? (int) $license['order_id'] : null,
            'delivre_x_times'           => (int) ( $license['delivre_x_times'] ?? 1 ),
            'remaining_delivre_x_times' => (int) ( $license['remaining_delivre_x_times'] ?? 1 ),
            'admin_notes'               => $license['admin_notes'] ?? null,
        );

        if ( $include_key ) {
            $out['license_key'] = $license['license_key'] ?? '';
            $out['parsed_value'] = lflow_parse_license_value( $license['license_key'] ?? '', $out['license_type'] );
        }

        return $out;
    }

    // ── Args schemas ─────────────────────────────────────────────────────────

    private function list_args(): array {
        return array(
            'status'     => array( 'type' => 'string', 'default' => '' ),
            'product_id' => array( 'type' => 'integer', 'default' => 0 ),
            'type'       => array( 'type' => 'string', 'default' => '' ),
            'search'     => array( 'type' => 'string', 'default' => '' ),
            'page'       => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
            'per_page'   => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
            'orderby'    => array( 'type' => 'string', 'default' => 'license_id' ),
            'order'      => array( 'type' => 'string', 'default' => 'DESC', 'enum' => array( 'ASC', 'DESC' ) ),
            'include_key' => array( 'type' => 'boolean', 'default' => false ),
        );
    }

    private function create_args( bool $required = true ): array {
        return array(
            'product_id'      => array( 'type' => 'integer', 'required' => $required, 'minimum' => 0 ),
            'variation_id'    => array( 'type' => 'integer', 'default' => 0 ),
            'license_type'    => array( 'type' => 'string', 'default' => 'key', 'enum' => array( 'key', 'account', 'link', 'code' ) ),
            'license_key'     => array( 'required' => $required ),
            'license_status'  => array( 'type' => 'string', 'default' => 'available' ),
            'expiration_date' => array( 'type' => 'string', 'default' => '' ),
            'valid'           => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0 ),
            'delivre_x_times' => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
            'admin_notes'     => array( 'type' => 'string', 'default' => '' ),
        );
    }
}
