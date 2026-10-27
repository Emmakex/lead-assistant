# Lead Assistant 0.7.0 acceptance

These scenarios validate lead discovery and safe CSV export without changing the conversational runtime.

## A. Search

Create qualified/captured leads with distinguishable names, emails and phone numbers.

Expected:

- searching a partial name returns matching qualified/captured leads;
- searching a partial email returns matching qualified/captured leads;
- searching a partial phone returns matching qualified/captured leads;
- unqualified `conversation` sessions never appear even when their data would match.

## B. Status filter

Expected:

- `All` includes both `qualified` and `captured`;
- `Qualified` includes only `qualified`;
- `Captured` includes only `captured`;
- unsupported status values are ignored rather than interpolated into SQL.

## C. Activity-date filters

Set the WordPress site timezone to a non-UTC zone and create leads around a local midnight boundary.

Expected:

- `From` is inclusive from local 00:00 converted to UTC;
- `To` includes the complete selected local day and excludes the following local day;
- filters use `updated_at` / last activity;
- invalid calendar dates are ignored.

## D. Combined filters

Combine search, status and date range.

Expected:

- all active conditions are applied with AND semantics;
- result count matches the displayed rows across pagination;
- pagination preserves the active filters.

## E. Empty result

Use filters that match nothing.

Expected:

- page remains valid;
- count reports zero;
- table shows a no-results message;
- no unqualified session is exposed as a fallback.

## F. Filtered CSV parity

Apply a filter set, then choose `Export filtered CSV`.

Expected:

- export requires an administrator account and a valid nonce;
- exported rows match the same qualified/captured filter set as the screen;
- export does not include ordinary conversation sessions;
- export contains status, name, email, phone, created, last activity and privacy-consent fields.

## G. CSV streaming

Create more than 500 matching leads and export them.

Expected:

- rows are emitted in batches rather than requiring the entire export in memory;
- ordering remains newest activity first;
- each matching lead is exported once.

## H. UTF-8 compatibility

Use names containing accents and non-ASCII characters.

Expected:

- CSV is UTF-8;
- BOM is present for common spreadsheet compatibility;
- characters remain readable when opened in spreadsheet software.

## I. CSV formula injection

Store visitor-controlled values beginning with examples such as:

- `=1+1`
- `+SUM(1,1)`
- `-2+3`
- `@SUM(1,1)`

Expected:

- exported cell is prefixed as text before CSV encoding;
- spreadsheet software does not interpret the visitor value as a formula;
- ordinary values remain unchanged.

## J. Phone number preservation

Export a phone beginning with `+`.

Expected:

- it is neutralized as text, preserving the visible international phone number rather than allowing spreadsheet formula interpretation.

## K. Export permission boundary

Attempt the export action without `manage_options` or with an invalid/missing nonce.

Expected:

- export is rejected;
- no lead data is streamed.

## L. Runtime isolation

Expected:

- no public REST route changes in this cut;
- Conversation Governor behavior is unchanged;
- AI credentials, conversation state and lead-capture authorization are unchanged;
- this cut adds only admin-side discovery/export capabilities.
