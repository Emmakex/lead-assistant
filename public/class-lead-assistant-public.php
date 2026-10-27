<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lead_Assistant_Public {
    private static int $instance = 0;

    public static function init(): void {
        add_shortcode( 'lead_assistant', array( __CLASS__, 'render_shortcode' ) );
    }

    public static function render_shortcode(): string {
        self::$instance++;

        $ready       = Lead_Assistant_Settings::is_configured() && Lead_Assistant_AI_Provider::is_configured();
        $privacy_url = get_privacy_policy_url();
        $suffix      = (string) self::$instance;

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
                'endpoint'     => esc_url_raw( rest_url( 'lead-assistant/v1/message' ) ),
                'leadEndpoint' => esc_url_raw( rest_url( 'lead-assistant/v1/lead' ) ),
                'ready'        => $ready,
                'strings'      => array(
                    'error'        => __( 'I could not respond right now. Please try again.', 'lead-assistant' ),
                    'sending'      => __( 'Thinking…', 'lead-assistant' ),
                    'captureError' => __( 'We could not save your contact details. Please check the form and try again.', 'lead-assistant' ),
                    'captureDone'  => __( 'Thank you. Your contact details have been saved.', 'lead-assistant' ),
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
                    <label class="screen-reader-text" for="lead-assistant-message-<?php echo esc_attr( $suffix ); ?>">
                        <?php echo esc_html__( 'Message', 'lead-assistant' ); ?>
                    </label>
                    <input
                        id="lead-assistant-message-<?php echo esc_attr( $suffix ); ?>"
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

                <div class="lead-assistant__capture" data-lead-assistant-capture hidden>
                    <p class="lead-assistant__capture-title">
                        <?php echo esc_html__( 'Would you like us to contact you?', 'lead-assistant' ); ?>
                    </p>
                    <form class="lead-assistant__capture-form" data-lead-assistant-capture-form>
                        <label for="lead-assistant-name-<?php echo esc_attr( $suffix ); ?>">
                            <?php echo esc_html__( 'Name', 'lead-assistant' ); ?>
                            <input id="lead-assistant-name-<?php echo esc_attr( $suffix ); ?>" name="name" type="text" autocomplete="name" maxlength="120" required>
                        </label>

                        <label for="lead-assistant-email-<?php echo esc_attr( $suffix ); ?>">
                            <?php echo esc_html__( 'Email', 'lead-assistant' ); ?>
                            <input id="lead-assistant-email-<?php echo esc_attr( $suffix ); ?>" name="email" type="email" autocomplete="email" maxlength="190" required>
                        </label>

                        <label for="lead-assistant-phone-<?php echo esc_attr( $suffix ); ?>">
                            <?php echo esc_html__( 'Phone', 'lead-assistant' ); ?>
                            <input id="lead-assistant-phone-<?php echo esc_attr( $suffix ); ?>" name="phone" type="tel" autocomplete="tel" maxlength="60" required>
                        </label>

                        <label class="lead-assistant__consent">
                            <input name="privacy_consent" type="checkbox" value="1" required>
                            <span>
                                <?php if ( $privacy_url ) : ?>
                                    <?php
                                    printf(
                                        wp_kses(
                                            __( 'I have read and accept the <a href="%s" target="_blank" rel="noopener noreferrer">privacy policy</a>.', 'lead-assistant' ),
                                            array(
                                                'a' => array(
                                                    'href'   => array(),
                                                    'target' => array(),
                                                    'rel'    => array(),
                                                ),
                                            )
                                        ),
                                        esc_url( $privacy_url )
                                    );
                                    ?>
                                <?php else : ?>
                                    <?php echo esc_html__( 'I consent to the processing of my contact details so the business can respond to my enquiry.', 'lead-assistant' ); ?>
                                <?php endif; ?>
                            </span>
                        </label>

                        <button type="submit" class="lead-assistant__capture-submit">
                            <?php echo esc_html__( 'Send my details', 'lead-assistant' ); ?>
                        </button>
                        <p class="lead-assistant__capture-status" data-lead-assistant-capture-status aria-live="polite"></p>
                    </form>
                </div>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
