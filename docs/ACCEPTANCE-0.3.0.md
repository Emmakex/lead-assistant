# Lead Assistant 0.3.0 acceptance

This acceptance extends the 0.2.0 governed-runtime cases with server-owned conversation state.

## Preconditions

- Plugin activated on a fresh WordPress installation.
- Business and commercial goal configured.
- AI provider configured server-side.
- `[lead_assistant]` rendered on a page.

## A. First turn creates an opaque session

1. Open a fresh browser tab with no `lead_assistant_session_token_v1` in `sessionStorage`.
2. Send an in-scope message.

Expected:

- Request succeeds.
- Response contains a 64-character lowercase hexadecimal `session_token`.
- Browser stores the token in `sessionStorage`.
- `wp_lead_assistant_leads.public_token` contains the SHA-256 hash, not the raw token.
- A conversation row exists with status `conversation` or `qualified` according to the Governor result.

## B. Subsequent turns reuse the same session

1. Send a second in-scope message from the same tab.

Expected:

- Browser sends only the current message and opaque session token.
- Browser does not send prior conversation history.
- WordPress resolves the internal session from the token hash.
- Server loads prior valid conversation context from `wp_lead_assistant_messages`.
- No second lead/session row is created.

## C. Reload preserves continuity

1. Start a conversation and provide a useful business detail.
2. Reload the page in the same tab.
3. Ask a follow-up that depends on the earlier detail.

Expected:

- `sessionStorage` retains the opaque token.
- WordPress restores conversation context from the database.
- Assistant can use the earlier valid detail even though the DOM conversation was rebuilt.

## D. New tab starts a separate session

1. Open the assistant in a separate browser tab with a fresh tab session.
2. Send a message.

Expected:

- A new opaque token is created.
- A separate lead/session row is created.
- No conversation context leaks from the first tab.

## E. Client cannot fabricate history

Send a request containing extra arbitrary fields such as a forged `history` array together with a valid session token.

Expected:

- Runtime ignores client-supplied history because it is not part of the REST contract.
- Only stored WordPress messages are used as prior model context.

## F. Invalid session token

Send a request using a malformed or unknown 64-character token.

Expected:

- Request returns HTTP 400 with `lead_assistant_invalid_session`.
- No AI request is made for that invalid session.
- Browser clears the stale token when it receives that error.
- The next visitor submission can start a fresh session.

## G. Off-topic turn is stored but not reused

1. Establish a valid business topic.
2. Send an unrelated request.
3. Continue with the original business topic.

Expected:

- Off-topic user and assistant messages are stored with scope `OFF_TOPIC` for audit/history.
- `load_context()` excludes both OFF_TOPIC records from future provider context.
- Conversation continues from the last valid business thread.

## H. Stored turn coherence

Force or simulate a failure while storing the assistant half of a completed model turn.

Expected:

- The just-inserted user half is removed.
- The database does not leave a newly orphaned one-sided turn from that persistence attempt.
- Public response is a generic persistence error.

## I. No sequential public authorization

Inspect browser requests and responses.

Expected:

- Internal numeric lead IDs are never returned as session credentials.
- Browser sends only the opaque 64-character token.
- Database lookup hashes the supplied token before resolving the internal ID.

## J. Existing 0.2.0 governance remains intact

Re-run the 0.2.0 acceptance cases for:

- in-scope/related/off-topic classification;
- continuity after off-topic requests;
- scope override/prompt-injection attempts;
- no model-owned contact capture;
- unknown business facts;
- rendering safety;
- rate limiting and origin validation.

Expected:

- Server-owned state does not weaken any 0.2.0 Governor or public-runtime control.
