<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_Credential_Store {
    private const OPTION_NAME = 'lead_assistant_openai_credential';
    private const CONTEXT     = 'lead-assistant-openai-credential-v1';

    public static function api_key(): string {
        $server_key = self::server_key();
        if ( $server_key !== '' ) {
            return $server_key;
        }

        return self::stored_key();
    }

    public static function source(): string {
        if ( self::server_key() !== '' ) {
            return 'server';
        }

        if ( self::stored_key() !== '' ) {
            return 'stored';
        }

        return 'none';
    }

    public static function has_server_override(): bool {
        return self::server_key() !== '';
    }

    /**
     * @return true|WP_Error
     */
    public static function save( string $api_key ) {
        $api_key = trim( $api_key );

        if ( $api_key === '' || strlen( $api_key ) > 512 || preg_match( '/\s/', $api_key ) ) {
            return new WP_Error(
                'lead_assistant_invalid_api_key_format',
                __( 'The API key format is invalid.', 'lead-assistant' )
            );
        }

        try {
            $payload = self::encrypt( $api_key );
        } catch ( Throwable $exception ) {
            return new WP_Error(
                'lead_assistant_credential_encryption_failed',
                __( 'The API key could not be stored securely on this server.', 'lead-assistant' )
            );
        }

        if ( is_wp_error( $payload ) ) {
            return $payload;
        }

        $encoded = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
        if ( ! is_string( $encoded ) || $encoded === '' ) {
            return new WP_Error(
                'lead_assistant_credential_encoding_failed',
                __( 'The API key could not be stored securely on this server.', 'lead-assistant' )
            );
        }

        if ( get_option( self::OPTION_NAME, null ) === null ) {
            $saved = add_option( self::OPTION_NAME, $encoded, '', false );
        } else {
            $saved = update_option( self::OPTION_NAME, $encoded, false );

            // update_option() returns false when the value is unchanged; that is still a valid save.
            if ( ! $saved && get_option( self::OPTION_NAME, '' ) === $encoded ) {
                $saved = true;
            }
        }

        if ( ! $saved ) {
            return new WP_Error(
                'lead_assistant_credential_save_failed',
                __( 'The API key could not be saved.', 'lead-assistant' )
            );
        }

        return true;
    }

    public static function delete(): bool {
        if ( get_option( self::OPTION_NAME, null ) === null ) {
            return true;
        }

        return delete_option( self::OPTION_NAME );
    }

    private static function server_key(): string {
        if ( defined( 'LEAD_ASSISTANT_OPENAI_API_KEY' ) ) {
            return trim( (string) LEAD_ASSISTANT_OPENAI_API_KEY );
        }

        $environment_key = getenv( 'LEAD_ASSISTANT_OPENAI_API_KEY' );
        return is_string( $environment_key ) ? trim( $environment_key ) : '';
    }

    private static function stored_key(): string {
        $encoded = get_option( self::OPTION_NAME, '' );
        if ( ! is_string( $encoded ) || $encoded === '' ) {
            return '';
        }

        $payload = json_decode( $encoded, true );
        if ( ! is_array( $payload ) || (int) ( $payload['v'] ?? 0 ) !== 1 ) {
            return '';
        }

        try {
            return self::decrypt( $payload );
        } catch ( Throwable $exception ) {
            return '';
        }
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function encrypt( string $plaintext ) {
        $key = self::encryption_key();

        if ( function_exists( 'sodium_crypto_secretbox' ) && defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) ) {
            $nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );

            return array(
                'v'      => 1,
                'alg'    => 'secretbox',
                'nonce'  => base64_encode( $nonce ),
                'cipher' => base64_encode( $cipher ),
            );
        }

        if ( function_exists( 'openssl_encrypt' ) ) {
            $iv  = random_bytes( 12 );
            $tag = '';
            $cipher = openssl_encrypt(
                $plaintext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                self::CONTEXT
            );

            if ( ! is_string( $cipher ) || $cipher === '' || $tag === '' ) {
                return new WP_Error(
                    'lead_assistant_credential_encryption_unavailable',
                    __( 'Secure credential encryption is not available on this server.', 'lead-assistant' )
                );
            }

            return array(
                'v'      => 1,
                'alg'    => 'aes-256-gcm',
                'iv'     => base64_encode( $iv ),
                'tag'    => base64_encode( $tag ),
                'cipher' => base64_encode( $cipher ),
            );
        }

        return new WP_Error(
            'lead_assistant_credential_encryption_unavailable',
            __( 'Secure credential encryption is not available on this server.', 'lead-assistant' )
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function decrypt( array $payload ): string {
        $key = self::encryption_key();
        $alg = (string) ( $payload['alg'] ?? '' );

        if ( $alg === 'secretbox' && function_exists( 'sodium_crypto_secretbox_open' ) ) {
            $nonce  = base64_decode( (string) ( $payload['nonce'] ?? '' ), true );
            $cipher = base64_decode( (string) ( $payload['cipher'] ?? '' ), true );

            if ( ! is_string( $nonce ) || ! is_string( $cipher ) ) {
                return '';
            }

            $plaintext = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
            return is_string( $plaintext ) ? trim( $plaintext ) : '';
        }

        if ( $alg === 'aes-256-gcm' && function_exists( 'openssl_decrypt' ) ) {
            $iv     = base64_decode( (string) ( $payload['iv'] ?? '' ), true );
            $tag    = base64_decode( (string) ( $payload['tag'] ?? '' ), true );
            $cipher = base64_decode( (string) ( $payload['cipher'] ?? '' ), true );

            if ( ! is_string( $iv ) || ! is_string( $tag ) || ! is_string( $cipher ) ) {
                return '';
            }

            $plaintext = openssl_decrypt(
                $cipher,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                self::CONTEXT
            );

            return is_string( $plaintext ) ? trim( $plaintext ) : '';
        }

        return '';
    }

    private static function encryption_key(): string {
        return hash(
            'sha256',
            wp_salt( 'auth' ) . "\n" . wp_salt( 'secure_auth' ) . "\n" . self::CONTEXT,
            true
        );
    }
}
