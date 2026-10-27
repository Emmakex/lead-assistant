# Lead Assistant 0.4.0 acceptance

These scenarios validate application-owned qualified lead capture.

## A. No contact form before qualification

Start a new conversation and exchange normal in-scope messages.

Expected:

- contact form remains hidden while the Governor state is not `CONVERSION_READY`;
- the model does not ask for email, phone or postal address;
- no contact fields are sent to the AI provider.

## B. Qualified conversation reveals the form

Drive the configured business conversation to a genuine commercial opportunity.

Expected:

- the governed response returns `CONVERSION_READY`;
- WordPress persists the lead status as `qualified`;
- the browser reveals the WordPress-rendered contact form;
- the form contains name, email, phone and privacy consent;
- the contact form is separate from the assistant's generated text.

## C. Unqualified session cannot capture contact data

Call `POST /wp-json/lead-assistant/v1/lead` with a valid session token whose lead status is still `conversation`.

Expected:

- request is rejected with HTTP 409;
- contact fields are not written;
- lead status is unchanged.

## D. Session authorization

Submit the capture endpoint with a missing, malformed or unknown session token.

Expected:

- request is rejected;
- no sequential internal database ID is accepted as authorization;
- AI provider is not involved in contact capture.

## E. Contact validation

For a qualified session, test separately:

- missing name;
- malformed email;
- missing phone.

Expected:

- each invalid submission is rejected with HTTP 400;
- no partial contact record is stored.

## F. Explicit privacy consent

Submit otherwise-valid contact details with `privacy_consent=false`.

Expected:

- request is rejected with HTTP 400;
- consent flag/timestamp remain unset;
- lead remains `qualified` rather than becoming `captured`.

Then submit with consent enabled.

Expected:

- `consent_privacy=1`;
- `consent_at` is stored server-side;
- lead status becomes `captured`.

## G. Privacy-policy link

When WordPress has a privacy-policy page configured:

Expected:

- capture form links to that page;
- link opens safely with `noopener noreferrer`.

When no privacy-policy page is configured:

Expected:

- form still requires an explicit consent statement;
- plugin does not claim regulatory compliance.

## H. Contact data isolation from AI

Capture a lead, then continue the conversation.

Expected:

- name/email/phone remain stored in the lead row;
- server-owned model context contains only persisted conversation messages and business configuration;
- contact details are not appended to conversation messages;
- contact details are not sent to the AI provider.

## I. Monotonic lead state

After a lead reaches `qualified`, continue with an ordinary `IN_SCOPE` turn.

Expected:

- state does not downgrade to `conversation`.

After contact capture, continue with any allowed governed turn.

Expected:

- state remains `captured`.

## J. Repeated capture is idempotent

Submit the capture endpoint again after the lead is already `captured`.

Expected:

- request returns success without creating a new lead row;
- existing session identity is preserved.

## K. Capture rate limit

Send repeated capture requests from the same source beyond the configured capture threshold.

Expected:

- capture endpoint eventually returns HTTP 429;
- message and capture rate-limit buckets are separate;
- raw IP address is not stored by the plugin's rate-limit state.

## L. Browser-storage fallback

Block `sessionStorage`, start a conversation, reach qualification and submit the form without reloading the page.

Expected:

- the opaque token remains available through the in-memory fallback;
- conversation and capture continue to use the same server-owned session;
- a page reload may start a new session when browser storage is unavailable.

## M. Database migration

Upgrade an existing 0.3.0 installation without deactivating/reactivating the plugin.

Expected:

- `consent_privacy` and `consent_at` are added through the versioned migration;
- existing lead/message rows remain intact;
- `lead_assistant_db_version` advances to `0.4.0`.
