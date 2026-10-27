<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_Settings {
    private const OPTION_BUSINESS = 'lead_assistant_business_description';
    private const OPTION_GOAL     = 'lead_assistant_goal';

    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_post_lead_assistant_connect_ai', array( __CLASS__, 'handle_connect_ai' ) );
        add_action( 'admin_post_lead_assistant_disconnect_ai', array( __CLASS__, 'handle_disconnect_ai' ) );
    }

    public static function register_menu(): void {
        add_menu_page(
            __( 'Lead Assistant', 'lead-assistant' ),
            __( 'Lead Assistant', 'lead-assistant' ),
            'manage_options',
            'lead-assistant',
            array( __CLASS__, 'render_page' ),
            'dashicons-format-chat',
            30
        );

        add_submenu_page(
            'lead-assistant',
            __( 'Settings', 'lead-assistant' ),
            __( 'Settings', 'lead-assistant' ),
            'manage_options',
            'lead-assistant',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function register_settings(): void {
        register_setting(
            'lead_assistant_settings',
            self::OPTION_BUSINESS,
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'lead_assistant_settings',
            self::OPTION_GOAL,
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );
    }

    public static function business_description(): string {
        return (string) get_option( self::OPTION_BUSINESS, '' );
    }

    public static function goal(): string {
        return (string) get_option( self::OPTION_GOAL, '' );
    }

    public static function is_configured(): bool {
        return self::business_description() !== '' && self::goal() !== '';
    }

    public static function handle_connect_ai(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'lead-assistant' ) );
        }

        check_admin_referer( 'lead_assistant_connect_ai' );

        if ( Lead_Assistant_Credential_Store::has_server_override() ) {
            self::redirect_with_ai_notice( 'server_override' );
        }

        $api_key = isset( $_POST['openai_api_key'] )
            ? trim( (string) wp_unslash( $_POST['openai_api_key'] ) )
            : '';

        $validated = Lead_Assistant_AI_Provider::validate_api_key( $api_key );
        if ( is_wp_error( $validated ) ) {
            self::redirect_with_ai_notice( self::validation_notice_code( $validated ) );
        }

        $saved = Lead_Assistant_Credential_Store::save( $api_key );
        if ( is_wp_error( $saved ) ) {
            self::redirect_with_ai_notice( 'storage_error' );
        }

        self::redirect_with_ai_notice( 'connected' );
    }

    public static function handle_disconnect_ai(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'lead-assistant' ) );
        }

        check_admin_referer( 'lead_assistant_disconnect_ai' );

        if ( Lead_Assistant_Credential_Store::has_server_override() ) {
            self::redirect_with_ai_notice( 'server_override' );
        }

        Lead_Assistant_Credential_Store::delete();
        self::redirect_with_ai_notice( 'disconnected' );
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $ai_connected = Lead_Assistant_AI_Provider::is_configured();
        $ai_source    = Lead_Assistant_AI_Provider::connection_source();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Lead Assistant', 'lead-assistant' ); ?></h1>
            <p><?php echo esc_html__( 'Connect the AI, describe your business and define the goal. Lead Assistant handles the conversation policy automatically.', 'lead-assistant' ); ?></p>

            <?php self::render_ai_notice(); ?>

            <hr>
            <h2><?php echo esc_html__( '1. Connect AI', 'lead-assistant' ); ?></h2>
            <p>
                <strong><?php echo esc_html__( 'Status:', 'lead-assistant' ); ?></strong>
                <?php if ( $ai_connected ) : ?>
                    <?php echo esc_html__( 'Connected', 'lead-assistant' ); ?>
                    <?php if ( $ai_source === 'server' ) : ?>
                        <?php echo esc_html__( '(server configuration)', 'lead-assistant' ); ?>
                    <?php endif; ?>
                <?php else : ?>
                    <?php echo esc_html__( 'Not connected', 'lead-assistant' ); ?>
                <?php endif; ?>
            </p>

            <?php if ( $ai_source === 'server' ) : ?>
                <p><?php echo esc_html__( 'The AI key is provided by wp-config.php or the server environment. This takes priority over WordPress settings and cannot be changed from this screen.', 'lead-assistant' ); ?></p>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width: 720px;">
                    <input type="hidden" name="action" value="lead_assistant_connect_ai">
                    <?php wp_nonce_field( 'lead_assistant_connect_ai' ); ?>
                    <label for="lead-assistant-openai-key"><strong><?php echo esc_html__( 'OpenAI API key', 'lead-assistant' ); ?></strong></label>
                    <input
                        id="lead-assistant-openai-key"
                        name="openai_api_key"
                        type="password"
                        class="regular-text"
                        autocomplete="new-password"
                        spellcheck="false"
                        maxlength="512"
                        placeholder="<?php echo esc_attr( $ai_source === 'stored' ? __( 'Enter a new key to replace the current connection', 'lead-assistant' ) : __( 'Paste your API key', 'lead-assistant' ) ); ?>"
                        required
                        style="display:block; width:100%; max-width:620px; margin:8px 0;"
                    >
                    <p class="description">
                        <?php echo esc_html__( 'The key is validated server-side, encrypted before storage and never displayed again or sent to the public browser.', 'lead-assistant' ); ?>
                    </p>
                    <?php submit_button( $ai_source === 'stored' ? __( 'Replace connection', 'lead-assistant' ) : __( 'Connect AI', 'lead-assistant' ), 'primary', 'submit', false ); ?>
                </form>

                <?php if ( $ai_source === 'stored' ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
                        <input type="hidden" name="action" value="lead_assistant_disconnect_ai">
                        <?php wp_nonce_field( 'lead_assistant_disconnect_ai' ); ?>
                        <?php submit_button( __( 'Disconnect AI', 'lead-assistant' ), 'secondary', 'submit', false ); ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <hr>
            <form method="post" action="options.php">
                <?php settings_fields( 'lead_assistant_settings' ); ?>

                <h2><?php echo esc_html__( '2. Describe your business', 'lead-assistant' ); ?></h2>
                <p><?php echo esc_html__( 'A short, factual description is enough. No prompt engineering is required.', 'lead-assistant' ); ?></p>
                <textarea id="lead-assistant-business" name="<?php echo esc_attr( self::OPTION_BUSINESS ); ?>" rows="5" class="large-text" style="max-width:900px;" placeholder="<?php echo esc_attr__( 'Example: We are a local renovation company offering kitchens, bathrooms, painting and flooring.', 'lead-assistant' ); ?>"><?php echo esc_textarea( self::business_description() ); ?></textarea>

                <h2><?php echo esc_html__( '3. Define the goal', 'lead-assistant' ); ?></h2>
                <p><?php echo esc_html__( 'Tell the assistant what a useful commercial outcome looks like.', 'lead-assistant' ); ?></p>
                <textarea id="lead-assistant-goal" name="<?php echo esc_attr( self::OPTION_GOAL ); ?>" rows="4" class="large-text" style="max-width:900px;" placeholder="<?php echo esc_attr__( 'Example: Answer service questions and identify visitors with a real renovation project.', 'lead-assistant' ); ?>"><?php echo esc_textarea( self::goal() ); ?></textarea>

                <?php submit_button( __( 'Save business configuration', 'lead-assistant' ) ); ?>
            </form>

            <hr>
            <h2><?php echo esc_html__( 'Use the assistant', 'lead-assistant' ); ?></h2>
            <p><?php echo esc_html__( 'Add this shortcode to any page:', 'lead-assistant' ); ?></p>
            <code>[lead_assistant]</code>
        </div>
        <?php
    }

    private static function render_ai_notice(): void {
        $notice = isset( $_GET['lead_assistant_ai_notice'] )
            ? sanitize_key( (string) wp_unslash( $_GET['lead_assistant_ai_notice'] ) )
            : '';

        if ( $notice === '' ) {
            return;
        }

        $messages = array(
            'connected'       => array( 'success', __( 'AI connected successfully.', 'lead-assistant' ) ),
            'disconnected'    => array( 'success', __( 'AI connection removed from WordPress.', 'lead-assistant' ) ),
            'auth_error'      => array( 'error', __( 'The API key could not be authenticated. Check the key and try again.', 'lead-assistant' ) ),
            'model_error'     => array( 'error', __( 'The key is valid, but the model required by Lead Assistant is not available to this account.', 'lead-assistant' ) ),
            'network_error'   => array( 'error', __( 'WordPress could not reach the AI provider. Check the server connection and try again.', 'lead-assistant' ) ),
            'service_error'   => array( 'error', __( 'The AI provider could not validate the connection right now.', 'lead-assistant' ) ),
            'storage_error'   => array( 'error', __( 'The connection was valid but the key could not be stored securely on this server.', 'lead-assistant' ) ),
            'invalid'         => array( 'error', __( 'Enter a valid API key and try again.', 'lead-assistant' ) ),
            'server_override' => array( 'warning', __( 'This site uses an AI key supplied by server configuration. Change it on the server instead of WordPress.', 'lead-assistant' ) ),
        );

        if ( ! isset( $messages[ $notice ] ) ) {
            return;
        }

        $type    = $messages[ $notice ][0];
        $message = $messages[ $notice ][1];
        ?>
        <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php
    }

    private static function validation_notice_code( WP_Error $error ): string {
        switch ( $error->get_error_code() ) {
            case 'lead_assistant_ai_key_auth_error':
                return 'auth_error';
            case 'lead_assistant_ai_model_unavailable':
                return 'model_error';
            case 'lead_assistant_ai_key_network_error':
                return 'network_error';
            case 'lead_assistant_ai_key_service_error':
                return 'service_error';
            default:
                return 'invalid';
        }
    }

    private static function redirect_with_ai_notice( string $notice ): void {
        $url = add_query_arg(
            array(
                'page'                     => 'lead-assistant',
                'lead_assistant_ai_notice' => sanitize_key( $notice ),
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }
}
