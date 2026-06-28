# GoogleIntegration — master debug prompt (handoff to strong model)

Copy everything below the line into a new agent session. Repo: `/home/skoksharov/nonprofit-espocrm`, Espo **9.3.6**, DDEV `https://nonprofit-espocrm.ddev.site`.

**Last user report:** 2026-05-15 — after rebuild + cache clear; menu loads again but **critical blockers remain**.

---

## Role

You are a senior EspoCRM 9.x extension developer. **Stabilize GoogleIntegration end-to-end.** Read **`AGENTS.md`** (SECTION 26 EXT-*, PKG-*, API-REST) before coding. User language: Russian; code/specs: English.

## Notion (mandatory when MCP is available — AGENTS.md protocol)

**Do not create local markdown “paste into Notion” files.** Use Notion MCP only.

When `plugin-notion-workspace-notion` is connected:

1. Fetch **active** project + task pages from `AGENTS.md` NOTION section (Projects DB + Tasks DB). Archive only: https://www.notion.so/34e8d469d4058027af82f2ce986a6448 (Safehouse CRM Phase 1 — read-only).
2. Append executor log and deploy notes — never overwrite prior entries.
3. Update task status (In progress / Blocked / Done); do not close OAuth tasks until blockers below are fixed.
4. If MCP is unavailable, **skip Notion** and tell the user — do not substitute repo files.

**Git branch:** `feat/google/calendar-and-drive` (not pushed unless user asks).

## Hard constraints

- No `git push` / PR unless user explicitly asks.
- After metadata changes: `ddev exec php clear_cache.php && ddev exec php rebuild.php` + **hard refresh** (Ctrl+Shift+R).
- Frontend canonical: `client/custom/modules/google-integration/` — metadata MUST use `google-integration:views/...` (never bare `views/...`).
- Canonical redirect URI: `rtrim(siteUrl,'/') + '/?entryPoint=oauthCallback'`.
- Verify fixes in **browser Network tab** (correct `edit.js` / `oauth2.js` URL and cache-bust `?r=` timestamp).

## Environment

| Item | Value |
| ---- | ----- |
| siteUrl | `https://nonprofit-espocrm.ddev.site` |
| Module | `custom/Espo/Modules/GoogleIntegration/` |
| Integration id | `GoogleIntegration` |
| Smokes | `ddev exec php bin/smoke-google-integration.php` |
| Logs | `data/logs/espo-YYYY-MM-DD.log` |
| Config flag | `data/config.php` → `'integrations' => (object) ['GoogleIntegration' => true]` — may stay **true** if admin save never persists |

---

## BLOCKER symptoms (user-verified, must all be fixed)

### BLOCKER-1 — Cannot disable integration in Admin (HIGHEST PRIORITY)

**User report:** Uncheck **Abilitato** → **Salva** → still fails; integration **remains enabled** (“всегда включена”). Cannot turn off Google integration instance-wide.

**Observed UI:** Admin → Integrazioni → Google (Calendario e Drive); Redirect URI visible; Client ID / Secret rows often missing until Abilitato checked.

**Console (still reproduces after first save fix attempt):**

```
Uncaught TypeError: Cannot read properties of null (reading 'val')
  at n.fetch (espo-main.js:19793)
  at o.fetchToModel (espo-main.js:18536)
  at edit.js → GoogleIntegrationAdminEditView.save
```

**Root cause (confirmed in code review):** `getFieldsForSave()` used `this.model.get('enabled')` **before** reading the checkbox. User unchecks Abilitato in UI but model still has `enabled: true` → save still tries `fetchToModel()` on hidden `clientId` / `clientSecret` password views → null `.val()` crash → **no PUT to server** → DB + `data/config.php` `integrations.GoogleIntegration` stay enabled.

**Attempted fix (may be incomplete — verify in browser):** `syncEnabledFromView()` before `getFieldsForSave()` in `client/custom/modules/google-integration/src/views/admin/integrations/edit.js`. If user still sees error, either fix not deployed (cache) or needs stronger approach (e.g. `model.set('enabled', enabledView.fetch(), {ui:true})` then save only changed attrs).

**Acceptance:**

1. Abilitato **unchecked** → Salva → no console error → success toast.
2. Reload page → Abilitato stays unchecked.
3. `integration` table row `enabled = 0` (or false).
4. `data/config.php` → `integrations.GoogleIntegration` becomes **false** after save (Espo syncs on Integration update).
5. Personal External Account: Connect should be blocked or show integration disabled when admin off.

---

### BLOCKER-2 — Client ID / Client Secret not shown in Admin (regression vs pre-calendarSyncMode)

**User report:** “строк с client data нету (были до calendarSyncMode)”.

**Expected:** Check **Abilitato** → rows for Client ID, Client Secret + Redirect URI (oauth2.tpl `{{#each fieldDataList}}`).

**Check:**

- `fieldDataList` populated in `edit.js` from `CREDENTIAL_FIELD_DEFS` + metadata fields.
- `syncCredentialFieldsVisibility()` / `super.afterRender()` not leaving fields permanently hidden.
- Template `client/res/templates/admin/integrations/oauth2.tpl` — placeholders `{{{var name ../this}}}`.
- Stale duplicate: `client/custom/res/templates/external-account/google-integration-oauth2.tpl` (orphan; not admin — remove if confusing).

**Acceptance:** With Abilitato **checked**, user can enter and save Client ID + Secret.

---

### BLOCKER-3 — OAuth Connect: `invalid_grant: Malformed auth code` (UNCHANGED)

