<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_Activator {
    private const DB_VERSION = '0.4.0';

    public static function activate(): void {
        self::migrate();
    }

    public static function maybe_upgrade(): void {
        $installed = (string) get_option( 'lead_assistant_db_version', '0.0.0' );

        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            self::migrate();
        }
    }

    private static function migrate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $leads_table     = $wpdb->prefix . 'lead_assistant_leads';
        $messages_table  = $wpdb->prefix . 'lead_assistant_messages';

        $leads_sql = "CREATE TABLE {$leads_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            public_token CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'started',
            name VARCHAR(120) NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(60) NULL,
            consent_privacy TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            consent_at DATETIME NULL,
            business_context LONGTEXT NULL,
            lead_context LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_token (public_token),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        $messages_sql = "CREATE TABLE {$messages_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            role VARCHAR(20) NOT NULL,
            scope VARCHAR(32) NULL,
            content LONGTEXT NOT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $leads_sql );
        dbDelta( $messages_sql );

        update_option( 'lead_assistant_db_version', self::DB_VERSION, false );
    }
}
