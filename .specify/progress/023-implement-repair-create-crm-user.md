# 023 — Implement 004.1: repair Contact-first CRM user create

**Date:** 2026-09-17  
**Agent:** Cursor Auto (`/speckit-implement`, current model; T011/T012 Launch was not named)

## State

- Feature `004.1-repair-create-crm-user` implemented on **local DDEV**.
- Parent `004-contact-first-crm-user` create-User UAT remains Failed until this
  list Pass. Owner closes **004 and 004.1 together** after 004.1 UAT Pass.
- 003 Google/WF owner UAT still required **after those two close**.
- Production `crm.safehouse.community` copy/rebuild/rsync **not** done.
- Live volunteer access mail **not** sent.
- Git commit / push **not** done.

## Files changed

- Layouts: Contact `edit.json` (checkbox), `detail.json` (checkbox removed,
  keep `linkedUser`), `detailSmall.json` (checkbox); `app.layouts` maps Contact
  `edit`; `createCrmUser` `layoutDetailDisabled`.
- Dynamic Logic: Volunteer/Employee **and** `linkedUserId` empty.
- JS: Contact `record/edit.js` + `edit-small.js` (drawer + POST User after
  Contact id); `modals/create-crm-user.js` (stash, no User POST);
  `user/record/create-from-contact.js` (lock identity, sendAccessInfo);
  role-profile mixin sets `hasEmployeeRole`.
- PHP: `ContactUserChannelSync`, hooks `SyncLinkedUserChannels` /
  `SyncLinkedContactChannels`; `TemplateHelpers/SafehouseLogo`.
- Templates: `accessInfo` and `passwordChangeLink` en_US / it_IT / ru_RU
  (no `{{password}}`); `app.templates` module NonprofitEspocrm.
- Tests: `ContactUserChannelSyncTest.php`. Handshake
  `UserContactProfileSync::linkFromSourceContact` unchanged.
- Specs: `checklists/owner-user-tests.md`; this progress file.

## Verification

- Rebuild: `Rebuild has been done.`
- Cron gate: `scheduled_job` all Inactive (52), `job` count 0.
- PHPUnit `tests/unit/Espo/Modules/NonprofitEspocrm`: **95 tests, 200 assertions**, OK.
- PHPStan (level 5) on `ContactUserChannelSync`, both channel hooks,
  `SafehouseLogo`, `UserContactProfileSync`: clean.
- Git push, production rebuild/copy, live volunteer mail: **not** done.

## Blockers

- Owner UAT V1–V7 on DDEV. PHPUnit does not close the feature.
- Production apply and live volunteer mail are Skip until named.

## Next steps

1. Owner runs `specs/004.1-repair-create-crm-user/checklists/owner-user-tests.md`
   V1–V7; report Pass/Fail/Skip.
2. Optional: Cursor-browser walkthrough if the owner agrees.
3. Pass → close 004 **and** 004.1; then 003 U1–U6.
4. Ask before git commit; never push unless asked.
