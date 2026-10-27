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

WordPress owns conversation state. The browser stores only an opaque random session token; the database stores only its SHA-256 hash. The client no longer supplies conversation history to the AI runtime.

Valid prior turns are loaded server-side from WordPress. `OFF_TOPIC` exchanges remain stored for audit/history but are excluded from future model context, preserving the last valid business thread.

Public requests are length-bounded, origin-checked when a browser origin is present, and rate-limited before the AI provider is called.

## Architecture rule

Core -> Conversation Governor -> Business configuration -> Actions -> Presets

The core must never contain mortgage-, real-estate-, insurance-, or other vertical-specific behavior.

## Status

Version `0.3.0` development cut: server-owned conversation state. SmartFinance remains a reference implementation only; its vertical mortgage logic is not copied into the core.
