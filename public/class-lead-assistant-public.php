<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_Public {
    public static function init(): void {
        add_shortcode( 'lead_assistant', array( __CLASS__, 'render_shortcode' ) );
    }

    public static function render_shortcode(): string {
        $ready = Lead_Assistant_Settings::is_configured() && Lead_Assistant_AI_Provider::is_configured();

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

        wp_localize_script(
            'lead-assistant',
            'LeadAssistantConfig',
            array(
                'endpoint' => esc_url_raw( rest_url( 'lead-assistant/v1/message' ) ),
                'ready'    => $ready,
                'strings'  => array(
                    'error'   => __( 'I could not respond right now. Please try again.', 'lead-assistant' ),
                    'sending' => __( 'Thinking…', 'lead-assistant' ),
                ),
            )
        );

        ob_start();
        ?>
        <section class="lead-assistant" data-lead-assistant>
            <div class="lead-assistant__card">
                <div class="lead-assistant__messages" aria-live="polite" aria-busy="false">
                    <div class="lead-assistant__message lead-assistant__message--assistant">
                        <?php
                        echo esc_html(
                            $ready
                                ? __( 'Hello. How can I help you today?', 'lead-assistant' )
                                : __( 'The assistant is temporarily unavailable.', 'lead-assistant' )
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
                        maxlength="1500"
                        <?php disabled( ! $ready ); ?>
                    >
                    <button type="submit" <?php disabled( ! $ready ); ?>>
                        <?php echo esc_html__( 'Send', 'lead-assistant' ); ?>
                    </button>
                </form>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
