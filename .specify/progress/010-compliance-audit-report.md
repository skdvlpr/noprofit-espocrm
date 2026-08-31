# Compliance Audit Report

**Created:** 2026-08-31  
**Feature:** `specs/001-custom-code-audit`  
**Auditor:** Cursor Auto (+ parallel explore subagents)  
**Espo docs clone SHA:** `ab2a5ee338be141ffe0d1b2c29ba742432bba089` (2026-08-31, already up to date)  
**Local runtime:** DDEV mandatory (`ddev exec`). This project’s DDEV describe
shows **Traefik** router + **nginx-fpm** in the web container (current DDEV
defaults). Production front door: **Caddy + automatic TLS**
(`crm.safehouse.community`). Constitution XVIII: local≈prod with **DDEV** as
the primary intentional difference; Caddy-on-both remains the product intent —
if local edge proxy differs from prod Caddy, treat as known env drift to
reconcile later, not as permission to use host PHP.  
**Scope:** NonprofitEspocrm, GoogleIntegration, WorkflowEngine, BugTracker, SafehouseAuroraThemes (+ matching client trees / theme CSS).

> **Secrets hygiene:** No raw secret values are included in this report — paths and kinds only.

## Executive summary

Custom modules do **not** hardcode secrets in git and do **not** patch Espo `application/` core. The highest-impact remediation themes are:

1. **Hard client AMD coupling** of “standalone” GoogleIntegration and WorkflowEngine into NonprofitEspocrm views (breaks stock-Espo installs of those ZIPs).
2. **Migrate plaintext config secrets** (`webPushVapidPrivateKey`, `safehouseCrmSyncToken`) to Espo **App Secrets**.
3. **Field-level ACL / export gating** for donor financial PII on `PrimaNota` (no custom `entityAcl` today).
4. **Performance:** PHP loop-counting in shift coverage sync on every invite save.

**Recommended next Spec Kit feature (backlog rank 1):**  
`Decouple GoogleIntegration/WorkflowEngine client from NonprofitEspocrm shared views`

## Methodology & coverage limits

- **Method:** Full module inventory + risk-based deep dive (secrets → ACL/PII → coupling → native-first/performance). Static analysis via ripgrep/file reads; parallel subagent reviews.
- **DDEV:** Project started for this session. Read-only probe via `ddev exec`
  confirmed: config keys `webPushVapidPrivateKey`, `safehouseCrmSyncToken`,
  `googleMapsApiKey` are **present(redacted)**; `AppSecret.count=0`; Roles
  present (`Admin`, `Volunteer`, `Member`, `Employee`, `Website`). Full
  Role `fieldData` matrix for PrimaNota fields remains a follow-up (F-016).
- **Not covered as deliverables:** `bin/` rewrite, CI redesign (cross-referenced only). Production SSH not performed.
- **Limits:** Not every layout/line audited; theme CSS under `client/custom/css/` inventoried as intentional theme exception.

## Security & secrets

### Secret location candidates

| ID | Kind | Location | in_git? | in_package? | Recommended store |
|----|------|----------|---------|-------------|-------------------|
| S-001 | WebPush VAPID private key | `data/config.php` key `webPushVapidPrivateKey` (written by `NonprofitEspocrm/Tools/WebPush/WebPushService.php`) | No | No | App Secrets |
| S-002 | Google OAuth client secret | Integration `GoogleCalendarDrive.clientSecret` (password field / DB) | No | No | Espo Integration store (acceptable); confirm at-rest handling |
| S-003 | Google OAuth tokens | ExternalAccount access/refresh tokens | No | No | Framework-managed (OK) |
| S-004 | CRM↔site sync bearer token | `data/config.php` key `safehouseCrmSyncToken` | No | No | App Secrets |
| S-005 | Google Maps browser API key | `data/config.php` `googleMapsApiKey` → client JS | No | No | Config OK; restrict key in Google Cloud Console |
| S-006 | WebPush subscription secrets | `WebPushSubscription` entity (DB) | No | No | Owner-scoped ACL |
| S-007 | Export pattern for integration settings | `integration-settings*.json` (gitignored; not on disk) | No | No | Keep ignored |

**Stripe secret/webhook keys:** none in CRM tree (by design — site-side). Pattern scan for `sk_live` / `whsec_` in non-vendor custom code: none.

**Positive:** Role auto-provision / RoleSetup removed from Installers; OAuth callback `postMessage` origin-scoped; deploy excludes `data/config.php`.

### ACL / PII narrative

Framework Integration/ExternalAccount handle OAuth secrets appropriately. Residual risk is **classification**: `PrimaNota` holds donor financial/contact fields with scope ACL + export enabled and **no** custom `entityAcl` field locks. Effective exposure depends on Role configuration in the DB (probe recommended). `Contact` marks tax/birth fields as personal data. No hardcoded secrets in git/packages found.

