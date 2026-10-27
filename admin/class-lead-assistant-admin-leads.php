<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_Admin_Leads {
    private const PAGE_SLUG = 'lead-assistant-leads';
    private const PER_PAGE  = 20;

    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
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

    private static function render_list(): void {
        global $wpdb;

        $page       = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
        $offset     = ( $page - 1 ) * self::PER_PAGE;
        $leads_table = $wpdb->prefix . 'lead_assistant_leads';

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$leads_table} WHERE status IN ('qualified', 'captured')"
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, created_at, updated_at, status, name, email, phone, consent_privacy, consent_at
                FROM {$leads_table}
                WHERE status IN ('qualified', 'captured')
                ORDER BY updated_at DESC, id DESC
                LIMIT %d OFFSET %d",
                self::PER_PAGE,
                $offset
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        $total_pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Leads', 'lead-assistant' ); ?></h1>
            <p><?php echo esc_html__( 'Qualified conversations and captured contacts appear here. Unqualified chat sessions are not shown in this view.', 'lead-assistant' ); ?></p>

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
                            <td colspan="7"><?php echo esc_html__( 'No qualified leads yet.', 'lead-assistant' ); ?></td>
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
                                    'base'      => add_query_arg(
                                        array(
                                            'page'  => self::PAGE_SLUG,
                                            'paged' => '%#%',
                                        ),
                                        admin_url( 'admin.php' )
                                    ),
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
