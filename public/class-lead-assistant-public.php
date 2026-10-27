<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_Public {
    public static function init(): void {
        add_shortcode( 'lead_assistant', array( __CLASS__, 'render_shortcode' ) );
    }

    public static function render_shortcode(): string {
        wp_enqueue_style(
            'lead-assistant',
            LEAD_ASSISTANT_URL . 'public/css/lead-assistant.css',
            array(),
            LEAD_ASSISTANT_VERSION
        );

        wp_enqueue_script(
            'lead-assistant',
            LEAD_ASSISTANT_URL . 'public/js/lead-assistant.js',
            array(),
            LEAD_ASSISTANT_VERSION,
            true
        );

        ob_start();
        ?>
        <section class="lead-assistant" data-lead-assistant>
            <div class="lead-assistant__card">
                <div class="lead-assistant__messages" aria-live="polite">
                    <div class="lead-assistant__message lead-assistant__message--assistant">
                        <?php
                        echo esc_html(
                            Lead_Assistant_Settings::is_configured()
                                ? __( 'Hello. How can I help you today?', 'lead-assistant' )
                                : __( 'Lead Assistant needs to be configured by the site administrator.', 'lead-assistant' )
                        );
                        ?>
                    </div>
                </div>

                <form class="lead-assistant__composer" data-lead-assistant-form>
                    <label class="screen-reader-text" for="lead-assistant-message">
                        <?php echo esc_html__( 'Message', 'lead-assistant' ); ?>
                    </label>
                    <input
                        id="lead-assistant-message"
                        type="text"
                        placeholder="<?php echo esc_attr__( 'Write your message…', 'lead-assistant' ); ?>"
                        autocomplete="off"
                        <?php disabled( ! Lead_Assistant_Settings::is_configured() ); ?>
                    >
                    <button type="submit" <?php disabled( ! Lead_Assistant_Settings::is_configured() ); ?>>
                        <?php echo esc_html__( 'Send', 'lead-assistant' ); ?>
                    </button>
                </form>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
