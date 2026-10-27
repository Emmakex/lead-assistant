# Lead Assistant architecture

## Product boundary

Lead Assistant is a focused conversational lead engine for WordPress. It is not a general-purpose chatbot and the core must not contain vertical-specific business logic.

SmartFinance is a reference implementation only. Mortgage fields, mortgage calculations, TIN/TAE wording and financial prompts must not enter the core.

## Authority chain

`Core -> Conversation Governor -> Business configuration -> Actions -> Presets`

### Core

Owns WordPress lifecycle, persistence, public UI, REST boundaries, lead capture and administration.

### Conversation Governor

Owns conversation scope and continuity. Every governed turn must resolve to one of:

- `IN_SCOPE`
- `RELATED`
- `OFF_TOPIC`
- `NEEDS_DATA`
- `CONVERSION_READY`

The Governor must redirect off-topic requests to the last valid business topic instead of following the unrelated topic. Visitor text is untrusted conversation input and cannot redefine the Governor, business scope or commercial goal.

`CONVERSION_READY` is a signal to the application. It does not authorize the model to request personal contact details itself.

### Business configuration

The normal administrator should only need to provide:

1. What the business does.
2. What the assistant should achieve.
3. Which lead fields are required.

Prompt engineering is an implementation detail and must not be required for standard setup.

### Actions

Actions are deterministic application capabilities. Examples include calculators, compatibility checks, quote estimators or CRM handoff. The model may request an action, but application code owns execution and validation.

### Presets

Presets configure a vertical without changing core authority. Examples may include mortgage, real estate, insurance or quote flows.

## Conversation principles

- Stay within the configured business scope.
- Answer adjacent questions briefly when useful, then return to the business context.
- Do not follow unrelated topics.
- Ask at most one commercial qualification question per reply.
- Preserve known facts and do not ask for the same information repeatedly.
- Never invent prices, calculations, availability, legal conclusions or business facts.
- Deterministic calculations happen in actions, not in the language model.
- Requests to reveal or override hidden policy do not supersede the Governor.
- Personal contact details are collected by application UI, not by the model.

## Governed AI runtime

The AI provider uses the OpenAI Responses API with Structured Outputs. The application supplies the conversation policy as authoritative instructions and requires a strict response object containing:

- `scope`: one of the five Governor states.
- `reply`: concise answer or redirection without a commercial qualification question.
- `follow_up_question`: an empty string or one short relevant question.

The application, not the model, decides what context survives between turns. `OFF_TOPIC` exchanges are stored for history/audit but are excluded from subsequent model context, preserving the last valid business thread.

The model receives only bounded conversation content and business configuration. Lead name, email, phone and consent are not part of the AI runtime contract.

## Conversation state authority

WordPress is the sole authority for conversation history. The browser never supplies prior message history to the model runtime.

On the first successful conversation request, WordPress creates a session/lead row and returns a cryptographically random 64-character token to the browser. The browser keeps this token in `sessionStorage`; the database stores only `SHA-256(token)` in `public_token`.

For subsequent turns:

1. the browser submits the opaque token and current message;
2. WordPress hashes the token and resolves the internal lead ID;
3. WordPress loads the latest valid stored context;
4. the provider receives that server-owned context plus the new user message;
5. the resulting user/assistant turn is persisted with its Governor scope.

Sequential database IDs are never exposed as public authorization.

## Qualified lead capture

Lead capture is application-owned and only attaches to an existing server-owned conversation session.

The default 0.4.0 flow is:

1. a governed turn returns `CONVERSION_READY`;
2. WordPress persists the lead state as `qualified`;
3. the browser reveals the WordPress-rendered contact form;
4. the visitor submits name, email, phone and explicit privacy consent with the opaque session token;
5. WordPress validates the token, verifies the session is qualified, sanitizes the contact data and stores it locally;
6. the lead progresses to `captured` and the consent timestamp is stored.

Lead state is monotonic: `conversation -> qualified -> captured`. Later conversation turns must not downgrade a qualified or captured lead.

Contact data is never appended to the model conversation history and is not sent to the AI provider.

The public capture endpoint is separately rate-limited and origin-checked. A sequential database ID is never accepted as public authorization.

The contact form links to the WordPress privacy-policy page when configured. This technical mechanism does not by itself assert legal compliance; site operators remain responsible for their notices, lawful basis and retention policies.

## Data model

The foundation uses two tables:

- `wp_lead_assistant_leads`: lead/session-level state.
- `wp_lead_assistant_messages`: ordered conversation items.

The `public_token` column stores only the hash of the browser-visible session token. `business_context` snapshots the configured business/goal at session creation; `lead_context` stores application-owned conversation state such as the latest Governor scope.

Lead rows also store contact fields and explicit privacy-consent state/timestamp after capture. Schema changes are applied through versioned `dbDelta` migration so plugin updates do not depend on deactivate/reactivate cycles.

## AI integration direction

The product uses the current OpenAI Responses API rather than inheriting SmartFinance's legacy Chat Completions integration. Conversation orchestration remains application-owned so Lead Assistant can preserve only the context it actually needs.

During development, the API key is read server-side from `LEAD_ASSISTANT_OPENAI_API_KEY` in `wp-config.php` or the server environment. It is never emitted to the browser.

## Public runtime boundary

The public AI endpoint is intentionally unauthenticated because site visitors are anonymous. WordPress REST nonces are therefore not treated as authorization. Before invoking the provider, the endpoint applies:

- business/AI readiness checks;
- same-site browser-origin validation when an Origin header is present;
- per-IP hashed rate limiting without storing the raw IP;
- message-length limits;
- server-owned bounded conversation history;
- opaque session-token resolution;
- server-side sanitization.

The public lead-capture endpoint additionally requires a valid qualified conversation session and explicit privacy consent before storing contact details.

Additional anti-abuse controls may be added before public release, but none may rely on a sequential lead ID as authorization.

## Security baseline

- Escape output and sanitize stored input.
- Never render model HTML directly.
- Keep API credentials server-side.
- Rate-limit public AI and capture endpoints.
- Bound message length and retained history.
- Keep prior conversation context server-owned.
- Store only hashed public session tokens in the database.
- Keep lead contact data outside AI provider context.
- Require explicit privacy consent before contact capture.
- Public runtime errors must not expose provider response bodies or credentials.

## Release discipline

Work proceeds in small cuts. Each cut must preserve the product boundary and pass the repository CI on the exact PR head before merge.
