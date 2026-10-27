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

## Simple AI connection

The standard WordPress setup is intentionally small:

1. Open **Lead Assistant -> Settings**.
2. Paste an OpenAI API key and choose **Connect AI**.
3. Describe the business and define the commercial goal.
4. Add `[lead_assistant]` to a page.

The submitted key is validated server-side before it is stored. Validation uses the OpenAI Models API to confirm authentication and availability of the configured model without generating a conversational response.

For the standard WordPress path, the key is encrypted before being stored in the database using a key derived from the site's WordPress security salts. The plaintext key is never displayed again and is never emitted to public JavaScript.

Advanced installations may still provide `LEAD_ASSISTANT_OPENAI_API_KEY` through `wp-config.php` or the server environment. A server-provided key takes priority over a WordPress-stored credential. `LEAD_ASSISTANT_OPENAI_MODEL` remains available as an advanced server-side model override.

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

Leads can be searched by name, email or phone and filtered by status and WordPress-local activity date. The same filtered result can be exported as a UTF-8 CSV. CSV cells beginning with spreadsheet formula characters are neutralized before export.

The screen is restricted to users with `manage_options`. It still does not provide CRM actions, deletion or contact-status workflows.

## Release candidate packaging

The installable ZIP is built from an explicit allowlist instead of copying the repository wholesale. Only runtime distribution files are packaged:

- `lead-assistant.php`
- `readme.txt`
- `includes/`
- `admin/`
- `public/`

Run locally with:

```bash
bash scripts/build-release.sh
bash scripts/smoke-release.sh
```

CI also builds and smoke-tests the package and uploads it as the `lead-assistant-release-candidate` artifact. The smoke validates the single `lead-assistant/` plugin root, matching version metadata, PHP/JavaScript syntax and the absence of development-only files or potential committed API credentials.

## Architecture rule

Core -> Conversation Governor -> Business configuration -> Actions -> Presets

The core must never contain mortgage-, real-estate-, insurance-, or other vertical-specific behavior.

## Status

Version `0.8.0-rc1`: first installable release candidate. The focus of this cut is packaging and acceptance of the existing product, not new runtime functionality. SmartFinance remains a reference implementation only; its vertical mortgage logic is not copied into the core.
