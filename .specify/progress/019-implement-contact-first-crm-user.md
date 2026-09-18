# 019 — Implement 004: Contact-first volunteer/employee CRM user

**Date:** 2026-09-16  
**Agent:** Cursor Auto (`/speckit-implement`, current model)

## State

- Feature `004-contact-first-crm-user` implemented on **local DDEV**.
- Owner 2026-09-16: wipe local records, dump production MariaDB, import into
  DDEV, then code + competence copy + User `notStorable`. Same sequence is
  documented for production in
  `specs/004-contact-first-crm-user/contracts/competence-migration.md`.
- Production `crm.safehouse.community` copy/rebuild/rsync **not** done.
- Git commit / push **not** done.
- 003 owner UAT (`specs/003-google-standalone/checklists/owner-user-tests.md`
  U1–U6) still required **after 004 closes**.

## Local data (after dump)

- Users 20, Contacts 39, Contacts with competences 9.
- User `activity_competences` leftover column still present after soft
  rebuild (values retained). Contact column populated by copy (`copied=14`
  on first apply while User field was still storable).
- `siteUrl` = `https://nonprofit-espocrm.ddev.site` (config.php, not a
  `settings` table).
- Dump file: `_local/safehouse-espocrm-prod.sql.gz` (gitignored).
- After rebuild, Espo re-activated scheduled jobs including Google Calendar
  Sync / Overlay and push reminders. Re-inactivated all scheduled jobs and
  truncated `job` so DDEV cron cannot hit production Google/email/push.

## Files changed (module + specs)

- Metadata: Contact `activityCompetences` (storable) + `createCrmUser`;
  User `sourceContactId`, `linkedContact`, `activityCompetences` notStorable;
  recordDefs User `__APPEND__` loader; clientDefs Contact edit view +
  Dynamic Logic; layouts Contact/User; i18n en/it/ru; consoleCommands.
- PHP: `UserContactProfileSync`, `ContactActivityCompetences`,
  `CopyUserActivityCompetences`, `ShiftPlanningSupport`,
  `ShiftPlanningInstaller`, `InactivateLinkedContacts` (cite only).
- JS: `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js`
- Tests: `UserContactProfileSyncTest.php`, `ContactActivityCompetencesTest.php`
- Specs: tasks marked `[x]`; competence-migration prod runbook; quickstart V5;
  `checklists/owner-user-tests.md`

## Verification

- PHPUnit `tests/unit/Espo/Modules/NonprofitEspocrm`: **90 tests, 188 assertions**, OK.
- PHPStan (level 5) on `ContactActivityCompetences`, `CopyUserActivityCompetences`,
  `UserContactProfileSync`: clean.
- Copy leftover rehearsal: nulled one Contact list, `copy --apply` restored
  `["MealPreparation","MealDistribution"]` from leftover User column
  (`copied=1`, `skippedEmpty=13`).
- Copy reads leftover User column via `Util::toUnderScore` when ORM is empty
  **and** Contact is empty. Does not overwrite a non-empty Contact.
- Planner `getUserCompetences` → Contact only (does not read leftover User).

## Blockers

- Owner UAT U1–U6 for 004 (local DDEV). PHPUnit does not close handshake.
- Production apply is Skip until the owner names that exact action.
- Do **not** `rebuild --hard` on production before copy (leftover column).

## Next steps

1. Owner runs 004 `owner-user-tests.md` U1–U6 on DDEV; report Pass/Fail/Skip.
2. Optional: Cursor-browser walkthrough of U1–U5 if the owner agrees.
3. After 004 UAT: 003 Google/WF U1–U6.
4. When owner names production: backup → deploy → **soft** rebuild →
   `copyUserActivityCompetences` then `--apply` → confirm Contact lists →
   optional `--hard` later.
5. Ask before git commit; never push unless asked.
