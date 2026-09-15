# 015 — Implement 003: Google standalone, retire WF

**Date:** 2026-09-15  
**Agent:** Cursor Auto (`/speckit-tasks` + `/speckit-implement`)

## State

- `specs/003-google-standalone/tasks.md` written (T001–T024).
- Local DDEV `php command.php extension -l` reported **No extensions** (WF was
  a source tree, not an Extension Manager package). Source deleted so rsync
  cannot restore it.
- Google UI uses `google-integration:lib/template-variable-inserter` only.
- Template fields catalog = CalendarDateSource targets ∩ `scopes.*.entity`.
- Calendar pull preference list intersected with Metadata scopes.
- Production WF uninstall / git commit / push **not** done.

## Files

- Deleted: `custom/Espo/Modules/WorkflowEngine/`, `client/custom/modules/workflow-engine/`,
  WF unit/integration tests, `bin/smoke-workflow-engine.php`,
  `bin/packaging/WorkflowEngine-zip-AfterInstall.php`, `bin/build-workflow-engine.sh`
- Google inserter: `client/custom/modules/google-integration/src/lib/template-variable-inserter.js`
- Views: `google-calendar-description-template.js`, `google-calendar-opportunity-event-settings.js`
- PHP: `GoogleCalendarIntegrationTemplateFields.php`, `CalendarSyncRunner.php`, `Installer.php`
- AfterInstall: Nonprofit + `scripts/AfterInstall.php`
- Smoke: `bin/smoke-google-integration.php`
- Tests: `GoogleCalendarIntegrationTemplateFieldsTest.php`, `CoreCompatibilityTest.php`
- Checklists: `specs/003-google-standalone/checklists/owner-user-tests.md`

## Verification

- `rg nonprofit-espocrm: client/custom/modules/google-integration` → empty
- `test ! -d custom/Espo/Modules/WorkflowEngine` → PASS
- DDEV `php command.php rebuild` → Rebuild has been done
- PHPUnit unit: 116 tests OK (GoogleIntegration 40 OK)
- PHPStan: no errors
- `ddev exec php bin/smoke-google-integration.php` → ALL PASS
  (coupling inverted: nonprofit AMD is fail; Google inserter required)

## Blockers

- Owner UAT handshake open (U1–U7).
- Prod uninstall/deploy not approved.
- WF return from another instance = new specify, not restore of this tree.

## Next steps

1. Owner UAT Pass/Fail/Skip.
2. Ask before commit (constitution VIII).
3. After UAT: next audit rank F-003 App Secrets (F-002 cancelled).
