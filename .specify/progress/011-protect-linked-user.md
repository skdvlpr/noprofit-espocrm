# 011 — ProtectLinkedUser identity-bind guard

**Date:** 2026-09-03
**Agent:** Cursor automation (critical-bug-investigation-2561)

## State

HEAD on `main` was still `3ca35e45` (docs-only since 2026-09-01). Open drafts #54 (OAuth refresh), #55 (Covered-slot), #56 (Stripe lock) were not duplicated.

Shipped the next everyday-trigger hole: Volunteer/Employee can create own Contacts and set `linkedUser` / `portalUser` to another account. `ProtectLinkedUser` BeforeSave blocks foreign binds for non-admin actors.

## Files changed

- `custom/Espo/Modules/NonprofitEspocrm/Hooks/Contact/ProtectLinkedUser.php` (new)
- `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/{en_US,it_IT,ru_RU}/Contact.json`
- `tests/unit/Espo/Modules/NonprofitEspocrm/ProtectLinkedUserTest.php` (new)
- `tests/integration/Espo/Modules/NonprofitEspocrm/ContactHooksTest.php`

Did **not** recreate `Tools/RoleSetup.php` (removed on HEAD). Hook is the durable guard.

## Verification

- Contact i18n JSON parsed.
- Host `php` / `ddev` unavailable in this cloud run; unit + integration tests added for CI.
- Existing admin Contact hook tests still bind `linkedUser` (admin skip path).

## Blockers

- Runtime PHPUnit not executed here (no DDEV/PHP).

## Next steps

- Merge after CI green; rebuild + clear cache on deploy.
- Remaining residuals (do not duplicate open PRs): DropRetiredPartyTables live-row check, Cancelled-slot revive, Case client-supplied `websiteReferenceId`, BugTracker HTML email injection, export Totale, InboundEmail `caseTypeDefault` without normalize.
