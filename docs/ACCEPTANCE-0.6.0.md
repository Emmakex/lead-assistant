# Lead Assistant 0.6.0 acceptance

These scenarios validate the simple server-side AI connection flow.

## A. First-time setup

With no server override and no stored credential, open **Lead Assistant -> Settings**.

Expected:

- status is `Not connected`;
- an empty password-style API-key field is shown;
- the key field is never pre-filled;
- business description and commercial goal remain the only normal configuration fields after AI connection.

## B. Valid API key

Submit a valid API key as an administrator.

Expected:

- request is nonce-protected and requires `manage_options`;
- validation happens server-side through the provider Models API;
- the configured model must be listed as available to the credential;
- only after successful validation is the credential stored;
- redirect returns a success notice;
- subsequent settings views show `Connected` without revealing any part of the plaintext key.

## C. Invalid authentication

Submit an invalid or revoked API key.

Expected:

- provider authentication failure is shown as a generic admin error;
- plaintext key is not persisted;
- provider response bodies are not exposed;
- settings remain `Not connected` when there was no prior credential.

## D. Valid key without required model

Use a credential that authenticates but does not expose the model configured by Lead Assistant.

Expected:

- connection is rejected before persistence;
- administrator sees that the required model is unavailable;
- no conversational inference request is generated merely to validate the key.

## E. Encrypted database storage

After a successful WordPress-managed connection, inspect the option named `lead_assistant_openai_credential`.

Expected:

- the stored option is a JSON envelope containing an algorithm marker and encoded ciphertext metadata;
- the plaintext API key does not appear in the option value;
- the option is not autoloaded for every WordPress request;
- runtime decryption occurs only in `Lead_Assistant_Credential_Store`.

## F. Encryption backends

On a server with libsodium available:

Expected:

- credential is stored with `alg=secretbox`.

On a server without libsodium but with OpenSSL AES-256-GCM support:

Expected:

- credential is stored with `alg=aes-256-gcm`.

On a server with neither secure backend:

Expected:

- valid key is not persisted in plaintext;
- administrator receives a storage error and must use server configuration instead.

## G. WordPress salt rotation

Connect a WordPress-managed key, then change the WordPress authentication salts.

Expected:

- old encrypted credential cannot be decrypted with the new salts;
- Lead Assistant behaves as not connected rather than returning corrupted credential data;
- administrator can reconnect a new key and replace the unreadable payload.

## H. Server override precedence

Define `LEAD_ASSISTANT_OPENAI_API_KEY` in `wp-config.php` or the server environment.

Expected:

- settings show that the connection is supplied by server configuration;
- WordPress key input/disconnect controls are not offered for the active connection;
- runtime uses the server credential even if an older encrypted WordPress option exists;
- no server credential value is rendered in HTML.

## I. Disconnect stored credential

With a WordPress-managed connection and no server override, choose **Disconnect AI**.

Expected:

- action is nonce-protected and administrator-only;
- encrypted option is deleted;
- settings return to `Not connected`;
- public assistant becomes unavailable until another valid credential is connected.

## J. Replace stored credential

With a WordPress-managed connection, enter a different valid key and choose **Replace connection**.

Expected:

- new credential is validated before replacement;
- failed validation leaves the existing stored credential untouched;
- successful validation replaces the encrypted payload;
- old plaintext/new plaintext are never shown back in the UI.

## K. Browser boundary

Inspect public page HTML, localized JavaScript configuration and files under `public/`.

Expected:

- no API key is present;
- no encrypted credential payload is present;
- no credential option identifier is present;
- the browser only receives normal assistant runtime endpoints and UI strings.

## L. Capability and CSRF boundary

Attempt connect/disconnect actions without `manage_options` or with an invalid/missing nonce.

Expected:

- action is rejected;
- stored credential is unchanged.

## M. Existing advanced configuration compatibility

Keep the existing server-side constants/environment configuration from a pre-0.6.0 installation and update the plugin.

Expected:

- no migration is required for the key;
- existing server configuration remains the active credential authority;
- existing conversation, lead and admin functionality continues unchanged.