## Extension map (coupling)

| Module | Backend path | Frontend path | Coupling | Summary status |
|--------|--------------|---------------|----------|----------------|
| NonprofitEspocrm | `custom/Espo/Modules/NonprofitEspocrm/` | `client/custom/modules/nonprofit-espocrm/` | soft-depends (suite hub; guarded Google/Themes) | findings |
| GoogleIntegration | `custom/Espo/Modules/GoogleIntegration/` | `client/custom/modules/google-integration/` | soft backend / **hard client** → Nonprofit | findings |
| WorkflowEngine | `custom/Espo/Modules/WorkflowEngine/` | `client/custom/modules/workflow-engine/` | soft backend / **hard client** → Nonprofit | findings |
| BugTracker | `custom/Espo/Modules/BugTracker/` | `client/custom/modules/bug-tracker/` | standalone-safe | pass |
| SafehouseAuroraThemes | `custom/Espo/Modules/SafehouseAuroraThemes/` | CSS: `client/custom/css/safehouse-aurora/` (+ fonts) — **no** `client/custom/modules/…` | standalone-safe | findings (layout exception) |

## Per-module summaries

### NonprofitEspocrm — findings
Vertical CRM suite; AfterInstall orchestrates siblings via `class_exists`. Soft deps on GoogleIntegration calendar provisioner and default Safehouse Aurora theme (theme set unguarded). Owns shared client libs that other modules hard-import.

### GoogleIntegration — findings
Backend standalone-safe (core Meeting/Call/Task/Opportunity). Client field views hard-`define` `nonprofit-espocrm:lib/template-variable-inserter`. Manifest display name `GoogleCalendarDrive` ≠ folder `GoogleIntegration`. `acceptableVersions` still includes Espo 9.3.6+.

### WorkflowEngine — findings
Backend standalone-safe. Client `edit-action.js` hard-references `nonprofit-espocrm:views/fields/template-text`. Manifest requires Espo ≥10 / PHP ≥8.2 (stricter than suite).

### BugTracker — pass
No Nonprofit coupling found in PHP or client AMD.

### SafehouseAuroraThemes — findings
Standalone theme metadata; assets under `client/custom/css/safehouse-aurora/` (documented layout exception vs modules.md frontend path).

## Findings catalog

### F-001 — Decouple GoogleIntegration client from NonprofitEspocrm shared views

| Field | Value |
|-------|-------|
| Severity | High |
| Module | GoogleIntegration |
| Category | extension-boundary |
| Impact | Standalone GoogleIntegration ZIP cannot resolve AMD views on stock Espo; calendar template UI breaks |
| Evidence | `client/custom/modules/google-integration/src/views/fields/google-calendar-description-template.js`; `…/google-calendar-opportunity-event-settings.js`; `bin/build-google-integration.sh` |
| Expectation | Module JS must resolve within own module path; extensions must not hard-depend on vertical CRM for “universal” packages |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/modules.md` |
| Citation (online) | https://docs.espocrm.com/development/modules/ |
| Action type | rewrite |
| Backlog rank | 1 |

### F-002 — Decouple WorkflowEngine email modal from NonprofitEspocrm template-text view

| Field | Value |
|-------|-------|
| Severity | High |
| Module | WorkflowEngine |
| Category | extension-boundary |
| Impact | Standalone WorkflowEngine email-action modal fails without NonprofitEspocrm |
| Evidence | `client/custom/modules/workflow-engine/src/views/modals/edit-action.js` |
| Expectation | Same as F-001; package claimed standalone |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/modules.md` |
| Citation (online) | https://docs.espocrm.com/development/modules/ |
| Action type | rewrite |
| Backlog rank | 2 |

### F-003 — Migrate VAPID private key from config.php to App Secrets

| Field | Value |
|-------|-------|
| Severity | Medium |
| Module | NonprofitEspocrm |
| Category | secrets |
| Impact | Sensitive key at rest in plaintext config on server (git-excluded) |
| Evidence | `custom/Espo/Modules/NonprofitEspocrm/Tools/WebPush/WebPushService.php` (`webPushVapidPrivateKey`) |
| Expectation | Sensitive values prefer Administration → App Secrets |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/administration/app-secrets.md` |
| Citation (online) | https://docs.espocrm.com/administration/app-secrets/ |
| Action type | migrate-secret |
| Backlog rank | 3 |

### F-004 — Migrate safehouseCrmSyncToken to App Secrets

| Field | Value |
|-------|-------|
| Severity | Medium |
| Module | NonprofitEspocrm |
| Category | secrets |
| Impact | Bearer token to donation site stored in plaintext config |
| Evidence | `Tools/PrimaNota/StripeRefreshService.php`, `StripeBulkPullService.php` |
| Expectation | App Secrets |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/administration/app-secrets.md` |
| Citation (online) | https://docs.espocrm.com/administration/app-secrets/ |
| Action type | migrate-secret |
| Backlog rank | 4 |

