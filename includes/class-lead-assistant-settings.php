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

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $ai_connected = class_exists( 'Lead_Assistant_AI_Provider' ) && Lead_Assistant_AI_Provider::is_configured();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Lead Assistant', 'lead-assistant' ); ?></h1>
            <p><?php echo esc_html__( 'Describe the business and the commercial goal. Lead Assistant will generate the conversation policy automatically.', 'lead-assistant' ); ?></p>

            <h2><?php echo esc_html__( 'AI connection', 'lead-assistant' ); ?></h2>
            <p>
                <strong><?php echo esc_html__( 'Status:', 'lead-assistant' ); ?></strong>
                <?php echo esc_html( $ai_connected ? __( 'Connected', 'lead-assistant' ) : __( 'Not configured', 'lead-assistant' ) ); ?>
            </p>
            <?php if ( ! $ai_connected ) : ?>
                <p>
                    <?php
                    echo wp_kses_post(
                        __( 'For this development build, define <code>LEAD_ASSISTANT_OPENAI_API_KEY</code> in <code>wp-config.php</code> or as a server environment variable. The key is never stored in this public repository.', 'lead-assistant' )
                    );
                    ?>
                </p>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'lead_assistant_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="lead-assistant-business"><?php echo esc_html__( 'What does your business do?', 'lead-assistant' ); ?></label></th>
                        <td>
                            <textarea id="lead-assistant-business" name="<?php echo esc_attr( self::OPTION_BUSINESS ); ?>" rows="5" class="large-text" placeholder="<?php echo esc_attr__( 'Example: We are a real-estate agency specializing in new-build homes.', 'lead-assistant' ); ?>"><?php echo esc_textarea( self::business_description() ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lead-assistant-goal"><?php echo esc_html__( 'What should the assistant achieve?', 'lead-assistant' ); ?></label></th>
                        <td>
                            <textarea id="lead-assistant-goal" name="<?php echo esc_attr( self::OPTION_GOAL ); ?>" rows="4" class="large-text" placeholder="<?php echo esc_attr__( 'Example: Answer questions and capture qualified people interested in buying a property.', 'lead-assistant' ); ?>"><?php echo esc_textarea( self::goal() ); ?></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save configuration', 'lead-assistant' ) ); ?>
            </form>

            <hr>
            <h2><?php echo esc_html__( 'Shortcode', 'lead-assistant' ); ?></h2>
            <code>[lead_assistant]</code>
        </div>
        <?php
    }
}
