# Lead Assistant 0.8.0-rc1 acceptance

This cut validates packaging and installability of the existing product. It does not add new public runtime features.

## A. Release metadata

1. `lead-assistant.php` header version is `0.8.0-rc1`.
2. `LEAD_ASSISTANT_VERSION` is `0.8.0-rc1`.
3. `readme.txt` stable tag is `0.8.0-rc1`.
4. A mismatch between any of the three fails the release smoke.

## B. ZIP structure

1. Run `bash scripts/build-release.sh`.
2. Confirm the output is `build/lead-assistant-0.8.0-rc1.zip`.
3. Confirm the archive has exactly one top-level plugin directory: `lead-assistant/`.
4. Confirm these paths exist in the archive:
   - `lead-assistant/lead-assistant.php`
   - `lead-assistant/readme.txt`
   - `lead-assistant/includes/`
   - `lead-assistant/admin/`
   - `lead-assistant/public/`
5. Confirm the archive does not contain `.git`, `.github`, `docs`, `scripts`, `build`, `README.md`, logs or `.DS_Store` files.

## C. Package smoke

1. Run `bash scripts/smoke-release.sh` after building.
2. All packaged PHP files must pass `php -l`.
3. All packaged public JavaScript files must pass `node --check`.
4. The packaged tree must not contain a potential committed API credential.
5. CI `release-package` must succeed and upload `lead-assistant-release-candidate`.

## D. Clean WordPress installation

On a clean WordPress installation compatible with the plugin requirements:

1. Install the generated ZIP through Plugins -> Add New -> Upload Plugin.
2. Activation completes without a fatal error.
3. `Lead Assistant` appears in the WordPress administration menu.
4. `Lead Assistant -> Settings` loads.
5. `Lead Assistant -> Leads` loads and shows no qualified leads on a fresh installation.
6. A page containing `[lead_assistant]` renders the assistant shell.
7. Without an AI connection, the public composer remains unavailable and the UI does not expose credentials or provider errors.

## E. AI connection acceptance

Using a disposable test API credential supplied through the WordPress settings UI:

1. Invalid credentials are rejected and are not stored.
2. Valid credentials are accepted only when the configured model is available to the credential.
3. The saved plaintext credential is never rendered back into the settings input.
4. Public page source and browser JavaScript do not contain the credential or credential option identifiers.
5. Disconnect removes the WordPress-stored credential.
6. A server-provided credential continues to override WordPress storage when configured.

Never use or commit a production credential for acceptance testing.

## F. Conversation Governor acceptance

Configure a simple test business and goal, then verify:

1. An in-scope question receives a useful in-scope answer.
2. A related/adjacent question may be answered briefly while preserving business focus.
3. An unrelated request is classified `OFF_TOPIC`, politely redirected, and does not become future model context.
4. A prompt-injection attempt asking to ignore policy does not override the Governor.
5. After an off-topic turn, a follow-up referring to the prior valid business topic continues correctly.
6. No response contains more than one commercial qualification question.
7. The model does not request name, email or phone directly.

## G. Session and persistence acceptance

1. The first successful message creates a server-owned session and returns an opaque 64-character token.
2. Only the token hash is stored in the lead table.
3. Reloading the same browser tab preserves conversation continuity when session storage is available.
4. A new tab creates an independent session.
5. Browser-supplied fake conversation history cannot alter server-owned context.
6. `OFF_TOPIC` messages remain visible in stored history but are excluded from later AI context.

## H. Qualified lead capture acceptance

1. A `CONVERSION_READY` turn changes the application state to qualified.
2. WordPress reveals the contact form; the model does not ask for contact data.
3. Name, valid email, phone and privacy consent are required by the application flow.
4. Capture without explicit privacy consent is rejected.
5. Captured contact data is stored on the existing session and never appended to AI message context.
6. Lead state progresses monotonically: `conversation -> qualified -> captured`.
7. Later messages cannot downgrade a qualified or captured lead.

## I. Administration and export acceptance

1. Only users with `manage_options` can access lead review/export.
2. The Leads view shows only `qualified` and `captured` sessions.
3. Search finds matching name, email or phone values.
4. Status filtering works for all/qualified/captured.
5. Date filters use the configured WordPress site timezone.
6. Pagination preserves active filters.
7. CSV export contains the same filtered result set.
8. CSV export requires a valid admin nonce.
9. Visitor-controlled values beginning with spreadsheet formula characters are neutralized.
10. Lead detail renders the complete stored transcript escaped as text.

## J. Responsive acceptance

Verify shortcode and capture UI at representative viewport widths:

- approximately 360 px phone;
- approximately 768 px tablet;
- approximately 1024 px small desktop;
- approximately 1440 px desktop.

At each width:

1. No horizontal overflow is introduced by the plugin.
2. Message text wraps inside the assistant card.
3. Composer input and button remain inside the card.
4. Contact fields remain usable and do not overlap.
5. Focus indicators remain visible for keyboard navigation.

## Exit criterion

`0.8.0-rc1` may be merged only when the exact PR head passes:

- PHP 8.0 lint;
- PHP 8.3 lint;
- frontend JavaScript syntax;
- repository guard;
- release-package build + smoke.

The generated CI ZIP is then the canonical candidate for manual WordPress acceptance. Any product/runtime defect found during manual acceptance is fixed in a separate RC commit and revalidated from a new exact head.