### F-005 — Add entityAcl / export gating for PrimaNota donor financial PII

| Field | Value |
|-------|-------|
| Severity | Medium |
| Module | NonprofitEspocrm |
| Category | acl-pii |
| Impact | Card last4, billing emails/phones, Stripe customer ids exportable; no field-level locks in module metadata |
| Evidence | PrimaNota fields / scopes; no `Resources/metadata/entityAcl/PrimaNota.json` |
| Expectation | Least privilege via Roles + field-level / entityAcl |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/administration/roles-management.md`; `docs/development/acl.md` |
| Citation (online) | https://docs.espocrm.com/administration/roles-management/; https://docs.espocrm.com/development/acl/ |
| Action type | fix |
| Backlog rank | 5 |

### F-006 — Align suite manifest floors with WorkflowEngine/BugTracker (Espo 10 / PHP 8.2)

| Field | Value |
|-------|-------|
| Severity | Medium |
| Module | cross-cutting |
| Category | packaging |
| Impact | Suite ZIP validates on Espo 9.3.x while bundling modules requiring ≥10 |
| Evidence | `NonprofitEspocrm/manifest.json` vs `WorkflowEngine/manifest.json` / `BugTracker/manifest.json`; `bin/build.sh` |
| Expectation | Extension Manager validates top-level manifest only |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/extension-packages.md` |
| Citation (online) | https://docs.espocrm.com/development/extension-packages/ |
| Action type | fix |
| Backlog rank | 6 |

### F-007 — Replace PHP collection-counting in ShiftCoverageSyncService

| Field | Value |
|-------|-------|
| Severity | Medium |
| Module | NonprofitEspocrm |
| Category | performance |
| Impact | Per-invite afterSave reloads invite collections 3× per slot to count |
| Evidence | `Tools/ShiftCoverageSyncService.php`; `Hooks/ActivityInvite/SyncSlotCoverage.php` |
| Expectation | Prefer DB `count`/aggregates over hydrating collections |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/orm.md` |
| Citation (online) | https://docs.espocrm.com/development/orm/ |
| Action type | fix |
| Backlog rank | 7 |

### F-008 — Cache WorkflowDefinition lookups per request in WorkflowEngine trigger

| Field | Value |
|-------|-------|
| Severity | Medium |
| Module | WorkflowEngine |
| Category | performance |
| Impact | Common afterSave queries definitions on every entity save |
| Evidence | `Hooks/Common/WorkflowTrigger.php`; `Services/WorkflowRunner.php` |
| Expectation | Hooks should avoid repeated unbounded work; cache per request |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/hooks.md` |
| Citation (online) | https://docs.espocrm.com/development/hooks/ |
| Action type | fix |
| Backlog rank | 8 |

### F-009 — Bound contact inactivation on User delete

| Field | Value |
|-------|-------|
| Severity | Medium |
| Module | NonprofitEspocrm |
| Category | performance |
| Impact | Unbounded Contact find + save loop in afterRemove |
| Evidence | `Hooks/User/InactivateLinkedContacts.php` |
| Expectation | Use sth/limit/mass update or job |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/orm.md` |
| Citation (online) | https://docs.espocrm.com/development/orm/ |
| Action type | fix |
| Backlog rank | 9 |

### F-010 — Normalize unbounded finds in ShiftPlanningSupport / related services

| Field | Value |
|-------|-------|
| Severity | Medium |
| Module | NonprofitEspocrm |
| Category | performance |
| Impact | Per-team N+1 user loads; multiple unbounded finds |
| Evidence | `Tools/ShiftPlanning/ShiftPlanningSupport.php` (+ related services) |
| Expectation | limit/sth/id-only selects |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/orm.md` |
| Citation (online) | https://docs.espocrm.com/development/orm/ |
| Action type | fix |
| Backlog rank | 10 |

### F-011 — Document theme CSS path exception (SafehouseAuroraThemes)

| Field | Value |
|-------|-------|
| Severity | Low |
| Module | SafehouseAuroraThemes |
| Category | packaging |
| Impact | Assets outside `client/custom/modules/{hyphen}/` |
| Evidence | `client/custom/css/safehouse-aurora/`; theme metadata JSON |
| Expectation | Document intentional exception vs modules.md frontend layout |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/modules.md` |
| Citation (online) | https://docs.espocrm.com/development/modules/ |
| Action type | accept-risk |
| Backlog rank | 11 |

### F-012 — Guard Nonprofit default-theme provision if Themes module absent

| Field | Value |
|-------|-------|
| Severity | Low |
| Module | NonprofitEspocrm |
| Category | extension-boundary |
| Impact | Config may point at missing theme if Themes not installed |
| Evidence | `Tools/Installer.php` `provisionDefaultTheme` |
| Expectation | Detect sibling module before writing theme config |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/modules.md` |
| Citation (online) | https://docs.espocrm.com/development/modules/ |
| Action type | fix |
| Backlog rank | 12 |

