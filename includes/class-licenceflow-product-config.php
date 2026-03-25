<?php
/**
 * LicenceFlow — Product configuration layer
 *
 * Reads and writes the wp_lflow_licensed_products table.
 * One row per (product_id, variation_id) pair.
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_Product_Config {

    /** Default config values */
    private static array $defaults = array(
        'active'        => 0,
        'license_type'  => 'key',
        'delivery_qty'  => 1,
        'show_in'       => 'both',
        'default_valid' => 0,
    );

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Get config for a product (or variation).
     * Returns a row from wp_lflow_licensed_products, or defaults if none exists.
     *
     * @return array  Keys: config_id, product_id, variation_id, active, license_type, delivery_qty, show_in
     */
    public static function get_config( int $product_id, int $variation_id = 0 ): array {
        global $wpdb;

        $cache_key = 'lflow_cfg_' . $product_id . '_' . $variation_id;
        $cached    = wp_cache_get( $cache_key, 'licenceflow' );
        if ( $cached !== false ) {
            return $cached;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}lflow_licensed_products WHERE product_id = %d AND variation_id = %d",
                $product_id, $variation_id
            ),
            ARRAY_A
        );

        $result = $row ?: array_merge(
            self::$defaults,
            array(
                'config_id'    => null,
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
            )
        );

        wp_cache_set( $cache_key, $result, 'licenceflow', 300 );
        return $result;
    }

    /**
     * Get configs for all variations of a product.
     * Returns an array keyed by variation_id.
     *
     * @return array<int, array>
     */
    public static function get_variation_configs( int $product_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}lflow_licensed_products WHERE product_id = %d AND variation_id > 0",
                $product_id
            ),
            ARRAY_A
        );

        $result = array();
        foreach ( $rows as $row ) {
            $result[ (int) $row['variation_id'] ] = $row;
        }
        return $result;
    }

    /**
     * Get all active licensed product configs (with licensing enabled).
     *
     * @return array
     */
    public static function get_all_active(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}lflow_licensed_products WHERE active = 1 ORDER BY product_id ASC",
            ARRAY_A
        ) ?: array();
    }

    // ── Convenience accessors ─────────────────────────────────────────────────

    /**
     * Check if licensing is active for a product/variation.
     */
    public static function is_active( int $product_id, int $variation_id = 0 ): bool {
        $config = self::get_config( $product_id, $variation_id );
        return (bool) $config['active'];
    }

    /**
     * Get the license type template for a product/variation.
     *
     * @return string  key|account|link|code
     */
    public static function get_license_type( int $product_id, int $variation_id = 0 ): string {
        $config = self::get_config( $product_id, $variation_id );
        $type   = $config['license_type'] ?? 'key';
        $valid  = array_keys( lflow_license_types() );
        return in_array( $type, $valid, true ) ? $type : 'key';
    }

    /**
     * Get the delivery quantity for a product/variation.
     */
    public static function get_delivery_qty( int $product_id, int $variation_id = 0 ): int {
        $config = self::get_config( $product_id, $variation_id );
        return max( 1, (int) ( $config['delivery_qty'] ?? 1 ) );
    }

    /**
     * Get the default customer validity (days) for a product/variation.
     * Returns 0 if no default is set (unlimited).
     */
    public static function get_default_valid( int $product_id, int $variation_id = 0 ): int {
        $config = self::get_config( $product_id, $variation_id );
        return max( 0, (int) ( $config['default_valid'] ?? 0 ) );
    }

    /**
     * Get the display channel for a product/variation.
     *
     * @return string  email|website|both
     */
    public static function get_show_in( int $product_id, int $variation_id = 0 ): string {
        $config  = self::get_config( $product_id, $variation_id );
        $show_in = $config['show_in'] ?? 'both';
        return in_array( $show_in, array( 'email', 'website', 'both' ), true ) ? $show_in : 'both';
    }

    /**
     * Count available licenses for a product/variation.
     */
    public static function count_available( int $product_id, int $variation_id = 0 ): int {
        return LicenceFlow_License_DB::count_available( $product_id, $variation_id );
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Insert or update a product config row.
     *
     * @param array $data  Keys: active, license_type, delivery_qty, show_in
     */
    public static function save_config( int $product_id, int $variation_id, array $data ): bool {
        global $wpdb;

        // Sanitize and whitelist
        $row = array(
            'product_id'    => $product_id,
            'variation_id'  => $variation_id,
            'active'        => isset( $data['active'] ) ? (int) (bool) $data['active'] : 0,
            'license_type'  => in_array( $data['license_type'] ?? '', array_keys( lflow_license_types() ), true )
                               ? $data['license_type'] : 'key',
            'delivery_qty'  => max( 1, (int) ( $data['delivery_qty'] ?? 1 ) ),
            'show_in'       => in_array( $data['show_in'] ?? '', array( 'email', 'website', 'both' ), true )
                               ? $data['show_in'] : 'both',
            'default_valid' => max( 0, (int) ( $data['default_valid'] ?? 0 ) ),
        );

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT config_id FROM {$wpdb->prefix}lflow_licensed_products WHERE product_id = %d AND variation_id = %d",
                $product_id, $variation_id
            )
        );

        if ( $existing ) {
            $result = $wpdb->update(
                $wpdb->prefix . 'lflow_licensed_products',
                $row,
                array( 'config_id' => (int) $existing )
            );
        } else {
            $result = $wpdb->insert( $wpdb->prefix . 'lflow_licensed_products', $row );
        }

        // Invalidate cache for this product/variation
        wp_cache_delete( 'lflow_cfg_' . $product_id . '_' . $variation_id, 'licenceflow' );

        return $result !== false;
    }

    /**
     * Delete the config for a product (and all its variations).
     */
    public static function delete_product( int $product_id ): bool {
        global $wpdb;
        $result = $wpdb->delete( $wpdb->prefix . 'lflow_licensed_products', array( 'product_id' => $product_id ) );
        return $result !== false;
    }

    // ── WC Product helpers ────────────────────────────────────────────────────

    /**
     * Get all WC products that have at least one licensing config (active=1).
     * Returns an array of [ product_id => product_name ] for use in dropdowns.
     *
     * @return array<int, string>
     */
    public static function get_licensed_products_for_select(): array {
        global $wpdb;

        // Single JOIN query instead of N wc_get_product() calls
        $rows = $wpdb->get_results(
            "SELECT DISTINCT lp.product_id, p.post_title
             FROM {$wpdb->prefix}lflow_licensed_products lp
             INNER JOIN {$wpdb->posts} p ON p.ID = lp.product_id
             WHERE lp.active = 1
               AND p.post_status NOT IN ('trash', 'auto-draft')
             ORDER BY p.post_title ASC",
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return array();
        }

        $result = array();
        foreach ( $rows as $row ) {
            $result[ (int) $row['product_id'] ] = $row['post_title'];
        }
        return $result;
    }

    /**
     * Get the variations of a WC product as array of [ variation_id => variation_name ].
     * Returns empty array for simple products.
     *
     * @return array<int, string>
     */
    public static function get_variation_options( int $product_id ): array {
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return array();
        }

        $result = array();
        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( $variation ) {
                $result[ $variation_id ] = $variation->get_formatted_name();
            }
        }

        return $result;
    }
}
