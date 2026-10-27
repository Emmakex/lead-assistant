<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_REST_API {
    private const NAMESPACE = 'lead-assistant/v1';
    private const ROUTE     = '/message';

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'handle_message' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'message' => array(
                        'required' => true,
                        'type'     => 'string',
                    ),
                    'session_token' => array(
                        'required' => false,
                        'type'     => 'string',
                        'default'  => '',
                    ),
                ),
            )
        );
    }

    public static function handle_message( WP_REST_Request $request ) {
        if ( ! Lead_Assistant_Settings::is_configured() || ! Lead_Assistant_AI_Provider::is_configured() ) {
            return new WP_Error(
                'lead_assistant_not_ready',
                __( 'The assistant is temporarily unavailable.', 'lead-assistant' ),
                array( 'status' => 503 )
            );
        }

        $origin_error = self::validate_origin( $request );
        if ( is_wp_error( $origin_error ) ) {
            return $origin_error;
        }

        $rate_limit_error = self::check_rate_limit();
        if ( is_wp_error( $rate_limit_error ) ) {
            return $rate_limit_error;
        }

        $message = self::clean_text( (string) $request->get_param( 'message' ), 1500 );
        if ( $message === '' ) {
            return new WP_Error(
                'lead_assistant_empty_message',
                __( 'Please write a message.', 'lead-assistant' ),
                array( 'status' => 400 )
            );
        }

        $session_token = strtolower( trim( (string) $request->get_param( 'session_token' ) ) );
        $lead_id       = 0;
        $new_token     = '';

        if ( $session_token !== '' ) {
            $lead_id = Lead_Assistant_Conversation_Store::resolve_lead_id( $session_token );
            if ( $lead_id < 1 ) {
                return new WP_Error(
                    'lead_assistant_invalid_session',
                    __( 'This conversation session is no longer valid.', 'lead-assistant' ),
                    array( 'status' => 400 )
                );
            }
        } else {
            $session = Lead_Assistant_Conversation_Store::create_session();
            if ( is_wp_error( $session ) ) {
                return new WP_Error(
                    'lead_assistant_session_failed',
                    __( 'The assistant is temporarily unavailable.', 'lead-assistant' ),
                    array( 'status' => 500 )
                );
            }

            $lead_id   = (int) $session['lead_id'];
            $new_token = (string) $session['token'];
        }

        $history = Lead_Assistant_Conversation_Store::load_context( $lead_id, 10 );
        $result  = Lead_Assistant_AI_Provider::respond( $history, $message );

        if ( is_wp_error( $result ) ) {
            return new WP_Error(
                'lead_assistant_runtime_error',
                __( 'The assistant is temporarily unavailable.', 'lead-assistant' ),
                array( 'status' => 502 )
            );
        }

        if ( ! Lead_Assistant_Conversation_Store::append_turn( $lead_id, $message, $result ) ) {
            return new WP_Error(
                'lead_assistant_persistence_error',
                __( 'The assistant is temporarily unavailable.', 'lead-assistant' ),
                array( 'status' => 500 )
            );
        }

        if ( $new_token !== '' ) {
            $result['session_token'] = $new_token;
        }

        return rest_ensure_response( $result );
    }

    /**
     * Public endpoint: origin validation is a browser-abuse guard, not authentication.
     */
    private static function validate_origin( WP_REST_Request $request ) {
        $origin = (string) $request->get_header( 'origin' );
        if ( $origin === '' ) {
            return true;
        }

        $origin_host = wp_parse_url( $origin, PHP_URL_HOST );
        $site_host   = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

        if ( ! is_string( $origin_host ) || ! is_string( $site_host ) || strtolower( $origin_host ) !== strtolower( $site_host ) ) {
            return new WP_Error(
                'lead_assistant_invalid_origin',
                __( 'Request origin is not allowed.', 'lead-assistant' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    private static function check_rate_limit() {
        $limit  = (int) apply_filters( 'lead_assistant_rate_limit_requests', 20 );
        $window = (int) apply_filters( 'lead_assistant_rate_limit_window', 10 * MINUTE_IN_SECONDS );

        if ( $limit < 1 || $window < 1 ) {
            return true;
        }

        $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $hash   = hash_hmac( 'sha256', $remote, wp_salt( 'nonce' ) );
        $key    = 'lead_assistant_rl_' . substr( $hash, 0, 32 );
        $state  = get_transient( $key );

        if ( ! is_array( $state ) ) {
            set_transient(
                $key,
                array(
                    'count' => 1,
                ),
                $window
            );
            return true;
        }

        $count = isset( $state['count'] ) ? (int) $state['count'] : 0;
        if ( $count >= $limit ) {
            return new WP_Error(
                'lead_assistant_rate_limited',
                __( 'Too many messages. Please try again shortly.', 'lead-assistant' ),
                array( 'status' => 429 )
            );
        }

        $state['count'] = $count + 1;
        set_transient( $key, $state, $window );
        return true;
    }

    private static function clean_text( string $value, int $max_length ): string {
        $value = trim( wp_strip_all_tags( $value ) );

        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $value, 0, $max_length );
        }

        return substr( $value, 0, $max_length );
    }
}
