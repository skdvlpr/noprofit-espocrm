# GoogleIntegration Debug Analysis (2026-05-15)

## Scope

This document captures runtime-debug scope for GoogleIntegration in EspoCRM 9.3.6 without applying heavy functional fixes yet.

Primary sources of truth:
- EspoCRM docs and core behavior (`ExternalAccount`, `oauthCallback`, module client loading).
- Google OAuth behavior and `invalid_grant` troubleshooting.

## Current blockers to fix

1. Admin cannot reliably disable integration (`enabled = false`) without JS crash (`reading 'val'`) and state rollback.
2. Client credential rows (Client ID/Client Secret) visibility is unstable around enable/disable flow.
3. Re-login / reconnect fails with `Google OAuth: invalid_grant: Malformed auth code`.

## Runtime hypotheses (instrumented)

- `H1`: Admin save still reads stale `enabled` state from model, so save flow uses wrong field set.
- `H2`: Hidden credential fields are still fetched/validated in some branches, triggering null-field `.val()` path.
- `H3`: OAuth callback message handling runs more than once or race conditions occur between popup close and message event.
- `H4`: `redirect_uri` mismatch between authorize request, callback, and token exchange request.
- `H5`: Host/origin mismatch (Espo `siteUrl` vs actual URL) causes inconsistent callback/request flow.

## Instrumentation added (no secrets logged)

- `client/custom/modules/google-integration/src/views/admin/integrations/edit.js`
  - Logs `enabled` sync from checkbox, fields selected for save, fetchability of each field.
- `client/custom/modules/google-integration/src/views/external-account/oauth2.js`
  - Logs connect lock state, callback message receipt, code length, authorizationCode POST dispatch, origin mismatch ignores.
- `custom/Espo/Modules/GoogleIntegration/EntryPoints/OauthCallback.php`
  - Logs callback hit, code length, error presence.
- `custom/Espo/Modules/GoogleIntegration/Controllers/ExternalAccount.php`
  - Logs authorizationCode input shape and host mismatch branch.
- `custom/Espo/Modules/GoogleIntegration/Tools/OAuth/AuthorizationCodeHandler.php`
  - Logs resolved redirect URI and exchange start metadata.

Session log file:
- `/.cursor/debug-<session>.log`

## Candidate solution options (to apply only after runtime confirmation)

### Blocker 1 (admin disable/save)

Option A (preferred, minimal):
- Keep `syncEnabledFromView()` before determining fields-to-save.
- Persist only `enabled` when disabled.
- Never call `fetchToModel()` for hidden credential views.

Option B:
- Force explicit payload save (`model.save({enabled: false}, {patch: true})`) for disable action branch.
- Tradeoff: diverges from generic parent integration-save behavior.

### Blocker 2 (credential rows visibility)

Option A (preferred):
- Ensure `fieldDataList` is always populated from canonical metadata + credential defaults.
- Visibility controlled only by `enabled` and not by render timing side effects.

Option B:
- Recreate credential subviews on each enable/disable transition.
- Tradeoff: heavier re-render complexity.

### Blocker 3 (`Malformed auth code`)

Option A (preferred):
- Enforce single exchange attempt per connect click.
- Keep postMessage callback as single source, remove/guard any fallback duplicate triggers.
- Verify same canonical redirect URI is used by:
  - OAuth authorize request
  - callback page
  - token exchange payload

Option B:
- Add short-lived server-side dedupe guard by code fingerprint + account id.
- Tradeoff: extra state and failure modes if not designed carefully.

## Acceptance criteria after fix

- Disable in Admin succeeds without JS error; state persists after reload.
- Credential rows appear when `enabled = true` and hide when `enabled = false`.
- Connect/reconnect succeeds; no `Malformed auth code`; exactly one token exchange per connect attempt.
- End-to-end smoke passes: `ddev exec php bin/smoke-google-integration.php`.

## Notes

- No functional fix committed in this pass.
- Debug instrumentation remains active intentionally until post-fix verification is complete.
