# Lead Assistant 0.5.0 acceptance

These scenarios validate the first read-only WordPress administration surface for qualified leads.

## A. Menu structure

With an administrator account:

Expected:

- top-level `Lead Assistant` menu exists;
- `Settings` submenu opens the existing business/goal configuration;
- `Leads` submenu opens the lead list.

## B. Capability boundary

Attempt to open the Leads page as a user without `manage_options`.

Expected:

- access is denied;
- contact data and conversation content are not rendered.

## C. Lead-list scope

Create three stored sessions with statuses:

- `conversation`;
- `qualified`;
- `captured`.

Expected:

- `qualified` appears in Leads;
- `captured` appears in Leads;
- ordinary `conversation` does not appear in the Leads view.

## D. Contact data presentation

For a captured lead:

Expected list columns include:

- status;
- name;
- email;
- phone;
- last activity;
- privacy-consent timestamp;
- View action.

For a qualified lead without captured contact data:

Expected:

- missing contact values render as an em dash;
- consent renders as not captured;
- the lead remains reviewable because the conversation itself is commercially qualified.

## E. Conversation detail

Open a lead detail page.

Expected:

- lead summary is shown;
- all persisted messages for that lead are shown in stored order;
- each message shows role, Governor scope when available and timestamp;
- message content is escaped as text rather than interpreted as HTML.

## F. Off-topic audit visibility

Open a lead whose conversation contains an `OFF_TOPIC` exchange.

Expected:

- the exchange is visible in the admin transcript for audit/history;
- this does not change the runtime rule that off-topic messages are excluded from future AI context.

## G. Lead isolation

Manually change the `lead_id` query parameter to another existing qualified/captured lead.

Expected:

- an administrator may view that lead because the screen is an authenticated internal administration surface;
- public opaque session tokens are not used or exposed in the admin URL;
- no public REST authorization rule is weakened by the admin view.

Change the ID to an unqualified or nonexistent session.

Expected:

- the page returns `Lead not found` rather than exposing that session through the Leads view.

## H. Pagination

Create more than 20 qualified/captured leads.

Expected:

- list paginates at 20 rows per page;
- Previous/Next navigation keeps the `lead-assistant-leads` page slug;
- page navigation does not expose raw SQL or invalid parameters.

## I. Time display

Expected:

- stored UTC timestamps are displayed using the WordPress site date/time configuration;
- consent and activity timestamps remain distinguishable.

## J. Read-only boundary

Expected:

- 0.5.0 exposes no delete, edit, status-change, export, CRM or webhook action from the Leads screen;
- no nonce-requiring mutation is hidden behind a GET action;
- this cut is inspection-only.
