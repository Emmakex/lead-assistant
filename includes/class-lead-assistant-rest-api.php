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
                    'history' => array(
                        'required' => false,
                        'type'     => 'array',
                        'default'  => array(),
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

        $history = self::sanitize_history( $request->get_param( 'history' ) );
        $result  = Lead_Assistant_AI_Provider::respond( $history, $message );

        if ( is_wp_error( $result ) ) {
            return new WP_Error(
                'lead_assistant_runtime_error',
                __( 'The assistant is temporarily unavailable.', 'lead-assistant' ),
                array( 'status' => 502 )
            );
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

    /**
     * @return array<int,array{role:string,content:string}>
     */
    private static function sanitize_history( $history ): array {
        if ( ! is_array( $history ) ) {
            return array();
        }

        $clean = array();
        foreach ( array_slice( $history, -10 ) as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $role = isset( $item['role'] ) ? sanitize_key( (string) $item['role'] ) : '';
            if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
                continue;
            }

            $content = self::clean_text( (string) ( $item['content'] ?? '' ), 1500 );
            if ( $content === '' ) {
                continue;
            }

            $clean[] = array(
                'role'    => $role,
                'content' => $content,
            );
        }

        return $clean;
    }

    private static function clean_text( string $value, int $max_length ): string {
        $value = trim( wp_strip_all_tags( $value ) );

        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $value, 0, $max_length );
        }

        return substr( $value, 0, $max_length );
    }
}
