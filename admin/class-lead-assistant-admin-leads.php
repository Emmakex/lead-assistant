<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_Admin_Leads {
    private const PAGE_SLUG = 'lead-assistant-leads';
    private const PER_PAGE  = 20;

    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_lead_assistant_export_leads', array( __CLASS__, 'export_csv' ) );
    }

    public static function register_menu(): void {
        add_submenu_page(
            'lead-assistant',
            __( 'Leads', 'lead-assistant' ),
            __( 'Leads', 'lead-assistant' ),
            'manage_options',
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'lead-assistant' ) );
        }

        $lead_id = isset( $_GET['lead_id'] ) ? absint( wp_unslash( $_GET['lead_id'] ) ) : 0;

        if ( $lead_id > 0 ) {
            self::render_detail( $lead_id );
            return;
        }

        self::render_list();
    }

    public static function export_csv(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to export leads.', 'lead-assistant' ) );
        }

        check_admin_referer( 'lead_assistant_export_leads' );

        $filters = self::filters_from_request();
        $filename = 'lead-assistant-leads-' . gmdate( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'X-Content-Type-Options: nosniff' );

        $output = fopen( 'php://output', 'w' );
        if ( $output === false ) {
            wp_die( esc_html__( 'Could not create the export.', 'lead-assistant' ) );
        }

        // UTF-8 BOM improves compatibility with spreadsheet applications.
        fwrite( $output, "\xEF\xBB\xBF" );
        fputcsv(
            $output,
            array(
                __( 'Status', 'lead-assistant' ),
                __( 'Name', 'lead-assistant' ),
                __( 'Email', 'lead-assistant' ),
                __( 'Phone', 'lead-assistant' ),
                __( 'Created', 'lead-assistant' ),
                __( 'Last activity', 'lead-assistant' ),
                __( 'Privacy consent', 'lead-assistant' ),
            )
        );

        $offset = 0;
        $batch  = 500;

        do {
            $rows = self::query_rows( $filters, $batch, $offset );

            foreach ( $rows as $row ) {
                fputcsv(
                    $output,
                    array(
                        self::csv_cell( (string) ( $row['status'] ?? '' ) ),
                        self::csv_cell( (string) ( $row['name'] ?? '' ) ),
                        self::csv_cell( (string) ( $row['email'] ?? '' ) ),
                        self::csv_cell( (string) ( $row['phone'] ?? '' ) ),
                        self::csv_cell( self::format_utc_date( (string) ( $row['created_at'] ?? '' ) ) ),
                        self::csv_cell( self::format_utc_date( (string) ( $row['updated_at'] ?? '' ) ) ),
                        self::csv_cell(
                            (int) ( $row['consent_privacy'] ?? 0 ) === 1
                                ? self::format_utc_date( (string) ( $row['consent_at'] ?? '' ) )
                                : __( 'Not captured', 'lead-assistant' )
                        ),
                    )
                );
            }

            $count   = count( $rows );
            $offset += $count;
        } while ( $count === $batch );

        fclose( $output );
        exit;
    }

    private static function render_list(): void {
        $filters     = self::filters_from_request();
        $page        = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
        $offset      = ( $page - 1 ) * self::PER_PAGE;
        $total       = self::query_count( $filters );
        $rows        = self::query_rows( $filters, self::PER_PAGE, $offset );
        $total_pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );

        if ( $page > $total_pages ) {
            $page   = $total_pages;
            $offset = ( $page - 1 ) * self::PER_PAGE;
            $rows   = self::query_rows( $filters, self::PER_PAGE, $offset );
        }

        $pagination_args = array_merge(
            array(
                'page'  => self::PAGE_SLUG,
                'paged' => 999999999,
            ),
            self::filter_query_args( $filters )
        );

        $pagination_base = str_replace(
            '999999999',
            '%#%',
            add_query_arg( $pagination_args, admin_url( 'admin.php' ) )
        );

        $export_url = wp_nonce_url(
            add_query_arg(
                array_merge(
                    array( 'action' => 'lead_assistant_export_leads' ),
                    self::filter_query_args( $filters )
                ),
                admin_url( 'admin-post.php' )
            ),
            'lead_assistant_export_leads'
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Leads', 'lead-assistant' ); ?></h1>
            <p><?php echo esc_html__( 'Qualified conversations and captured contacts appear here. Unqualified chat sessions are not shown in this view.', 'lead-assistant' ); ?></p>

            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; flex-wrap:wrap; gap:10px; align-items:end; margin:16px 0;">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">

                <label>
                    <span style="display:block; font-weight:600;"><?php echo esc_html__( 'Search', 'lead-assistant' ); ?></span>
                    <input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php echo esc_attr__( 'Name, email or phone', 'lead-assistant' ); ?>">
                </label>

                <label>
                    <span style="display:block; font-weight:600;"><?php echo esc_html__( 'Status', 'lead-assistant' ); ?></span>
                    <select name="status">
                        <option value=""><?php echo esc_html__( 'All', 'lead-assistant' ); ?></option>
                        <option value="qualified" <?php selected( $filters['status'], 'qualified' ); ?>><?php echo esc_html__( 'Qualified', 'lead-assistant' ); ?></option>
                        <option value="captured" <?php selected( $filters['status'], 'captured' ); ?>><?php echo esc_html__( 'Captured', 'lead-assistant' ); ?></option>
                    </select>
                </label>

                <label>
                    <span style="display:block; font-weight:600;"><?php echo esc_html__( 'From', 'lead-assistant' ); ?></span>
                    <input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
                </label>

                <label>
                    <span style="display:block; font-weight:600;"><?php echo esc_html__( 'To', 'lead-assistant' ); ?></span>
                    <input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
                </label>

                <?php submit_button( __( 'Filter', 'lead-assistant' ), 'secondary', '', false ); ?>
                <a class="button" href="<?php echo esc_url( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html__( 'Clear', 'lead-assistant' ); ?></a>
                <a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>"><?php echo esc_html__( 'Export filtered CSV', 'lead-assistant' ); ?></a>
            </form>

            <p>
                <?php
                printf(
                    esc_html( _n( '%d lead found.', '%d leads found.', $total, 'lead-assistant' ) ),
                    (int) $total
                );
                ?>
            </p>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__( 'Status', 'lead-assistant' ); ?></th>
                        <th scope="col"><?php echo esc_html__( 'Name', 'lead-assistant' ); ?></th>
                        <th scope="col"><?php echo esc_html__( 'Email', 'lead-assistant' ); ?></th>
                        <th scope="col"><?php echo esc_html__( 'Phone', 'lead-assistant' ); ?></th>
                        <th scope="col"><?php echo esc_html__( 'Last activity', 'lead-assistant' ); ?></th>
                        <th scope="col"><?php echo esc_html__( 'Privacy consent', 'lead-assistant' ); ?></th>
                        <th scope="col"><?php echo esc_html__( 'Action', 'lead-assistant' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr>
                            <td colspan="7"><?php echo esc_html__( 'No leads match these filters.', 'lead-assistant' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <?php
                            $detail_url = add_query_arg(
                                array(
                                    'page'    => self::PAGE_SLUG,
                                    'lead_id' => (int) $row['id'],
                                ),
                                admin_url( 'admin.php' )
                            );
                            ?>
                            <tr>
                                <td><code><?php echo esc_html( (string) $row['status'] ); ?></code></td>
                                <td><?php echo esc_html( self::display_value( $row['name'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( self::display_value( $row['email'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( self::display_value( $row['phone'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( self::format_utc_date( (string) $row['updated_at'] ) ); ?></td>
                                <td>
                                    <?php
                                    echo esc_html(
                                        (int) ( $row['consent_privacy'] ?? 0 ) === 1
                                            ? self::format_utc_date( (string) ( $row['consent_at'] ?? '' ) )
                                            : __( 'Not captured', 'lead-assistant' )
                                    );
                                    ?>
                                </td>
                                <td><a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html__( 'View', 'lead-assistant' ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(
                            paginate_links(
                                array(
                                    'base'      => $pagination_base,
                                    'format'    => '',
                                    'current'   => $page,
                                    'total'     => $total_pages,
                                    'prev_text' => __( 'Previous', 'lead-assistant' ),
                                    'next_text' => __( 'Next', 'lead-assistant' ),
                                )
                            )
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_detail( int $lead_id ): void {
        global $wpdb;

        $leads_table    = $wpdb->prefix . 'lead_assistant_leads';
        $messages_table = $wpdb->prefix . 'lead_assistant_messages';

        $lead = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, created_at, updated_at, status, name, email, phone, consent_privacy, consent_at
                FROM {$leads_table}
                WHERE id = %d
                  AND status IN ('qualified', 'captured')
                LIMIT 1",
                $lead_id
            ),
            ARRAY_A
        );

        if ( ! is_array( $lead ) ) {
            wp_die( esc_html__( 'Lead not found.', 'lead-assistant' ) );
        }

        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT created_at, role, scope, content
                FROM {$messages_table}
                WHERE lead_id = %d
                ORDER BY id ASC",
                $lead_id
            ),
            ARRAY_A
        );

        if ( ! is_array( $messages ) ) {
            $messages = array();
        }

        $back_url = add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) );
        ?>
        <div class="wrap">
            <p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php echo esc_html__( 'Back to leads', 'lead-assistant' ); ?></a></p>
            <h1><?php echo esc_html( sprintf( __( 'Lead #%d', 'lead-assistant' ), $lead_id ) ); ?></h1>

            <table class="widefat striped" style="max-width: 900px; margin-bottom: 24px;">
                <tbody>
                    <tr><th><?php echo esc_html__( 'Status', 'lead-assistant' ); ?></th><td><code><?php echo esc_html( (string) $lead['status'] ); ?></code></td></tr>
                    <tr><th><?php echo esc_html__( 'Name', 'lead-assistant' ); ?></th><td><?php echo esc_html( self::display_value( $lead['name'] ?? '' ) ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Email', 'lead-assistant' ); ?></th><td><?php echo esc_html( self::display_value( $lead['email'] ?? '' ) ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Phone', 'lead-assistant' ); ?></th><td><?php echo esc_html( self::display_value( $lead['phone'] ?? '' ) ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Created', 'lead-assistant' ); ?></th><td><?php echo esc_html( self::format_utc_date( (string) $lead['created_at'] ) ); ?></td></tr>
                    <tr><th><?php echo esc_html__( 'Last activity', 'lead-assistant' ); ?></th><td><?php echo esc_html( self::format_utc_date( (string) $lead['updated_at'] ) ); ?></td></tr>
                    <tr>
                        <th><?php echo esc_html__( 'Privacy consent', 'lead-assistant' ); ?></th>
                        <td>
                            <?php
                            echo esc_html(
                                (int) ( $lead['consent_privacy'] ?? 0 ) === 1
                                    ? self::format_utc_date( (string) ( $lead['consent_at'] ?? '' ) )
                                    : __( 'Not captured', 'lead-assistant' )
                            );
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__( 'Conversation', 'lead-assistant' ); ?></h2>
            <?php if ( empty( $messages ) ) : ?>
                <p><?php echo esc_html__( 'No conversation messages found.', 'lead-assistant' ); ?></p>
            <?php else : ?>
                <div style="max-width: 900px;">
                    <?php foreach ( $messages as $message ) : ?>
                        <div style="margin: 0 0 12px; padding: 12px; border: 1px solid #dcdcde; background: #fff;">
                            <p style="margin: 0 0 6px;">
                                <strong><?php echo esc_html( ucfirst( (string) $message['role'] ) ); ?></strong>
                                <?php if ( ! empty( $message['scope'] ) ) : ?>
                                    &middot; <code><?php echo esc_html( (string) $message['scope'] ); ?></code>
                                <?php endif; ?>
                                &middot; <?php echo esc_html( self::format_utc_date( (string) $message['created_at'] ) ); ?>
                            </p>
                            <div style="white-space: pre-wrap; overflow-wrap: anywhere;"><?php echo esc_html( (string) $message['content'] ); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @return array{search:string,status:string,date_from:string,date_to:string}
     */
    private static function filters_from_request(): array {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['s'] ) ) : '';
        $search = function_exists( 'mb_substr' ) ? mb_substr( trim( $search ), 0, 190 ) : substr( trim( $search ), 0, 190 );

        $status = isset( $_GET['status'] ) ? sanitize_key( (string) wp_unslash( $_GET['status'] ) ) : '';
        if ( ! in_array( $status, array( '', 'qualified', 'captured' ), true ) ) {
            $status = '';
        }

        $date_from = isset( $_GET['date_from'] ) ? self::valid_date( (string) wp_unslash( $_GET['date_from'] ) ) : '';
        $date_to   = isset( $_GET['date_to'] ) ? self::valid_date( (string) wp_unslash( $_GET['date_to'] ) ) : '';

        return array(
            'search'    => $search,
            'status'    => $status,
            'date_from' => $date_from,
            'date_to'   => $date_to,
        );
    }

    /**
     * @param array{search:string,status:string,date_from:string,date_to:string} $filters
     */
    private static function query_count( array $filters ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'lead_assistant_leads';
        $where = self::build_where( $filters );
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
    }

    /**
     * @param array{search:string,status:string,date_from:string,date_to:string} $filters
     * @return array<int,array<string,mixed>>
     */
    private static function query_rows( array $filters, int $limit, int $offset ): array {
        global $wpdb;

        $limit  = max( 1, min( 500, $limit ) );
        $offset = max( 0, $offset );
        $table  = $wpdb->prefix . 'lead_assistant_leads';
        $where  = self::build_where( $filters );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, created_at, updated_at, status, name, email, phone, consent_privacy, consent_at
                FROM {$table}
                {$where}
                ORDER BY updated_at DESC, id DESC
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return is_array( $rows ) ? $rows : array();
    }

    /**
     * @param array{search:string,status:string,date_from:string,date_to:string} $filters
     */
    private static function build_where( array $filters ): string {
        global $wpdb;

        $clauses = array( "status IN ('qualified', 'captured')" );
        $args    = array();

        if ( $filters['status'] !== '' ) {
            $clauses[] = 'status = %s';
            $args[]    = $filters['status'];
        }

        if ( $filters['search'] !== '' ) {
            $like      = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $clauses[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $args[]    = $like;
            $args[]    = $like;
            $args[]    = $like;
        }

        if ( $filters['date_from'] !== '' ) {
            $from = self::local_date_boundary_to_utc( $filters['date_from'], false );
            if ( $from !== '' ) {
                $clauses[] = 'updated_at >= %s';
                $args[]    = $from;
            }
        }

        if ( $filters['date_to'] !== '' ) {
            $to = self::local_date_boundary_to_utc( $filters['date_to'], true );
            if ( $to !== '' ) {
                $clauses[] = 'updated_at < %s';
                $args[]    = $to;
            }
        }

        $sql = 'WHERE ' . implode( ' AND ', $clauses );
        return empty( $args ) ? $sql : $wpdb->prepare( $sql, $args );
    }

    private static function valid_date( string $value ): string {
        $value = trim( sanitize_text_field( $value ) );
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
            return '';
        }

        return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $value : '';
    }

    private static function local_date_boundary_to_utc( string $date, bool $next_day ): string {
        try {
            $local = new DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
            if ( $next_day ) {
                $local = $local->modify( '+1 day' );
            }

            return $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
        } catch ( Exception $exception ) {
            return '';
        }
    }

    /**
     * @param array{search:string,status:string,date_from:string,date_to:string} $filters
     * @return array<string,string>
     */
    private static function filter_query_args( array $filters ): array {
        $args = array();

        if ( $filters['search'] !== '' ) {
            $args['s'] = $filters['search'];
        }
        if ( $filters['status'] !== '' ) {
            $args['status'] = $filters['status'];
        }
        if ( $filters['date_from'] !== '' ) {
            $args['date_from'] = $filters['date_from'];
        }
        if ( $filters['date_to'] !== '' ) {
            $args['date_to'] = $filters['date_to'];
        }

        return $args;
    }

    private static function csv_cell( string $value ): string {
        if ( preg_match( '/^[\s]*[=+\-@\t\r]/u', $value ) ) {
            return "'" . $value;
        }

        return $value;
    }

    private static function display_value( $value ): string {
        $value = trim( (string) $value );
        return $value !== '' ? $value : '—';
    }

    private static function format_utc_date( string $value ): string {
        if ( $value === '' ) {
            return '—';
        }

        $formatted = get_date_from_gmt( $value, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
        return $formatted !== '' ? $formatted : $value;
    }
}
