# 026 — 004.2 UAT repair: layout, modal copy, cron, filters, CID logo

**Date:** 2026-09-18  
**Agent:** Cursor Auto (plan `004.2 UAT repair`; stay in `specs/004.2-create-user-review`)

## State

- Same feature folder. No new Spec Kit feature. Write surface:
  `custom/` and `client/custom/` only. **No** `application/` edits.
- Owner checklist V1–V12 remains **unchecked**. PHPUnit does not close UAT.
- Close **004 + 004.1 + 004.2** only after owner Pass.
- Git commit / push / production copy / live volunteer mail: **not** done.

## Files changed

- Layout: `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/edit.json`
  (keep personName `name`; drop duplicate `firstName`/`lastName` row).
- JS: `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js`
  (`salutationName` + volunteer-field preview in review stash);
  `.../user/record/create-from-contact.js` (lock `salutationName`).
- Filters: `Classes/Select/Contact/PrimaryFilters/Volunteers.php`,
  `VolunteersEmployees.php` (`isOccasional` false **or** null).
- Logo: `Tools/SafehouseLogoAttachment.php` (inline Attachment + recreate
  when dump row has no file on disk); `TemplateHelpers/SafehouseLogo.php`;
  `Tools/ShiftEmailService.php` (shared helper). PNG already at
  `client/custom/modules/nonprofit-espocrm/img/safe-house-logo.png`.
- Tests: `ContactCreateFieldFillTest`, `VolunteersPrimaryFilterTest`,
  `SafehouseLogoHelperTest`.
- Specs: `tasks.md` T026–T032; owner checklist V1/V2/V11/V12 copy;
  `quickstart.md` notes. This progress file.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/terms-and-naming.md
https://github.com/espocrm/documentation/blob/master/docs/administration/layout-manager.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/select-defs.md
https://github.com/espocrm/documentation/blob/master/docs/administration/jobs.md
https://github.com/espocrm/documentation/blob/master/docs/development/attachments.md
https://github.com/espocrm/documentation/blob/master/docs/development/template-custom-helper.md
https://github.com/espocrm/documentation/blob/master/docs/development/tests.md

## Local data / cron

- DDEV SQL: Volunteer regular `is_occasional=0` = 2; occasional `=1` = 17;
  Employee = 2; MemberContact = 3. No NULL `is_occasional` in this dump.
- Dummy + Cleanup Active. Google Calendar Sync / Overlay and
  `SafehouseCrmSubmitPushReminders` Inactive. Dummy stays Active after rebuild.
- New UAT pair (confirm path): Contact `6aad392775dd70ace` ↔ User
  `6aad392b963d9a11e` (`uat-repair-0042-cid`, email
  `uat-repair-0042-cid@example.com`). Cancel path (`PrefixOcc` /
  `uat-cancel-0042@example.com`) persisted **nothing**.

## Verification

- PHPUnit `tests/unit/Espo/Modules/NonprofitEspocrm`: **122 tests, 277
  assertions**, OK. Integration install still TypeError in HookManager
  (pre-existing); unit only.
- PHPStan level 5 on changed logo PHP: clean.
- Rebuild: already done earlier this implement; Dummy not re-gated.
- Browser DDEV (`Uatbrowser` admin), hard navigation:
  - `#Admin`: cron/lavori-pianificati banner **gone**. Cache-disabled
    and Espo 10.0.8 nagger expected.
  - Contatti filters: Volontari **1–2 / 2**; Volontari occasionali
    **1–17 / 17**; Dipendenti **1–2 / 2**; Associati **1–3 / 3**.
  - `#Contact/create`: one personName row (Nominativo/Cognome inside
    `name`); Volunteer + Occasionale + Crea utente CRM; Salva opens
    `dialog-record` with **Mr. UatRepair2 CidLogo**, Occasionale checked
    read-only, role Volunteer, `sendAccessInfo` on.
  - First run Annulla: no Contact/User.
  - Confirm: Contact name Mr.; linked User; Assigned User still
    Uatbrowser; User Volontariato Occasionale checked read-only.
- Mailpit CID: native create-user `sendAccessInfo` did **not** land in
  Mailpit because the system Group Email Account SMTP is Gmail
  (`smtp.gmail.com`), not Mailpit. Same helper + Espo Sender rewrite
  sent via Mailpit SMTP `127.0.0.1:1025` (example.com only; **not**
  Gmail). HTML: `<img src="cid:6aad3eaca62781de9@espo">`; logo visible;
  no remote `safe-house-logo.png` URL; no password value. Dump
  Attachment `6a77c1826385ed386` had no `data/upload` file — helper now
  recreates.

## Blockers

- Owner UAT Pass on V1–V12. Do not mark checklist `[x]`.
- Native access-info on DDEV still uses Gmail SMTP unless outbound is
  pointed at Mailpit without touching production accounts.

## Next steps

1. Owner runs `specs/004.2-create-user-review/checklists/owner-user-tests.md`
   V1–V12; report Pass/Fail/Skip.
2. Pass → close 004 + 004.1 + 004.2; then 003 Google/WF U1–U6.
3. Ask before git commit; never push unless asked.
