<?php
/**
 * Plugin Name: Lead Assistant
 * Plugin URI: https://github.com/Emmakex/lead-assistant
 * Description: Focused AI chatbot and lead generation assistant for WordPress.
 * Version: 0.6.0
 * Author: Financial Rocks
 * License: GPL-2.0-or-later
 * Text Domain: lead-assistant
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LEAD_ASSISTANT_VERSION', '0.6.0' );
define( 'LEAD_ASSISTANT_FILE', __FILE__ );
define( 'LEAD_ASSISTANT_PATH', plugin_dir_path( __FILE__ ) );
define( 'LEAD_ASSISTANT_URL', plugin_dir_url( __FILE__ ) );

require_once LEAD_ASSISTANT_PATH . 'includes/class-lead-assistant-activator.php';
require_once LEAD_ASSISTANT_PATH . 'includes/class-lead-assistant-credential-store.php';
require_once LEAD_ASSISTANT_PATH . 'includes/class-lead-assistant-settings.php';
require_once LEAD_ASSISTANT_PATH . 'includes/class-lead-assistant-conversation-governor.php';
require_once LEAD_ASSISTANT_PATH . 'includes/class-lead-assistant-conversation-store.php';
require_once LEAD_ASSISTANT_PATH . 'includes/class-lead-assistant-ai-provider.php';
require_once LEAD_ASSISTANT_PATH . 'includes/class-lead-assistant-rest-api.php';
require_once LEAD_ASSISTANT_PATH . 'admin/class-lead-assistant-admin-leads.php';
require_once LEAD_ASSISTANT_PATH . 'public/class-lead-assistant-public.php';

register_activation_hook( __FILE__, array( 'Lead_Assistant_Activator', 'activate' ) );

add_action( 'plugins_loaded', static function () {
    Lead_Assistant_Activator::maybe_upgrade();
    Lead_Assistant_Settings::init();
    Lead_Assistant_Admin_Leads::init();
    Lead_Assistant_REST_API::init();
    Lead_Assistant_Public::init();
} );
