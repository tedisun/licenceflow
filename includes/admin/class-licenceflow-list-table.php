<?php
/**
 * LicenceFlow — License list table
 *
 * Extends WP_List_Table to display paginated, filterable, sortable licenses.
 *
 * @package LicenceFlow
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class LicenceFlow_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'licence',
            'plural'   => 'licences',
            'ajax'     => false,
        ) );
    }

    // ── Columns ───────────────────────────────────────────────────────────────

    public function get_columns(): array {
        return array(
            'cb'              => '<input type="checkbox" id="lflow-select-all">',
            'license_id'      => __( 'ID', 'licenceflow' ),
            'product'         => __( 'Produit', 'licenceflow' ),
            'license_key_col' => __( 'Clé / Valeur', 'licenceflow' ),
            'stock_col'       => __( 'Dispo.', 'licenceflow' ),
            'license_type'    => __( 'Type', 'licenceflow' ),
            'license_status'  => __( 'Statut', 'licenceflow' ),
            'owner'           => __( 'Client', 'licenceflow' ),
            'sold_date'       => __( 'Date de vente', 'licenceflow' ),
            'expiration_date' => __( 'Expiration (admin)', 'licenceflow' ),
            'actions'         => __( 'Actions', 'licenceflow' ),
        );
    }

    public function get_sortable_columns(): array {
        return array(
            'license_id'      => array( 'license_id', true ),
            'license_status'  => array( 'license_status', false ),
            'license_type'    => array( 'license_type', false ),
            'sold_date'       => array( 'sold_date', false ),
            'expiration_date' => array( 'expiration_date', false ),
        );
    }

    protected function get_bulk_actions(): array {
        // Bulk actions are handled by our custom toolbar in page-licenses.php
        return array();
    }

    // ── Data ──────────────────────────────────────────────────────────────────

    public function prepare_items(): void {
        $per_page = (int) LicenceFlow_Settings::get( 'lflow_nb_rows_by_page', 15 );
        $page     = $this->get_pagenum();

        $args = array(
            'status'       => sanitize_key( $_GET['license_status'] ?? '' ),
            'product_id'   => absint( $_GET['product_id'] ?? 0 ),
            'variation_id' => absint( $_GET['variation_id'] ?? 0 ) > 0 ? absint( $_GET['variation_id'] ) : -1,
            'type'         => sanitize_key( $_GET['license_type'] ?? '' ),
            'search'       => sanitize_text_field( $_GET['s'] ?? '' ),
            'page'         => $page,
            'per_page'     => $per_page,
            'orderby'      => sanitize_key( $_GET['orderby'] ?? 'license_id' ),
            'order'        => strtoupper( sanitize_key( $_GET['order'] ?? 'DESC' ) ),
        );

        $result = LicenceFlow_License_DB::get_list( $args );

        $this->items = $result['items'];

        $this->set_pagination_args( array(
            'total_items' => $result['total'],
            'per_page'    => $per_page,
            'total_pages' => ceil( $result['total'] / $per_page ),
        ) );

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }

    // ── Column renderers ──────────────────────────────────────────────────────

    protected function column_cb( $item ): string {
        return '<input type="checkbox" name="license_ids[]" value="' . absint( $item['license_id'] ) . '">';
    }

    protected function column_license_id( $item ): string {
        return '<strong>#' . absint( $item['license_id'] ) . '</strong>';
    }

    protected function column_product( $item ): string {
        $product = wc_get_product( $item['product_id'] );
        $name    = $product ? esc_html( $product->get_name() ) : '#' . absint( $item['product_id'] );
        if ( $item['variation_id'] > 0 ) {
            $variation = wc_get_product( $item['variation_id'] );
            if ( $variation && $variation->is_type( 'variation' ) ) {
                $var_label = wc_get_formatted_variation( $variation, true, false );
                if ( $var_label ) {
                    $name .= '<br><small style="color:#646970">' . esc_html( $var_label ) . '</small>';
                }
            }
        }
        return $name;
    }

    protected function column_license_key_col( $item ): string {
        $type = $item['license_type'] ?? 'key';
        $raw  = $item['license_key'] ?? '';

        // For non-key types the stored value is JSON; extract the main field
        if ( $type !== 'key' ) {
            $parsed = lflow_parse_license_value( $raw, $type );
            if ( $type === 'account' ) {
                $display = ( $parsed['username'] ?? '' ) . ' / ' . ( $parsed['password'] ?? '' );
            } elseif ( $type === 'link' ) {
                $display = $parsed['url'] ?? $raw;
            } elseif ( $type === 'code' ) {
                $display = $parsed['code'] ?? $raw;
            } else {
                $display = $raw;
            }
        } else {
            $display = $raw;
        }

        if ( $display === '' ) {
            return '<span style="color:#646970">—</span>';
        }

        $short = mb_strlen( $display ) > 40 ? mb_substr( $display, 0, 38 ) . '…' : $display;
        $esc   = esc_attr( $display );

        return sprintf(
            '<code style="font-size:.8em; word-break:break-all;" title="%s">%s</code> ' .
            '<button type="button" class="button button-small lflow-copy-key" data-key="%s" title="%s" style="padding:0 5px;">⧉</button>',
            $esc,
            esc_html( $short ),
            $esc,
            esc_attr__( 'Copier', 'licenceflow' )
        );
    }

    protected function column_stock_col( $item ): string {
        $remaining = (int) ( $item['remaining_delivre_x_times'] ?? 1 );
        $total     = (int) ( $item['delivre_x_times'] ?? 1 );

        if ( $remaining <= 0 ) {
            $color = 'color:#d63638;';
        } elseif ( $remaining < $total ) {
            $color = 'color:#dba617;';
        } else {
            $color = 'color:#00a32a;';
        }

        return '<span style="font-weight:600;' . $color . '" title="' . esc_attr__( 'Livraisons restantes / Livraisons max pour cette licence', 'licenceflow' ) . '">'
            . $remaining . '/' . $total
            . '</span>';
    }

    protected function column_license_type( $item ): string {
        $icons = array(
            'key'     => '🔑',
            'account' => '👤',
            'link'    => '🔗',
            'code'    => '🎟️',
        );
        $type  = $item['license_type'] ?? 'key';
        $icon  = $icons[ $type ] ?? '🔑';
        $label = lflow_license_type_label( $type );
        return '<span class="lflow-type-badge">' . $icon . ' ' . esc_html( $label ) . '</span>';
    }

    protected function column_license_status( $item ): string {
        $status  = $item['license_status'] ?? 'available';
        $statuses = lflow_license_statuses();
        $label   = $statuses[ $status ] ?? $status;
        return '<span class="lflow-status-badge lflow-status-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
    }

    protected function column_owner( $item ): string {
        if ( empty( $item['owner_email_address'] ) ) {
            return '<span style="color:#646970">—</span>';
        }
        $name = trim( $item['owner_first_name'] . ' ' . $item['owner_last_name'] );
        $out  = '';
        if ( $name ) {
            $out .= esc_html( $name ) . '<br>';
        }
        $out .= '<small>' . esc_html( $item['owner_email_address'] ) . '</small>';

        if ( ! empty( $item['order_id'] ) ) {
            $order_url = get_edit_post_link( $item['order_id'] );
            if ( ! $order_url ) {
                // HPOS
                $order_url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . absint( $item['order_id'] ) );
            }
            $out .= '<br><small><a href="' . esc_url( $order_url ) . '">#' . absint( $item['order_id'] ) . '</a></small>';
        }
        return $out;
    }

    protected function column_sold_date( $item ): string {
        return lflow_format_date( $item['sold_date'] ?? '' );
    }

    protected function column_expiration_date( $item ): string {
        $date = $item['expiration_date'] ?? '';
        if ( empty( $date ) || $date === '0000-00-00' ) {
            return '<span style="color:#646970">—</span>';
        }

        $days_left = (int) floor( ( strtotime( $date ) - time() ) / DAY_IN_SECONDS );
        $color     = '';
        if ( $days_left < 0 ) {
            $color = 'color:#d63638;';
        } elseif ( $days_left <= 7 ) {
            $color = 'color:#dba617;';
        }

        return '<span style="' . $color . '">' . esc_html( lflow_format_date( $date, true ) ) . '</span>';
    }

    protected function column_actions( $item ): string {
        $edit_url   = LicenceFlow_Admin::edit_license_url( (int) $item['license_id'] );
        $delete_id  = absint( $item['license_id'] );

        return sprintf(
            '<a href="%s" class="button button-small">%s</a> ' .
            '<a href="#" class="button button-small lflow-delete-license" data-id="%d">%s</a>',
            esc_url( $edit_url ),
            esc_html__( 'Modifier', 'licenceflow' ),
            $delete_id,
            esc_html__( 'Supprimer', 'licenceflow' )
        );
    }

    public function column_default( $item, $column_name ): string {
        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '—';
    }

    // ── Status filter tabs ────────────────────────────────────────────────────

    protected function get_views(): array {
        $current  = sanitize_key( $_GET['license_status'] ?? '' );
        $base_url = admin_url( 'admin.php?page=lflow-licenses' );
        $total    = LicenceFlow_License_DB::count_by_status();
        $all      = array_sum( $total );

        $views = array();

        $views['all'] = sprintf(
            '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
            esc_url( $base_url ),
            $current === '' ? ' class="current"' : '',
            esc_html__( 'Toutes', 'licenceflow' ),
            $all
        );

        foreach ( lflow_license_statuses() as $slug => $label ) {
            $count = $total[ $slug ] ?? 0;
            $views[ $slug ] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( 'license_status', $slug, $base_url ) ),
                $current === $slug ? ' class="current"' : '',
                esc_html( $label ),
                $count
            );
        }

        return $views;
    }

    // ── Extra filters — handled by custom filter bar in page-licenses.php ────

    protected function extra_tablenav( $which ): void {
        // Intentionally empty: filters are rendered above the table in page-licenses.php
    }

    public function no_items(): void {
        esc_html_e( 'Aucune licence trouvée.', 'licenceflow' );
    }
}
