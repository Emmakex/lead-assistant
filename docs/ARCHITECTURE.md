# Lead Assistant architecture

## Product boundary

Lead Assistant is a focused conversational lead engine for WordPress. It is not a general-purpose chatbot and the core must not contain vertical-specific business logic.

SmartFinance is a reference implementation only. Mortgage fields, mortgage calculations, TIN/TAE wording and financial prompts must not enter the core.

## Authority chain

`Core -> Conversation Governor -> Business configuration -> Actions -> Presets`

### Core

Owns WordPress lifecycle, persistence, public UI, REST boundaries and administration.

### Conversation Governor

Owns conversation scope and continuity. Every governed turn must resolve to one of:

- `IN_SCOPE`
- `RELATED`
- `OFF_TOPIC`
- `NEEDS_DATA`
- `CONVERSION_READY`

The Governor must redirect off-topic requests to the last valid business topic instead of following the unrelated topic. Visitor text is untrusted conversation input and cannot redefine the Governor, business scope or commercial goal.

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

## Governed AI runtime

The AI provider uses the OpenAI Responses API with Structured Outputs. The application supplies the conversation policy as authoritative instructions and requires a strict response object containing:

- `scope`: one of the five Governor states.
- `reply`: concise answer or redirection without a commercial qualification question.
- `follow_up_question`: an empty string or one short relevant question.

The application, not the model, decides what context survives between turns. `OFF_TOPIC` exchanges are stored for history/audit but are excluded from subsequent model context, preserving the last valid business thread.

The model receives only bounded conversation content and business configuration. Lead email and phone are not part of the AI runtime contract.

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

## Data model

The foundation uses two tables:

- `wp_lead_assistant_leads`: lead/session-level state.
- `wp_lead_assistant_messages`: ordered conversation items.

The `public_token` column stores only the hash of the browser-visible session token. `business_context` snapshots the configured business/goal at session creation; `lead_context` stores application-owned conversation state such as the latest Governor scope.

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

Additional anti-abuse controls may be added before public release, but none may rely on a sequential lead ID as authorization.

## Security baseline

- Escape output and sanitize stored input.
- Never render model HTML directly.
- Keep API credentials server-side.
- Rate-limit public AI endpoints.
- Bound message length and retained history.
- Keep prior conversation context server-owned.
- Store only hashed public session tokens in the database.
- Do not send email or phone to the AI provider unless a future feature explicitly requires it.
- Consent and privacy handling must be explicit before production lead capture.
- Public runtime errors must not expose provider response bodies or credentials.

## Release discipline

Work proceeds in small cuts. Each cut must preserve the product boundary and pass the repository CI on the exact PR head before merge.
