# Lead Assistant

AI chatbot and lead generation assistant for WordPress.

## Product principle

Lead Assistant is not a general-purpose chatbot. It is a focused conversational lead engine for WordPress: it understands the business, keeps conversations within scope, captures qualified leads, and stores the commercial context needed for follow-up.

## Product goals

- Three-step onboarding: connect AI, describe the business, choose the lead goal.
- No prompt engineering required for normal users.
- Conversation Governor keeps the assistant focused and redirects off-topic requests.
- Leads and conversations remain under WordPress administration.
- Core stays industry-agnostic; calculators and vertical behavior are added as actions/presets.
- Mobile-first public interface.

## Initial shortcode

`[lead_assistant]`

## Development AI connection

The current development build reads the OpenAI key server-side from either:

- `LEAD_ASSISTANT_OPENAI_API_KEY` in `wp-config.php`, or
- the `LEAD_ASSISTANT_OPENAI_API_KEY` server environment variable.

Optional model override:

- `LEAD_ASSISTANT_OPENAI_MODEL` in `wp-config.php`.

No API credential is sent to the browser or committed to the repository. A simplified secure onboarding UI will replace this development configuration path before the commercial release.

## Conversation runtime

The runtime uses the OpenAI Responses API with Structured Outputs. Every model turn returns an application-controlled structure:

- `scope`
- `reply`
- `follow_up_question`

WordPress owns conversation state. The browser stores only an opaque random session token; the database stores only its SHA-256 hash. The client never supplies conversation history to the AI runtime.

Valid prior turns are loaded server-side from WordPress. `OFF_TOPIC` exchanges remain stored for audit/history but are excluded from future model context, preserving the last valid business thread.

Public requests are length-bounded, origin-checked when a browser origin is present, and rate-limited before the AI provider is called.

## Qualified lead capture

The language model does not request or receive contact details. When the Governor classifies a conversation as `CONVERSION_READY`, the application may reveal a short contact form managed entirely by WordPress.

The current default form collects:

- name;
- email;
- phone;
- explicit privacy consent.

Contact data is attached to the existing opaque conversation session and is never included in the conversation context sent to the AI provider. Lead status progresses monotonically from `conversation` to `qualified` to `captured`.

The form links to the WordPress privacy-policy page when one is configured. This technical consent mechanism does not by itself claim legal or regulatory compliance; the site operator remains responsible for its privacy notices and processing basis.

## WordPress lead administration

WordPress administrators can open **Lead Assistant -> Leads** to review commercial opportunities without exposing ordinary unqualified chat sessions in the lead view.

The list includes `qualified` and `captured` sessions, contact details when available, last activity and privacy-consent state. Each lead has a read-only detail page with the complete stored conversation and Governor scopes.

The screen is restricted to users with `manage_options`. It does not yet provide CRM actions, deletion, export or contact-status workflows; those remain outside the 0.5.0 cut.

## Architecture rule

Core -> Conversation Governor -> Business configuration -> Actions -> Presets

The core must never contain mortgage-, real-estate-, insurance-, or other vertical-specific behavior.

## Status

Version `0.5.0` development cut: secure WordPress lead list and conversation detail. SmartFinance remains a reference implementation only; its vertical mortgage logic is not copied into the core.
