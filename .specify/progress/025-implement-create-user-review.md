# 025 — Implement 004.2: create-user review modal

**Date:** 2026-09-18  
**Agent:** Cursor Auto (`/speckit-implement`)

## State

- Feature `004.2-create-user-review` implemented on **local DDEV**.
- Write surface: `custom/` and `client/custom/` only. **No** `application/`
  or vendored Espo edits.
- 004.1 UAT not accepted. Owner closes **004 + 004.1 + 004.2** after this
  UAT Pass. Then 003 Google/WF U1–U6.
- Production `crm.safehouse.community` copy/rebuild/rsync **not** done.
- Live volunteer access mail **not** sent.
- Git commit / push **not** done.

## Local data

- `kokshse196@proton.me`: only `semen.koksharov` (`6a779c1a499e559ca`,
  2026-08-08) remains. Today-created duplicates already removed.

## Files changed

- JS: `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js`
  (review after Save, create-only checkbox, hide linkedUser, admin type
  filter, omit password, rollback Contact on User fail);
  `edit-small.js`; `views/modals/create-crm-user.js` (stash only);
  `views/user/record/create-from-contact.js` (no password UI, invite-link
  label).
- PHP: `Tools/UserEmailUniqueness.php`;
  `Classes/FieldValidators/User/EmailAddress/UniqueAmongUsers.php`;
  `Classes/DuplicateWhereBuilders/User.php`;
  `Hooks/Contact/RestrictPersonnelTypeToAdmin.php`;
  `Hooks/Contact/SyncOccasionalToUser.php` (no-op copy);
  `Tools/UserContactProfileSync.php` (no profile write-back);
  `Classes/FieldProcessing/User/ContactProfileLoader.php`.
- Metadata: `entityDefs/User.json` (profile `notStorable`+`readOnly`,
  email unique validator); `recordDefs/User.json` (duplicate where-builder).
- i18n Contact/User en/it/ru: invite label, needs-email, unique-email,
  tooltips (review-after-save; User profile is Contact reflection).
- Tests: `UserEmailUniqueTest`, `RestrictPersonnelTypeToAdminTest`,
  `CreateCrmUserPayloadOmitPasswordTest`; unit+integration
  `UserContactProfileSyncTest`; `ContactHooksTest` occasional via loader.
- Specs: `checklists/owner-user-tests.md`; this progress file.

## Path

Code. Formula cannot intercept Save, unique User email, admin-only enum
filter, or the review modal.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md

## Verification

- Rebuild: `Rebuild has been done.`
- Cron gate: `scheduled_job` all Inactive (66), `job` count 0.
- PHPUnit `tests/unit/Espo/Modules/NonprofitEspocrm`: **111 tests, 238
  assertions**, OK.
- PHPStan (level 5) on new/changed 004.2 PHP: clean.
- Isolated integration PHPUnit: test-install TypeError in core
  `HookManager::readHookData` (`get_class_methods` on a non-class string)
  before assertions. Same harness issue as skipping full integration in
  004.1; unit tests cover uniqueness, admin restriction, and no
  write-back.
- Git push, production rebuild/copy, live volunteer mail: **not** done.

## Blockers

- Owner UAT V1–V10 on DDEV. PHPUnit does not close the feature.
- Production apply and live volunteer mail are Skip until named.

## Next steps

1. Owner runs `specs/004.2-create-user-review/checklists/owner-user-tests.md`
   V1–V10; report Pass/Fail/Skip.
2. Optional: Cursor-browser walkthrough if the owner agrees.
3. Pass → close 004 **and** 004.1 **and** 004.2; then 003 U1–U6.
4. Ask before git commit; never push unless asked.
