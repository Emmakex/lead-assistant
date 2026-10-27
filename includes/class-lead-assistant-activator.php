<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_Activator {
    public static function activate(): void {
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

        add_option( 'lead_assistant_db_version', '0.1.0', '', false );
    }
}
