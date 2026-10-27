<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_Conversation_Store {
    /**
     * @return array{lead_id:int,token:string}|WP_Error
     */
    public static function create_session() {
        global $wpdb;

        try {
            $token = bin2hex( random_bytes( 32 ) );
        } catch ( Exception $exception ) {
            return new WP_Error(
                'lead_assistant_session_token_failed',
                __( 'Could not start the conversation.', 'lead-assistant' )
            );
        }

        $now   = current_time( 'mysql', true );
        $table = $wpdb->prefix . 'lead_assistant_leads';

        $inserted = $wpdb->insert(
            $table,
            array(
                'public_token'     => hash( 'sha256', $token ),
                'created_at'       => $now,
                'updated_at'       => $now,
                'status'           => 'conversation',
                'business_context' => wp_json_encode(
                    array(
                        'business' => Lead_Assistant_Settings::business_description(),
                        'goal'     => Lead_Assistant_Settings::goal(),
                    ),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $inserted === false ) {
            return new WP_Error(
                'lead_assistant_session_create_failed',
                __( 'Could not start the conversation.', 'lead-assistant' )
            );
        }

        return array(
            'lead_id' => (int) $wpdb->insert_id,
            'token'   => $token,
        );
    }

    public static function resolve_lead_id( string $token ): int {
        global $wpdb;

        if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
            return 0;
        }

        $table = $wpdb->prefix . 'lead_assistant_leads';
        $hash  = hash( 'sha256', $token );

        $lead_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE public_token = %s LIMIT 1",
                $hash
            )
        );

        return $lead_id ? (int) $lead_id : 0;
    }

    /**
     * @return array<int,array{role:string,content:string}>
     */
    public static function load_context( int $lead_id, int $limit = 10 ): array {
        global $wpdb;

        if ( $lead_id < 1 ) {
            return array();
        }

        $limit = max( 1, min( 20, $limit ) );
        $table = $wpdb->prefix . 'lead_assistant_messages';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content
                FROM {$table}
                WHERE lead_id = %d
                  AND (scope IS NULL OR scope <> %s)
                ORDER BY id DESC
                LIMIT %d",
                $lead_id,
                Lead_Assistant_Conversation_Governor::OFF_TOPIC,
                $limit
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        $context = array();
        foreach ( array_reverse( $rows ) as $row ) {
            $role = isset( $row['role'] ) ? sanitize_key( (string) $row['role'] ) : '';
            if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
                continue;
            }

            $content = trim( wp_strip_all_tags( (string) ( $row['content'] ?? '' ) ) );
            if ( $content === '' ) {
                continue;
            }

            $context[] = array(
                'role'    => $role,
                'content' => $content,
            );
        }

        return $context;
    }

    public static function append_turn( int $lead_id, string $user_message, array $assistant_result ): bool {
        global $wpdb;

        if ( $lead_id < 1 ) {
            return false;
        }

        $scope = isset( $assistant_result['scope'] )
            ? (string) $assistant_result['scope']
            : Lead_Assistant_Conversation_Governor::IN_SCOPE;

        $reply = trim( (string) ( $assistant_result['reply'] ?? '' ) );
        $question = trim( (string) ( $assistant_result['follow_up_question'] ?? '' ) );
        $assistant_message = $reply;

        if ( $question !== '' ) {
            $assistant_message .= ( $assistant_message !== '' ? "\n\n" : '' ) . $question;
        }

        $messages_table = $wpdb->prefix . 'lead_assistant_messages';
        $leads_table    = $wpdb->prefix . 'lead_assistant_leads';
        $now            = current_time( 'mysql', true );

        $user_ok = $wpdb->insert(
            $messages_table,
            array(
                'lead_id'    => $lead_id,
                'created_at' => $now,
                'role'       => 'user',
                'scope'      => $scope,
                'content'    => $user_message,
                'meta'       => null,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        $assistant_ok = $wpdb->insert(
            $messages_table,
            array(
                'lead_id'    => $lead_id,
                'created_at' => $now,
                'role'       => 'assistant',
                'scope'      => $scope,
                'content'    => $assistant_message,
                'meta'       => null,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $user_ok === false || $assistant_ok === false ) {
            return false;
        }

        $status = $scope === Lead_Assistant_Conversation_Governor::CONVERSION_READY
            ? 'qualified'
            : 'conversation';

        $wpdb->update(
            $leads_table,
            array(
                'updated_at' => $now,
                'status'     => $status,
                'lead_context' => wp_json_encode(
                    array(
                        'last_scope' => $scope,
                    ),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ),
            array( 'id' => $lead_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        return true;
    }
}