### F-013 — Adopt App Secrets as standard for new secrets (governance)

| Field | Value |
|-------|-------|
| Severity | Low |
| Module | cross-cutting |
| Category | secrets |
| Impact | No custom code uses AppSecret/SecretProvider today |
| Evidence | Repo scan — only core AppSecret |
| Expectation | Prefer App Secrets for sensitive values |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/administration/app-secrets.md` |
| Citation (online) | https://docs.espocrm.com/administration/app-secrets/ |
| Action type | open-follow-on-spec |
| Backlog rank | 13 |

### F-014 — Harden bin/ smoke + builders (deferred)

| Field | Value |
|-------|-------|
| Severity | Low |
| Module | cross-cutting |
| Category | other |
| Impact | Large smoke/builder surface; deferred hygiene |
| Evidence | ~33 `bin/smoke-*.php`; 6 `bin/build*.sh`; `dist/` ZIPs present |
| Expectation | Proper tests long-term; builders gitignore policy (constitution IX/XIV) |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/tests.md` |
| Citation (online) | https://docs.espocrm.com/development/tests/ |
| Action type | open-follow-on-spec |
| Backlog rank | 14 |

### F-015 — Redact verbose Google OAuth failure response logging

| Field | Value |
|-------|-------|
| Severity | Low |
| Module | GoogleIntegration |
| Category | secrets |
| Impact | Full provider error bodies may hit logs |
| Evidence | `GoogleIntegration/Core/ExternalAccount/Clients/Google.php` `logTokenExchangeFailure` |
| Expectation | Minimize sensitive material in logs |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/development/acl.md` |
| Citation (online) | https://docs.espocrm.com/development/acl/ |
| Action type | fix |
| Backlog rank | 15 |

### F-016 — Blocked: verify Role fieldData / PrimaNota effective ACL in DB

| Field | Value |
|-------|-------|
| Severity | Medium |
| Module | NonprofitEspocrm |
| Category | acl-pii |
| Impact | Cannot prove least-privilege without Role rows |
| Evidence | Roles live in DB; static code only |
| Expectation | Confirm Roles after RoleSetup removal |
| Citation (local) | `~/safehouse/espocrm-documentation/docs/administration/roles-management.md` |
| Citation (online) | https://docs.espocrm.com/administration/roles-management/ |
| Action type | blocked-needs-prod |
| Backlog rank | — (partially probed on DDEV: AppSecret=0, Roles exist; fieldData matrix still open) |

## Ranked remediation backlog

| Rank | Finding | Proposed `/speckit-specify` title | Blocked? |
|------|---------|-----------------------------------|----------|
| 1 | F-001 | Decouple GoogleIntegration client from NonprofitEspocrm shared views | no |
| 2 | F-002 | Decouple WorkflowEngine email modal from NonprofitEspocrm template-text | no |
| 3 | F-003 | Migrate WebPush VAPID private key to App Secrets | no |
| 4 | F-004 | Migrate safehouseCrmSyncToken to App Secrets | no |
| 5 | F-005 | Add entityAcl and export gating for PrimaNota donor PII | no |
| 6 | F-006 | Raise suite extension manifest to Espo 10 / PHP 8.2 floors | no |
| 7 | F-007 | Replace ShiftCoverageSyncService PHP counting with repository count | no |
| 8 | F-008 | Cache WorkflowDefinition lookups per request in WorkflowEngine | no |
| 9 | F-009 | Bound User-delete Contact inactivation hook | no |
| 10 | F-010 | Normalize unbounded finds in shift planning services | no |
| 11 | F-011 | Document SafehouseAuroraThemes CSS path layout exception | no |
| 12 | F-012 | Guard Nonprofit Installer default theme when Themes absent | no |
| 13 | F-013 | Standardize new secrets on Espo App Secrets | no |
| 14 | F-014 | Tests and bin/ hygiene (smoke/builders policy) | no — deferred queued spec |
| 15 | F-015 | Redact Google OAuth failure response logging | no |

## Cross-references (out of scope deliverables)

- **Tests & `bin/` hygiene** — F-014; also ~17 ZIPs under `dist/` and committed builders (constitution IX policy).
- **Harden CI/CD** — deploy uses secrets env; review still a separate spec; no workflow edits this feature.
- **Production Caddy** — context only; no prod probe.

## Owner acceptance (SC-006)

- [ ] User confirms this report is sufficient to drive the next remediation priority.

**Suggested first specify after acceptance:** backlog rank 1 (F-001).
