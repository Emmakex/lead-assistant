=== Lead Assistant ===
Contributors: emmakex
Tags: ai chatbot, lead generation, lead capture, sales assistant, chatbot
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Focused AI chatbot and lead generation assistant for WordPress.

== Description ==

Lead Assistant is being built as a simple, focused conversational lead engine for WordPress.

The normal setup is intentionally small: connect the AI provider, describe the business, define the commercial goal and add the shortcode `[lead_assistant]`.

The assistant is governed by a conversation policy designed to keep discussions focused on the configured business instead of becoming a general-purpose chatbot.

When the conversation is qualified, WordPress can reveal a short contact form. The AI model does not request or receive the visitor's email or phone.

Administrators can review qualified/captured leads and their stored conversation from Lead Assistant -> Leads.

This repository is currently in development and is not yet a production release.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Activate Lead Assistant.
3. Open Lead Assistant -> Settings.
4. Paste an OpenAI API key and click Connect AI.
5. Describe the business and define the commercial goal.
6. Configure a WordPress privacy-policy page if the site will capture contact details.
7. Add `[lead_assistant]` to a page.
8. Review qualified/captured opportunities under Lead Assistant -> Leads.

Advanced installations may provide `LEAD_ASSISTANT_OPENAI_API_KEY` from `wp-config.php` or the server environment instead. Server configuration takes priority over a WordPress-stored credential.

== Changelog ==

= 0.6.0 =
* Added simple Connect AI onboarding in WordPress settings.
* Validates an API key server-side before storage.
* Confirms availability of the configured model through the provider Models API.
* Encrypts WordPress-stored API credentials using site-derived security material.
* Never pre-fills or returns the stored plaintext key to the browser.
* Preserves wp-config.php/environment credentials as the advanced server-side override.
* Added disconnect/replace connection controls with administrator capability and nonce checks.
* Added CI guards preventing credential identifiers from entering public browser code.

= 0.5.0 =
* Added a WordPress admin Leads screen for qualified and captured conversations.
* Added paginated lead listing with contact, activity and consent information.
* Added read-only lead detail with the complete stored conversation and Governor scopes.
* Restricted lead administration to users with `manage_options`.
* Kept unqualified chat sessions out of the Leads view.

= 0.4.0 =
* Added application-owned contact capture for qualified conversations.
* Added required privacy consent and consent timestamp storage.
* Added automatic database migration for new lead fields.
* Kept contact data outside AI conversation context.
* Added separate rate limiting for contact capture.
* Preserved monotonic lead states from conversation to qualified to captured.

= 0.3.0 =
* Moved conversation history authority from the browser to WordPress.
* Added opaque random session tokens with only SHA-256 hashes stored in the database.
* Added server-side loading of valid conversation context.
* Kept off-topic turns stored but excluded from future model context.
* Added session continuity across page reloads within the browser tab.

= 0.2.0 =
* Added governed OpenAI Responses API runtime.
* Added Structured Outputs for scope, reply and one optional follow-up question.
* Added bounded conversation context and off-topic context exclusion.
* Added public endpoint rate limiting and origin validation.
* Added JavaScript syntax validation to CI.

= 0.1.0 =
* Initial foundation.
* Business and goal configuration.
* Conversation Governor contract.
* Lead/message persistence foundation.
* Responsive shortcode shell.
