<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_AI_Provider {
    private const ENDPOINT        = 'https://api.openai.com/v1/responses';
    private const MODELS_ENDPOINT = 'https://api.openai.com/v1/models';
    private const DEFAULT_MODEL   = 'gpt-6.1-terra';

    public static function is_configured(): bool {
        return Lead_Assistant_Credential_Store::api_key() !== '';
    }

    public static function connection_source(): string {
        return Lead_Assistant_Credential_Store::source();
    }

    public static function model(): string {
        $model = defined( 'LEAD_ASSISTANT_OPENAI_MODEL' )
            ? (string) LEAD_ASSISTANT_OPENAI_MODEL
            : self::DEFAULT_MODEL;

        /**
         * Filter the OpenAI model used by Lead Assistant.
         *
         * @param string $model Model identifier.
         */
        return (string) apply_filters( 'lead_assistant_openai_model', $model );
    }

    /**
     * Validate authentication and confirm the configured model is available to the key.
     * This uses the Models API and does not generate model output.
     *
     * @return true|WP_Error
     */
    public static function validate_api_key( string $api_key ) {
        $api_key = trim( $api_key );

        if ( $api_key === '' || strlen( $api_key ) > 512 || preg_match( '/\s/', $api_key ) ) {
            return new WP_Error(
                'lead_assistant_ai_key_invalid_format',
                __( 'The API key format is invalid.', 'lead-assistant' )
            );
        }

        $response = wp_remote_get(
            self::MODELS_ENDPOINT,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            self::log_error( 'credential_validation_transport', $response->get_error_code() );
            return new WP_Error(
                'lead_assistant_ai_key_network_error',
                __( 'Could not reach the AI provider to validate the key.', 'lead-assistant' )
            );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( $status === 401 || $status === 403 ) {
            self::log_error( 'credential_validation_auth', (string) $status );
            return new WP_Error(
                'lead_assistant_ai_key_auth_error',
                __( 'The API key could not be authenticated.', 'lead-assistant' )
            );
        }

        if ( $status < 200 || $status >= 300 ) {
            self::log_error( 'credential_validation_http', (string) $status );
            return new WP_Error(
                'lead_assistant_ai_key_service_error',
                __( 'The AI provider could not validate the key right now.', 'lead-assistant' )
            );
        }

        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
            self::log_error( 'credential_validation_invalid_response', (string) $status );
            return new WP_Error(
                'lead_assistant_ai_key_service_error',
                __( 'The AI provider returned an unexpected validation response.', 'lead-assistant' )
            );
        }

        $configured_model = self::model();
        foreach ( $data['data'] as $model ) {
            if ( is_array( $model ) && isset( $model['id'] ) && (string) $model['id'] === $configured_model ) {
                return true;
            }
        }

        return new WP_Error(
            'lead_assistant_ai_model_unavailable',
            sprintf(
                /* translators: %s: configured model identifier. */
                __( 'The API key is valid, but the configured model (%s) is not available to this account.', 'lead-assistant' ),
                $configured_model
            )
        );
    }

    /**
     * Generate one governed assistant turn.
     *
     * @param array<int,array{role:string,content:string}> $history Prior valid conversation turns.
     * @return array{scope:string,reply:string,follow_up_question:string}|WP_Error
     */
    public static function respond( array $history, string $message ) {
        $api_key = Lead_Assistant_Credential_Store::api_key();

        if ( $api_key === '' ) {
            return new WP_Error(
                'lead_assistant_ai_not_configured',
                __( 'The AI provider is not configured.', 'lead-assistant' )
            );
        }

        $message = self::clean_text( $message, 1500 );
        if ( $message === '' ) {
            return new WP_Error(
                'lead_assistant_empty_message',
                __( 'The message is empty.', 'lead-assistant' )
            );
        }

        $policy = Lead_Assistant_Conversation_Governor::build_policy(
            Lead_Assistant_Settings::business_description(),
            Lead_Assistant_Settings::goal()
        );

        $instructions = $policy . "\n\nOUTPUT CONTRACT\n"
            . "Return the response using the supplied JSON schema.\n"
            . "The reply field must contain the useful answer or redirection, but no commercial qualification question.\n"
            . "The follow_up_question field must contain either an empty string or exactly one short, relevant question.\n"
            . "For OFF_TOPIC, keep follow_up_question empty and redirect to the last valid business topic when one exists.\n";

        $payload = array(
            'model'             => self::model(),
            'store'             => false,
            'instructions'      => $instructions,
            'input'             => self::build_input( $history, $message ),
            'max_output_tokens' => 500,
            'text'              => array(
                'format' => array(
                    'type'        => 'json_schema',
                    'name'        => 'lead_assistant_response',
                    'description' => 'A focused business-assistant response governed by the site scope.',
                    'strict'      => true,
                    'schema'      => array(
                        'type'                 => 'object',
                        'properties'           => array(
                            'scope'              => array(
                                'type' => 'string',
                                'enum' => Lead_Assistant_Conversation_Governor::allowed_states(),
                            ),
                            'reply'              => array(
                                'type'        => 'string',
                                'description' => 'Concise useful answer or polite redirection. Do not include the qualification question here.',
                            ),
                            'follow_up_question' => array(
                                'type'        => 'string',
                                'description' => 'Empty string or one short relevant qualification/data question.',
                            ),
                        ),
                        'required'             => array( 'scope', 'reply', 'follow_up_question' ),
                        'additionalProperties' => false,
                    ),
                ),
            ),
        );

        $response = wp_remote_post(
            self::ENDPOINT,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            )
        );

        if ( is_wp_error( $response ) ) {
            self::log_error( 'transport_error', $response->get_error_code() );
            return new WP_Error(
                'lead_assistant_ai_unavailable',
                __( 'The assistant is temporarily unavailable.', 'lead-assistant' )
            );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = (string) wp_remote_retrieve_body( $response );
        $data   = json_decode( $body, true );

        if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
            self::log_error( 'http_error', (string) $status );
            return new WP_Error(
                'lead_assistant_ai_unavailable',
                __( 'The assistant is temporarily unavailable.', 'lead-assistant' )
            );
        }

        $output_text = self::extract_output_text( $data );
        if ( $output_text === '' ) {
            $refusal = self::extract_refusal( $data );
            self::log_error( 'empty_output', $refusal !== '' ? 'refusal' : 'no_text' );

            return new WP_Error(
                'lead_assistant_ai_no_output',
                __( 'The assistant could not produce a response.', 'lead-assistant' )
            );
        }

        $result = json_decode( $output_text, true );
        if ( ! is_array( $result ) ) {
            self::log_error( 'invalid_structured_output', 'json_decode' );
            return new WP_Error(
                'lead_assistant_ai_invalid_output',
                __( 'The assistant returned an invalid response.', 'lead-assistant' )
            );
        }

        $scope = isset( $result['scope'] ) ? strtoupper( sanitize_key( (string) $result['scope'] ) ) : '';
        if ( ! in_array( $scope, Lead_Assistant_Conversation_Governor::allowed_states(), true ) ) {
            self::log_error( 'invalid_scope', $scope );
            return new WP_Error(
                'lead_assistant_ai_invalid_scope',
                __( 'The assistant returned an invalid response.', 'lead-assistant' )
            );
        }

        $reply    = self::clean_text( (string) ( $result['reply'] ?? '' ), 5000 );
        $question = self::clean_text( (string) ( $result['follow_up_question'] ?? '' ), 500 );

        if ( $reply === '' ) {
            return new WP_Error(
                'lead_assistant_ai_empty_reply',
                __( 'The assistant returned an empty response.', 'lead-assistant' )
            );
        }

        if ( $scope === Lead_Assistant_Conversation_Governor::OFF_TOPIC ) {
            $question = '';
        }

        return array(
            'scope'              => $scope,
            'reply'              => $reply,
            'follow_up_question' => $question,
        );
    }

    /**
     * @param array<int,array{role:string,content:string}> $history
     * @return array<int,array{role:string,content:string}>
     */
    private static function build_input( array $history, string $message ): array {
        $bounded = array();
        $chars   = 0;

        foreach ( array_reverse( $history ) as $item ) {
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

            $length = strlen( $content );
            if ( $chars + $length > 10000 || count( $bounded ) >= 10 ) {
                break;
            }

            array_unshift(
                $bounded,
                array(
                    'role'    => $role,
                    'content' => $content,
                )
            );
            $chars += $length;
        }

        $bounded[] = array(
            'role'    => 'user',
            'content' => $message,
        );

        return $bounded;
    }

    private static function extract_output_text( array $response ): string {
        if ( empty( $response['output'] ) || ! is_array( $response['output'] ) ) {
            return '';
        }

        $parts = array();
        foreach ( $response['output'] as $item ) {
            if ( ! is_array( $item ) || ( $item['type'] ?? '' ) !== 'message' ) {
                continue;
            }

            $content_items = $item['content'] ?? array();
            if ( ! is_array( $content_items ) ) {
                continue;
            }

            foreach ( $content_items as $content ) {
                if ( is_array( $content ) && ( $content['type'] ?? '' ) === 'output_text' && isset( $content['text'] ) ) {
                    $parts[] = (string) $content['text'];
                }
            }
        }

        return trim( implode( "\n", $parts ) );
    }

    private static function extract_refusal( array $response ): string {
        if ( empty( $response['output'] ) || ! is_array( $response['output'] ) ) {
            return '';
        }

        foreach ( $response['output'] as $item ) {
            if ( ! is_array( $item ) || empty( $item['content'] ) || ! is_array( $item['content'] ) ) {
                continue;
            }

            foreach ( $item['content'] as $content ) {
                if ( is_array( $content ) && ( $content['type'] ?? '' ) === 'refusal' ) {
                    return self::clean_text( (string) ( $content['refusal'] ?? '' ), 500 );
                }
            }
        }

        return '';
    }

    private static function clean_text( string $value, int $max_length ): string {
        $value = trim( wp_strip_all_tags( $value ) );

        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $value, 0, $max_length );
        }

        return substr( $value, 0, $max_length );
    }

    private static function log_error( string $event, string $detail ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( 'Lead Assistant AI: %s (%s)', sanitize_key( $event ), sanitize_text_field( $detail ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
