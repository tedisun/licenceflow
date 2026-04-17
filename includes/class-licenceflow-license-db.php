<?php
/**
 * LicenceFlow — License data layer
 *
 * All database reads/writes for the wp_lflow_licenses and wp_lflow_license_meta tables.
 * No HTML. No hooks. All queries use prepared statements.
 *
 * @package LicenceFlow
 * @author  Tedisun SARL
 */

defined( 'ABSPATH' ) || exit;

class LicenceFlow_License_DB {

    // ── Single record ─────────────────────────────────────────────────────────

    /**
     * Fetch a single license row by ID.
     * license_key is automatically decrypted.
     *
     * @return array|null
     */
    public static function get( int $license_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}lflow_licenses WHERE license_id = %d",
                $license_id
            ),
            ARRAY_A
        );
        if ( ! $row ) {
            return null;
        }
        $row['license_key'] = lflow_decrypt( $row['license_key'] );
        return $row;
    }

    // ── List with filters ─────────────────────────────────────────────────────

    /**
     * Get a paginated, filtered list of licenses.
     *
     * Supported $args:
     *   status      string   Filter by license_status
     *   product_id  int      Filter by product_id
     *   variation_id int     Filter by variation_id
     *   type        string   Filter by license_type
     *   order_id    int      Filter by order_id
     *   search      string   Search owner name/email
     *   page        int      Page number (1-based), default 1
     *   per_page    int      Rows per page, default 15
     *   orderby     string   Column to sort by, default 'license_id'
     *   order       string   ASC|DESC, default DESC
     *
     * @return array { items: array[], total: int }
     */
    public static function get_list( array $args = [] ): array {
        global $wpdb;

        $defaults = array(
            'status'      => '',
            'product_id'  => 0,
            'variation_id' => -1,
            'type'        => '',
            'order_id'    => 0,
            'search'      => '',
            'page'        => 1,
            'per_page'    => 15,
            'orderby'     => 'license_id',
            'order'       => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'license_status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['product_id'] ) ) {
            $where[]  = 'product_id = %d';
            $params[] = (int) $args['product_id'];
        }
        if ( $args['variation_id'] >= 0 ) {
            $where[]  = 'variation_id = %d';
            $params[] = (int) $args['variation_id'];
        }
        if ( ! empty( $args['type'] ) ) {
            $where[]  = 'license_type = %s';
            $params[] = $args['type'];
        }
        if ( ! empty( $args['order_id'] ) ) {
            $where[]  = 'order_id = %d';
            $params[] = (int) $args['order_id'];
        }
        if ( ! empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            if ( ctype_digit( (string) $args['search'] ) ) {
                $where[]  = '(owner_first_name LIKE %s OR owner_last_name LIKE %s OR owner_email_address LIKE %s OR order_id = %d)';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = (int) $args['search'];
            } else {
                $where[]  = '(owner_first_name LIKE %s OR owner_last_name LIKE %s OR owner_email_address LIKE %s)';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        $where_sql = implode( ' AND ', $where );

        // Whitelist sortable columns
        $allowed_orderby = array( 'license_id', 'license_status', 'license_type', 'sold_date', 'expiration_date', 'creation_date' );
        $orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'license_id';
        $order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $per_page = max( 1, min( 200, (int) $args['per_page'] ) );
        $offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

        // Total count
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}lflow_licenses WHERE $where_sql";
        $total     = (int) ( empty( $params )
            ? $wpdb->get_var( $count_sql )
            : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) )
        );

        // Rows
        $rows_sql = "SELECT * FROM {$wpdb->prefix}lflow_licenses WHERE $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $row_params = array_merge( $params, array( $per_page, $offset ) );
        $rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $row_params ), ARRAY_A );

        // Decrypt keys
        foreach ( $rows as &$row ) {
            $row['license_key'] = lflow_decrypt( $row['license_key'] );
        }
        unset( $row );

        return array(
            'items' => $rows ?: array(),
            'total' => $total,
        );
    }

    // ── Fetch available for delivery ──────────────────────────────────────────

    /**
     * Fetch N available licenses for a product (for order delivery).
     * Uses FIFO (ASC) or LIFO (DESC) by license_id.
     *
     * @return array List of license rows (decrypted)
     */
    public static function fetch_available( int $product_id, int $variation_id, int $qty, bool $fifo = true ): array {
        global $wpdb;

        if ( $qty <= 0 ) return array();

        $order = $fifo ? 'ASC' : 'DESC';

        // LIMIT $qty rows: worst case all licenses have remaining=1, so we need at most $qty rows.
        // A license with remaining=5 satisfies 5 slots — in practice we need far fewer rows.
        if ( $variation_id > 0 ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}lflow_licenses
                     WHERE product_id = %d AND variation_id = %d
                       AND license_status = 'available'
                       AND remaining_delivre_x_times > 0
                     ORDER BY license_id $order
                     LIMIT %d",
                    $product_id, $variation_id, $qty
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}lflow_licenses
                     WHERE product_id = %d
                       AND license_status = 'available'
                       AND remaining_delivre_x_times > 0
                     ORDER BY license_id $order
                     LIMIT %d",
                    $product_id, $qty
                ),
                ARRAY_A
            );
        }

        if ( empty( $rows ) ) return array();

        foreach ( $rows as &$row ) {
            $row['license_key'] = lflow_decrypt( $row['license_key'] );
        }
        unset( $row );

        // Build result: each license contributes up to remaining_delivre_x_times slots.
        // If there are enough distinct licenses, each appears once.
        // If not, the same license is repeated to fill the requested qty.
        $result          = array();
        $remaining_needed = $qty;

        foreach ( $rows as $row ) {
            if ( $remaining_needed <= 0 ) break;
            $can_contribute  = min( (int) $row['remaining_delivre_x_times'], $remaining_needed );
            for ( $i = 0; $i < $can_contribute; $i++ ) {
                $result[] = $row;
            }
            $remaining_needed -= $can_contribute;
        }

        return $result;
    }

    // ── Fetch available — Best Fit ────────────────────────────────────────────

    /**
     * Fetch N delivery slots using a best-fit strategy.
     *
     * Step 1 — single key: find the license whose remaining_delivre_x_times is
     *   the SMALLEST value >= $qty (exact or nearest-over). One key covers the
     *   whole order, minimising the number of distinct keys sent to the customer.
     *
     * Step 2 — fallback: if no single key can cover $qty, fetch enough keys
     *   ordered by remaining_delivre_x_times DESC (biggest first) so the fewest
     *   possible keys are combined to fill the order.
     *
     * Return format is identical to fetch_available() — license rows repeated
     * per delivery slot, ready for the core delivery engine.
     *
     * @param int $product_id
     * @param int $variation_id  0 = ignore variation
     * @param int $qty           Total delivery slots needed
     * @return array
     */
    public static function fetch_best_fit( int $product_id, int $variation_id, int $qty ): array {
        global $wpdb;

        if ( $qty <= 0 ) return array();

        // ── Step 1: single key that covers $qty (smallest sufficient) ──────────

        if ( $variation_id > 0 ) {
            $best = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}lflow_licenses
                     WHERE product_id = %d AND variation_id = %d
                       AND license_status = 'available'
                       AND remaining_delivre_x_times >= %d
                     ORDER BY remaining_delivre_x_times ASC
                     LIMIT 1",
                    $product_id, $variation_id, $qty
                ),
                ARRAY_A
            );
        } else {
            $best = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}lflow_licenses
                     WHERE product_id = %d
                       AND license_status = 'available'
                       AND remaining_delivre_x_times >= %d
                     ORDER BY remaining_delivre_x_times ASC
                     LIMIT 1",
                    $product_id, $qty
                ),
                ARRAY_A
            );
        }

        if ( $best ) {
            $best['license_key'] = lflow_decrypt( $best['license_key'] );
            // Fill all $qty slots with this single key
            return array_fill( 0, $qty, $best );
        }

        // ── Step 2: no single key covers — biggest keys first to minimise count ─

        if ( $variation_id > 0 ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}lflow_licenses
                     WHERE product_id = %d AND variation_id = %d
                       AND license_status = 'available'
                       AND remaining_delivre_x_times > 0
                     ORDER BY remaining_delivre_x_times DESC
                     LIMIT %d",
                    $product_id, $variation_id, $qty
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}lflow_licenses
                     WHERE product_id = %d
                       AND license_status = 'available'
                       AND remaining_delivre_x_times > 0
                     ORDER BY remaining_delivre_x_times DESC
                     LIMIT %d",
                    $product_id, $qty
                ),
                ARRAY_A
            );
        }

        if ( empty( $rows ) ) return array();

        foreach ( $rows as &$row ) {
            $row['license_key'] = lflow_decrypt( $row['license_key'] );
        }
        unset( $row );

        // Same slot-filling logic as fetch_available()
        $result           = array();
        $remaining_needed = $qty;
        foreach ( $rows as $row ) {
            if ( $remaining_needed <= 0 ) break;
            $can_contribute   = min( (int) $row['remaining_delivre_x_times'], $remaining_needed );
            for ( $i = 0; $i < $can_contribute; $i++ ) {
                $result[] = $row;
            }
            $remaining_needed -= $can_contribute;
        }
        return $result;
    }

    // ── Insert ────────────────────────────────────────────────────────────────

    /**
     * Insert a new license.
     * 'license_key' in $data should be the raw (plain) value — it will be encrypted here.
     *
     * @return int|false New license_id or false on error
     */
    public static function insert( array $data ) {
        global $wpdb;

        // Encrypt the key
        if ( isset( $data['license_key'] ) ) {
            $data['license_key'] = lflow_encrypt( $data['license_key'] );
        }

        // Set creation_date if not provided
        if ( empty( $data['creation_date'] ) ) {
            $data['creation_date'] = current_time( 'Y-m-d' );
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'lflow_licenses',
            $data
        );

        return $result ? $wpdb->insert_id : false;
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /**
     * Update an existing license.
     * If 'license_key' is in $data, it will be re-encrypted.
     *
     * @return bool
     */
    public static function update( int $license_id, array $data ): bool {
        global $wpdb;

        if ( array_key_exists( 'license_key', $data ) ) {
            $data['license_key'] = lflow_encrypt( $data['license_key'] );
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'lflow_licenses',
            $data,
            array( 'license_id' => $license_id )
        );

        return $result !== false;
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    /**
     * Hard-delete a license and its meta.
     */
    public static function delete( int $license_id ): bool {
        global $wpdb;

        // Delete meta first
        $wpdb->delete( $wpdb->prefix . 'lflow_license_meta', array( 'license_id' => $license_id ) );

        $result = $wpdb->delete( $wpdb->prefix . 'lflow_licenses', array( 'license_id' => $license_id ) );
        return (bool) $result;
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    /**
     * Update the status of multiple licenses at once.
     *
     * @param int[]  $license_ids
     * @param string $status
     */
    public static function bulk_update_status( array $license_ids, string $status ): bool {
        global $wpdb;

        if ( empty( $license_ids ) ) {
            return false;
        }

        $valid_statuses = array_keys( lflow_license_statuses() );
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return false;
        }

        $ids_placeholder = implode( ',', array_map( 'intval', $license_ids ) );

        if ( $status === 'available' ) {
            // When restoring to 'available', also reset remaining_delivre_x_times to delivre_x_times
            // so the license is actually deliverable again (fetch_available requires remaining > 0).
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}lflow_licenses
                     SET license_status = %s, remaining_delivre_x_times = delivre_x_times
                     WHERE license_id IN ($ids_placeholder)",
                    $status
                )
            );
        } else {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}lflow_licenses SET license_status = %s WHERE license_id IN ($ids_placeholder)",
                    $status
                )
            );
        }

        return $result !== false;
    }

    /**
     * Delete multiple licenses at once.
     *
     * @param int[] $license_ids
     */
    public static function bulk_delete( array $license_ids ): bool {
        global $wpdb;

        if ( empty( $license_ids ) ) {
            return false;
        }

        $ids = implode( ',', array_map( 'intval', $license_ids ) );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}lflow_license_meta WHERE license_id IN ($ids)" );
        $result = $wpdb->query( "DELETE FROM {$wpdb->prefix}lflow_licenses WHERE license_id IN ($ids)" );

        return $result !== false;
    }

    // ── Order-based query ─────────────────────────────────────────────────────

    /**
     * Get all licenses delivered for a WooCommerce order.
     */
    public static function get_by_order( int $order_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}lflow_licenses WHERE order_id = %d ORDER BY license_id ASC",
                $order_id
            ),
            ARRAY_A
        );

        foreach ( $rows as &$row ) {
            $row['license_key'] = lflow_decrypt( $row['license_key'] );
        }
        unset( $row );

        return $rows ?: array();
    }

    // ── Statistics ────────────────────────────────────────────────────────────

    /**
     * Count licenses grouped by status.
     *
     * @return array<string, int>
     */
    public static function count_by_status(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT license_status, COUNT(*) AS cnt FROM {$wpdb->prefix}lflow_licenses GROUP BY license_status",
            ARRAY_A
        );

        $result = array_fill_keys( array_keys( lflow_license_statuses() ), 0 );
        foreach ( $rows as $row ) {
            $result[ $row['license_status'] ] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Count licenses grouped by type.
     *
     * @return array<string, int>
     */
    public static function count_by_type(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT license_type, COUNT(*) AS cnt FROM {$wpdb->prefix}lflow_licenses GROUP BY license_type",
            ARRAY_A
        );

        $result = array_fill_keys( array_keys( lflow_license_types() ), 0 );
        foreach ( $rows as $row ) {
            $result[ $row['license_type'] ] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Count available and sold licenses per product (top N products).
     *
     * @return array  Each item: { product_id, available, sold, total }
     */
    public static function count_by_product( int $limit = 10 ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    product_id,
                    COALESCE(SUM(CASE WHEN license_status = 'available' AND remaining_delivre_x_times > 0 THEN remaining_delivre_x_times ELSE 0 END), 0) AS available,
                    SUM(CASE WHEN license_status = 'sold' THEN 1 ELSE 0 END) AS sold,
                    COUNT(*) AS total
                 FROM {$wpdb->prefix}lflow_licenses
                 GROUP BY product_id
                 ORDER BY total DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $rows ?: array();
    }

    /**
     * Get licenses whose admin expiration_date is within $days days.
     */
    public static function get_expiring_soon( int $days ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT license_id, product_id, variation_id, license_type, license_status, expiration_date, owner_email_address
                 FROM {$wpdb->prefix}lflow_licenses
                 WHERE expiration_date IS NOT NULL
                   AND expiration_date != '0000-00-00'
                   AND expiration_date >= CURDATE()
                   AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)
                   AND license_status NOT IN ('expired', 'returned')
                 ORDER BY expiration_date ASC",
                $days
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Get products with fewer than $threshold available licenses.
     *
     * @return array  Each item: { product_id, variation_id, available }
     */
    public static function get_low_stock_products( int $threshold = 5 ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product_id, variation_id, COALESCE(SUM(remaining_delivre_x_times), 0) AS available
                 FROM {$wpdb->prefix}lflow_licenses
                 WHERE license_status = 'available' AND remaining_delivre_x_times > 0
                 GROUP BY product_id, variation_id
                 HAVING available < %d
                 ORDER BY available ASC",
                $threshold
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Count products with fewer than $threshold available licenses (for admin bar badge).
     * Returns just the count — much lighter than get_low_stock_products().
     */
    public static function count_low_stock_products( int $threshold = 5 ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM (
                     SELECT product_id
                     FROM {$wpdb->prefix}lflow_licenses
                     WHERE license_status = 'available' AND remaining_delivre_x_times > 0
                     GROUP BY product_id, variation_id
                     HAVING COALESCE(SUM(remaining_delivre_x_times), 0) < %d
                 ) AS low_stock",
                $threshold
            )
        );
    }

    /**
     * Get the most recently delivered (sold) licenses.
     */
    public static function get_recent_deliveries( int $limit = 10 ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT license_id, product_id, variation_id, license_type, owner_email_address, sold_date, order_id
                 FROM {$wpdb->prefix}lflow_licenses
                 WHERE license_status IN ('sold', 'active', 'redeemed')
                   AND sold_date IS NOT NULL
                 ORDER BY sold_date DESC, license_id DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Count available licenses for a specific product/variation.
     */
    public static function count_available( int $product_id, int $variation_id = 0 ): int {
        global $wpdb;

        // Use SUM(remaining_delivre_x_times) — consistent with sync_product_stock.
        // A license with delivre_x_times=5 remaining=3 contributes 3 slots, not 1 row.
        if ( $variation_id > 0 ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(remaining_delivre_x_times), 0) FROM {$wpdb->prefix}lflow_licenses WHERE product_id = %d AND variation_id = %d AND license_status = 'available' AND remaining_delivre_x_times > 0",
                    $product_id, $variation_id
                )
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(remaining_delivre_x_times), 0) FROM {$wpdb->prefix}lflow_licenses WHERE product_id = %d AND license_status = 'available' AND remaining_delivre_x_times > 0",
                $product_id
            )
        );
    }

    /**
     * Count total licenses (all statuses) for a specific product/variation.
     */
    public static function count_total( int $product_id, int $variation_id = 0 ): int {
        global $wpdb;

        if ( $variation_id > 0 ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}lflow_licenses WHERE product_id = %d AND variation_id = %d",
                    $product_id, $variation_id
                )
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}lflow_licenses WHERE product_id = %d",
                $product_id
            )
        );
    }

    // ── Encryption key migration ──────────────────────────────────────────────

    /**
     * Re-encrypt all license_key values in the database.
     *
     * Decrypts each row with ($old_key, $old_iv) and re-encrypts with ($new_key, $new_iv).
     * Rows that fail to decrypt with the old key are left unchanged and counted as errors.
     *
     * @return array { migrated: int, skipped: int, errors: int }
     */
    public static function migrate_encryption_keys(
        string $old_key, string $old_iv,
        string $new_key, string $new_iv
    ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT license_id, license_key FROM {$wpdb->prefix}lflow_licenses",
            ARRAY_A
        );

        $migrated = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ( $rows as $row ) {
            $raw = $row['license_key'];

            // Decrypt with old key
            $plain = lflow_encrypt_decrypt( 'decrypt', $raw, $old_key, $old_iv );

            if ( $plain === false || $plain === '' ) {
                // Could not decrypt — either already using new key or corrupted.
                // Try decrypting with new key to detect "already migrated" rows.
                $check = lflow_encrypt_decrypt( 'decrypt', $raw, $new_key, $new_iv );
                if ( $check !== false && $check !== '' ) {
                    $skipped++; // Already encrypted with new key
                } else {
                    $errors++; // Truly unreadable
                }
                continue;
            }

            // Re-encrypt with new key
            $re_encrypted = lflow_encrypt_decrypt( 'encrypt', $plain, $new_key, $new_iv );

            $wpdb->update(
                $wpdb->prefix . 'lflow_licenses',
                array( 'license_key' => $re_encrypted ),
                array( 'license_id'  => (int) $row['license_id'] )
            );

            $migrated++;
        }

        return array(
            'migrated' => $migrated,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'total'    => count( $rows ),
        );
    }

    // ── License meta ──────────────────────────────────────────────────────────

    /**
     * Get a meta value for a license.
     *
     * @return mixed|null
     */
    public static function get_meta( int $license_id, string $meta_key ) {
        global $wpdb;

        $val = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}lflow_license_meta WHERE license_id = %d AND meta_key = %s",
                $license_id, $meta_key
            )
        );

        return maybe_unserialize( $val );
    }

    /**
     * Get all meta for a license as key => value array.
     */
    public static function get_all_meta( int $license_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->prefix}lflow_license_meta WHERE license_id = %d",
                $license_id
            ),
            ARRAY_A
        );

        $result = array();
        foreach ( $rows as $row ) {
            $result[ $row['meta_key'] ] = maybe_unserialize( $row['meta_value'] );
        }
        return $result;
    }

    /**
     * Insert or update a meta value.
     */
    public static function update_meta( int $license_id, string $meta_key, $value ): bool {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_id FROM {$wpdb->prefix}lflow_license_meta WHERE license_id = %d AND meta_key = %s",
                $license_id, $meta_key
            )
        );

        $serialized = maybe_serialize( $value );

        if ( $exists ) {
            $result = $wpdb->update(
                $wpdb->prefix . 'lflow_license_meta',
                array( 'meta_value' => $serialized ),
                array( 'license_id' => $license_id, 'meta_key' => $meta_key )
            );
        } else {
            $result = $wpdb->insert(
                $wpdb->prefix . 'lflow_license_meta',
                array(
                    'license_id' => $license_id,
                    'meta_key'   => $meta_key,
                    'meta_value' => $serialized,
                )
            );
        }

        return $result !== false;
    }

    /**
     * Delete a specific meta entry.
     */
    public static function delete_meta( int $license_id, string $meta_key ): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . 'lflow_license_meta',
            array( 'license_id' => $license_id, 'meta_key' => $meta_key )
        );

        return (bool) $result;
    }
}