**User report:** Personal → Account Esterni → Connettersi → **Internal server error** / `Google OAuth: invalid_grant: Malformed auth code`.

**Works:** External account page opens; disconnect + save works.

**Log pattern:**

```
ERROR: Google OAuth token exchange failed. HTTP 400 — invalid_grant: Malformed auth code.
— redirect_uri=https://nonprofit-espocrm.ddev.site?entryPoint=oauthCallback
```

(Historical wrong URI: `.../oauth-callback.php`.)

**Client must:**

- Load `client/custom/modules/google-integration/src/views/external-account/oauth2.js` (**not** stock `espo-extra.js` `connect`).
- postMessage from `EntryPoints/OauthCallback.php` (`type: googleIntegrationOAuthCallback`).
- `encodeURIComponent` on authorize query (not `encodeURI`).
- Single `POST ExternalAccount/action/authorizationCode` per Connect click.

**Server:**

- `Tools/OAuth/RedirectUri.php` — slash: `/?entryPoint=oauthCallback`
- `AuthorizationCodeHandler.php`, `Controllers/ExternalAccount.php`, `Core/ExternalAccount/Clients/Google.php`

**Google Console:** exactly `https://nonprofit-espocrm.ddev.site/?entryPoint=oauthCallback`

**Acceptance:** Connect → Connected; no Malformed auth code in log; one token exchange line per attempt.

---

## Secondary / fixed symptoms (do not regress)

| Status | Symptom |
| ------ | ------- |
| FIXED | Admin → Integrazioni blank + spinner (metadata used bare `views/...` → AMD 404) |
| FIXED | Wrong client path claim (`client/custom/src` only) — use `client/custom/modules/google-integration/` |
| OPEN | Extension noise in console (`message channel closed`) — ignore unless blocking |
| OPEN | `calendarSyncMode` UI on external account when connected (not blocking OAuth fix) |

---

## Regression timeline

1. **Before calendarSyncMode:** OAuth connect worked at least once; admin had client credential fields.
2. **calendarSyncMode work:** duplicate client trees, wrong metadata view IDs, stock popup OAuth, broken templates.
3. **Session fixes:** metadata → `google-integration:views/...`; ZIP includes client module; removed `client/custom/src` view overrides.
4. **Still broken (user 2026-05-15):** admin save/disable, credentials visibility, OAuth malformed grant.

---

## Architecture (do not regress)

```
custom/Espo/Modules/GoogleIntegration/          ← PHP, metadata, hooks, EntryPoints
client/custom/modules/google-integration/         ← ONLY runtime client
```

| Metadata key | Correct value |
| ------------ | ------------- |
| `view` | `google-integration:views/admin/integrations/edit` |
| `userView` | `google-integration:views/external-account/oauth2` |

Loader: bare `views/foo` → `client/lib/transpiled/src/...` → **404**.

---

## Full acceptance criteria

### Admin disable (BLOCKER-1)

- [ ] Uncheck Abilitato → Salva → success, no `reading 'val'`.
- [ ] Reload → still disabled.
- [ ] config `integrations.GoogleIntegration` = false.

### Admin enable + credentials (BLOCKER-2)

- [ ] Check Abilitato → Client ID, Client Secret visible.
- [ ] Save credentials → Saved.

### OAuth (BLOCKER-3)

- [ ] Network: module `oauth2.js` 200.
- [ ] Authorize URL has encoded `redirect_uri` with `%2F%3FentryPoint%3D`.
- [ ] postMessage → one authorizationCode POST → Connected.

### Automated

- [ ] `ddev exec php bin/smoke-google-integration.php` ALL PASS

---

## Debug workflow

1. Reproduce BLOCKER-1 with DevTools open → note exact `edit.js?r=` URL and line number in stack.
2. Confirm `syncEnabledFromView` exists in loaded file; if not → cache.
3. `ddev exec tail -f data/logs/espo-$(date +%Y-%m-%d).log` during one Connect (BLOCKER-3).
4. Compare stock `views/admin/integrations/edit` save in `client/lib/original/espo-admin.js`.
5. Fix → rebuild → cache → hard refresh → re-test all blockers.

---

## Key files

| Area | Path |
| ---- | ---- |
| Integration metadata | `Resources/metadata/integrations/GoogleIntegration.json` |
| Admin edit view | `client/custom/modules/google-integration/src/views/admin/integrations/edit.js` |
| User oauth2 view | `client/custom/modules/google-integration/src/views/external-account/oauth2.js` |
| User template | `client/custom/modules/google-integration/res/templates/external-account/oauth2.tpl` |
| Admin template (stock) | `client/res/templates/admin/integrations/oauth2.tpl` |
| OAuth callback | `EntryPoints/OauthCallback.php` |
| Redirect URI | `Tools/OAuth/RedirectUri.php` |
| Token exchange | `Tools/OAuth/AuthorizationCodeHandler.php` |
| Google client | `Core/ExternalAccount/Clients/Google.php` |
| ExternalAccount API | `Controllers/ExternalAccount.php` |
| Debug / handoff | this file |
| Agent rules | `AGENTS.md` |
| Transcript | `4cd34018-86d8-431c-b12d-e5fbf6576755` |

---

## Suggested fix order

1. **BLOCKER-1** — admin save when disabling (sync enabled from view first; then fetch only allowed fields; verify PUT + config flag).
2. **BLOCKER-2** — credential fields visibility when enabled.
3. **BLOCKER-3** — OAuth postMessage + redirect_uri parity + no double exchange.
4. Remove orphan `client/custom/res/templates/...` if present.
5. Full acceptance + smokes.

---

*End of master prompt.*
