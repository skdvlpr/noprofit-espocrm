# Handoff: PrimaNota phone-match email/phone backfill

**Date:** 2026-09-05  
**Type:** critical bugfix (automation)  
**HEAD base:** `3ca35e45`

## State

`SubjectParty::backfillMissingChannels()` saved reused Account/Contact rows with `SaveOption::SKIP_ALL`. Espo `email` / `phone` fields are non-storable and persist only via afterSave FieldProcessing savers (`Hooks\Common\FieldProcessing`). `SKIP_ALL` skips those hooks, so a phone-match (or linked-party) backfill silently dropped the new email/phone.

## Files

- `custom/Espo/Modules/NonprofitEspocrm/Hooks/PrimaNota/SubjectParty.php`
- `tests/integration/Espo/Modules/NonprofitEspocrm/PrimaNotaTest.php`
- `bin/smoke-prima-nota-subject.php`

## Verification

- Static path: `RDBRepository::saveInternal` + `Hooks\Common\FieldProcessing` confirm `SKIP_ALL` skips afterSave savers.
- Added integration coverage for phone-match email persist and no-overwrite of an existing email.
- Runtime PHPUnit/DDEV not available in this cloud image; CI on the PR should run the new tests.

## Blockers

None for the data-loss fix. Nested create ACL (Volunteer without Contact create) remains a residual, same as closed unmerged #34/#36/#37.

## Next steps

- Merge this PR.
- Still open residuals: DropRetiredPartyTables unconditional DROP, Cancelled-slot revive, Case client-supplied `websiteReferenceId`, BugTracker HTML email injection, open drafts #54–#58.
