=== Lead Assistant ===
Contributors: emmakex
Tags: ai chatbot, lead generation, lead capture, sales assistant, chatbot
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Focused AI chatbot and lead generation assistant for WordPress.

== Description ==

Lead Assistant is being built as a simple, focused conversational lead engine for WordPress.

The normal setup is intentionally small: connect the AI provider, describe the business, define the commercial goal and add the shortcode `[lead_assistant]`.

The assistant is governed by a conversation policy designed to keep discussions focused on the configured business instead of becoming a general-purpose chatbot.

This repository is currently in development and is not yet a production release.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Activate Lead Assistant.
3. For this development build, define `LEAD_ASSISTANT_OPENAI_API_KEY` in `wp-config.php` or as a server environment variable.
4. Open Lead Assistant in WordPress administration.
5. Describe the business and the commercial goal.
6. Add `[lead_assistant]` to a page.

== Changelog ==

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
