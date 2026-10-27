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

The Governor must redirect off-topic requests to the last valid business topic instead of following the unrelated topic.

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

## Data model

The foundation uses two tables:

- `wp_lead_assistant_leads`: lead/session-level state.
- `wp_lead_assistant_messages`: ordered conversation items.

Public updates must use an unguessable token or equivalent server-controlled session credential. Sequential database IDs must never be treated as public authorization.

## AI integration direction

The new product must use the current OpenAI Responses API rather than inheriting SmartFinance's legacy Chat Completions integration. Conversation orchestration remains application-owned so Lead Assistant can preserve only the context it actually needs.

No API key may be committed to the repository or emitted to the browser.

## Security baseline

- Escape output and sanitize stored input.
- Never render model HTML directly.
- Keep API credentials server-side.
- Rate-limit public AI endpoints before public release.
- Bound message length and retained history.
- Do not send email or phone to the AI provider unless a future feature explicitly requires it.
- Consent and privacy handling must be explicit before production lead capture.

## Release discipline

Work proceeds in small cuts. Each cut must preserve the product boundary and pass the repository CI on the exact PR head before merge.
