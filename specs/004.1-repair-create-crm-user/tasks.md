---
description: "Task list for 004.1 repair Contact-first CRM user create"
---

# Tasks: Repair Contact-first CRM user create

**Input**: Design documents from `/specs/004.1-repair-create-crm-user/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

**Runtime**: Local DDEV only (`https://nonprofit-espocrm.ddev.site`). MUST NOT copy/rebuild/rsync on `crm.safehouse.community`. MUST NOT send access mail to live volunteers. After any `rebuild`, set `scheduled_job` Inactive and clear `job` so cron cannot hit prod Google/email/push.

**Tests**: Constitution XIV — PHPUnit for `ContactUserChannelSync` (wrong User/Contact, loop). Handshake already covered by `UserContactProfileSyncTest`. No i18n/template snapshot tests. Owner UAT closes the feature.

**Organization**: User stories US1–US3 from spec.md. Cite Espo:
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/client-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-templates.md
https://github.com/espocrm/documentation/blob/master/docs/development/template-custom-helper.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md
https://github.com/espocrm/documentation/blob/master/docs/development/tests.md

**Complexity**: Each task lists `[C#]`, proposed model, and `test: yes/no`. Proposals are **not** a launch order. Implement on Auto unless the owner names **Launch** / **Replace** for that row.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no incomplete-task dependency)
- **[Story]**: US1–US3 on story-phase tasks only

## Path Conventions

`custom/Espo/Modules/NonprofitEspocrm/`, `client/custom/modules/nonprofit-espocrm/`, `tests/unit/Espo/Modules/NonprofitEspocrm/`. Local PHP via **DDEV only**.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm 004.1 artifacts and the local-only gate.

- [x] T001 Confirm `specs/004.1-repair-create-crm-user/{spec,plan,research,data-model,quickstart}.md` and `contracts/` exist; `.specify/feature.json` FEATURE_DIR is this folder [C1] Auto test: no — inventory
- [x] T002 Confirm `ddev status` healthy; do **not** SSH production; do **not** send volunteer access mail [C1] Auto test: no — gate

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Checkbox on create/edit/small create, **not** on detail. Dynamic Logic. Layout map. Rebuild. **⚠️ CRITICAL**: Complete before US1 JS.

**Independent Test**: Contact create (Volunteer/Employee) shows a real checkbox; saved detail shows **Utente CRM** / linked User field, not a `createCrmUser` dash. Quick create (`detailSmall`) has the checkbox.

- [x] T003 Set `createCrmUser` `layoutDetailDisabled: true` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Contact.json` (keep notStorable, default true) [C3] Auto test: no — metadata
- [x] T004 [P] Map Contact `edit` to NonprofitEspocrm in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/layouts.json` [C2] Auto test: no — app.layouts
- [x] T005 Add `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/edit.json` with `createCrmUser` on Overview (Volunteer/Employee create/edit form) [C3] Auto test: no — layout
- [x] T006 Remove `createCrmUser` from `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/detail.json`; keep `linkedUser` [C2] Auto test: no — layout
- [x] T007 [P] Add `createCrmUser` to `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/detailSmall.json` [C3] Auto test: no — quick create
- [x] T008 Update Dynamic Logic in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Contact.json`: `createCrmUser` visible when `contactType` in Volunteer, Employee **and** `linkedUserId` empty [C4] Auto test: no — Dynamic Logic
- [x] T009 `ddev exec php command.php rebuild` then `UPDATE scheduled_job SET status='Inactive'` and clear `job` [C3] Auto test: no — rebuild + cron gate

**Checkpoint**: Checkbox exists on create; detail shows identity link only.

---

## Phase 3: User Story 1 - Side panel creates a real User (Priority: P1) 🎯 MVP

**Goal**: Volunteer **and** Employee: checkbox opens a side `dialog-record` drawer; copied name/email/phone read-only; Contact save POSTs User with `sourceContactId`; Contact shows Utente CRM. Same for existing Contact with no User. Checkbox off → Contact only.

**Independent Test**: Quickstart V1, V2, V3, V4, V7.

### Implementation for User Story 1

- [x] T010 [P] [US1] Add `client/custom/modules/nonprofit-espocrm/src/views/user/record/create-from-contact.js` extending User record edit: lock copied identity fields; `sendAccessInfo` default on when email present; hide password when send-access on [C6] Auto test: no — UI; UAT V1
- [x] T011 [P] [US1] Add `client/custom/modules/nonprofit-espocrm/src/views/modals/create-crm-user.js`: `className` `dialog dialog-record`; confirm **stashes** attributes (no User POST); close without confirm unchecks `createCrmUser`. Cite modal.md [C7] claude-opus-5-thinking-high test: no — UI; after-save modal already failed
- [x] T012 [US1] Rewrite `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js`: open drawer on checkbox true; after Contact **has id**, `POST User` with `sourceContactId`, no password if send-access; fetch Contact. MUST NOT open User modal on dying `after:save` [C8] claude-opus-5-thinking-high test: no — UI; bug = no User / no link
- [x] T013 [US1] Add `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit-small.js` (same create-user behaviour for quick create) [C5] Auto test: no — UI
- [x] T014 [US1] Register `recordViews.edit` (keep) and `editQuick` (or editSmall) in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Contact.json` [C3] Auto test: no — wiring
- [x] T015 [US1] Confirm `UserContactProfileSync::linkFromSourceContact` in `custom/Espo/Modules/NonprofitEspocrm/Tools/UserContactProfileSync.php` still sets only `linkedUserId` (no `assignedUserId`, no new Contact). Change only if a handshake bug is found [C3] Auto test: yes — existing `UserContactProfileSyncTest.php`

**Checkpoint**: V1 Volunteer create yields Contact + User + link. Employee same. Off = Contact only.

---

## Phase 4: User Story 2 - Access email with set-password link (Priority: P1)

**Goal**: Native send-access, empty password, branded Access info / Password Change Link (logo helper). No plaintext password in the template.

**Independent Test**: Quickstart V5 (Skip if SMTP unset; User must still exist).

### Implementation for User Story 2

- [x] T016 [US2] Add `custom/Espo/Modules/NonprofitEspocrm/TemplateHelpers/SafehouseLogo.php` implementing Htmlizer `Helper`; img src from config site root + existing Safe House PNG (not `{{siteUrl}}` from access-info, that URL is the change-password link) [C5] Auto test: no — helper; UAT V5
- [x] T017 [P] [US2] Register helper + set `accessInfo` / `passwordChangeLink` `module` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/templateHelpers.json` and `.../app/templates.json` [C3] Auto test: no — metadata
- [x] T018 [P] [US2] Add branded `custom/Espo/Modules/NonprofitEspocrm/Resources/templates/accessInfo/{en_US,it_IT,ru_RU}/{body,subject}.tpl` — MUST NOT output `{{password}}`; Italian primary layout [C4] Auto test: no — templates
- [x] T019 [P] [US2] Add branded `custom/Espo/Modules/NonprofitEspocrm/Resources/templates/passwordChangeLink/{en_US,it_IT,ru_RU}/{body,subject}.tpl` the same way [C4] Auto test: no — templates

**Checkpoint**: Access/password-change mail uses Safe House mark; no password string in templates.

---

## Phase 5: User Story 3 - Email and phone stay in sync (Priority: P1)

**Goal**: Full `emailAddressData` / `phoneNumberData` both ways for linked Volunteer/Employee. Skip Help-seeker and skip-option loop.

**Independent Test**: Quickstart V6 + PHPUnit.

### Tests for User Story 3

- [x] T020 [US3] Add `tests/unit/Espo/Modules/NonprofitEspocrm/ContactUserChannelSyncTest.php`: Volunteer email set copied to User; User extra phone copied to Contact; Help-seeker never writes User; skip option no second save; no-op when unlinked. Write first; must fail until T021 [C5] Auto test: yes — wrong person / loop

### Implementation for User Story 3

- [x] T021 [US3] Implement `custom/Espo/Modules/NonprofitEspocrm/Tools/ContactUserChannelSync.php` (ORM only; SaveOption `nonprofitSkipContactUserChannelSync`) [C6] Auto test: yes — T020
- [x] T022 [P] [US3] Add `custom/Espo/Modules/NonprofitEspocrm/Hooks/Contact/SyncLinkedUserChannels.php` `afterSave` (unique hook name) [C4] Auto test: yes — T020
- [x] T023 [P] [US3] Add `custom/Espo/Modules/NonprofitEspocrm/Hooks/User/SyncLinkedContactChannels.php` `afterSave` (unique hook name) [C4] Auto test: yes — T020

**Checkpoint**: Changing one of several emails/phones on User updates Contact and the reverse.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: DDEV verify, 004.1 UAT artifact, dual-close note, no push.

- [x] T024 `ddev exec vendor/bin/phpunit tests/unit/Espo/Modules/NonprofitEspocrm` and PHPStan on new PHP if the job is quick [C4] Auto test: yes — T015/T020
- [x] T025 [P] Write English `specs/004.1-repair-create-crm-user/checklists/owner-user-tests.md` aligned with quickstart V1–V7 (mail Skip if no SMTP; prod Skip) [C3] Auto test: no — UAT
- [x] T026 [P] Append `.specify/progress/` implement log when implement runs; note close **004 and 004.1 together** after Pass; 003 UAT still after that [C2] Auto test: no — handoff
- [x] T027 Confirm git push, production rebuild/copy, and live volunteer mail were **not** done [C1] Auto test: no — gate

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup**: Immediate
- **Foundational**: After Setup; BLOCKS US1–US3 UI that needs layouts
- **US1**: After Phase 2
- **US2**: After Phase 2 (templates independent of drawer; UAT V5 needs US1 User)
- **US3**: After Phase 2; T021 after T020; hooks after Tool
- **Polish**: After desired stories

### User Story Dependencies

- **US1 (P1)**: MVP — create User from Contact
- **US2 (P1)**: Templates can ship with US1; live mail needs SMTP
- **US3 (P1)**: Independent PHP; needs a linked pair from US1 for UAT V6

### Parallel Opportunities

- T004 / T007 with T003–T006 (different files)
- T010 / T011 (user record view vs modal)
- T017 / T018 / T019 after T016 class exists (metadata vs tpl)
- T022 / T023 after T021 (Contact hook vs User hook)
- T025 / T026 with T024 notes

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1–2 layouts + rebuild + cron Inactive
2. T010–T014 drawer + Contact save POST User
3. STOP: DDEV V1/V2/V3

### Incremental

1. US1 → User actually appears
2. US2 → branded access mail
3. US3 → channel sync
4. Polish PHPUnit + owner-user-tests; dual close after Pass

---

## Notes

- [P] = different files, no incomplete-task dependency
- Do not edit `application/`
- Do not change Contact Formula unless hours/status break
- Do not `git commit` / `git push` unless the owner asks
- Suggested models are proposals, never a launch order
- After 004.1 implement-done: owner UAT; Pass → close 004 **and** 004.1; then 003 U1–U6

## Complexity owner table (constitution XV)

| ID | Work | C | Proposed model | test | Why |
|----|------|---|----------------|------|-----|
| T001 | Confirm 004.1 artifacts | 1 | Auto | no | Mechanical |
| T002 | Local-only / no prod mail | 1 | Auto | no | Gate |
| T003 | createCrmUser layoutDetailDisabled | 3 | Auto | no | One JSON key |
| T004 | app.layouts Contact edit | 2 | Auto | no | One map |
| T005 | Contact edit.json checkbox | 3 | Auto | no | Copy detail Overview |
| T006 | Remove checkbox from detail | 2 | Auto | no | Layout row |
| T007 | detailSmall checkbox | 3 | Auto | no | Small form |
| T008 | Dynamic Logic + linkedUserId | 4 | Auto | no | Native Dynamic Logic |
| T009 | Rebuild + cron Inactive | 3 | Auto | no | Constitution X/XVIII |
| T010 | User create-from-contact record view | 6 | Auto | no | sendAccessInfo + lock fields |
| T011 | Side drawer modal | 7 | claude-opus-5-thinking-high | no | dialog-record stash vs POST |
| T012 | Contact edit.js save → User POST | 8 | claude-opus-5-thinking-high | no | 004 after:save already failed |
| T013 | edit-small.js | 5 | Auto | no | Same behaviour, small form |
| T014 | clientDefs wiring | 3 | Auto | no | recordViews keys |
| T015 | Handshake verify | 3 | Auto | yes | Existing PHPUnit |
| T016 | SafehouseLogo helper | 5 | Auto | no | Config site root vs change-password URL |
| T017 | templates.json + templateHelpers | 3 | Auto | no | Metadata |
| T018 | accessInfo tpl en/it/ru | 4 | Auto | no | HTML, no {{password}} |
| T019 | passwordChangeLink tpl | 4 | Auto | no | Same brand |
| T020 | PHPUnit channel sync | 5 | Auto | yes | Wrong person / loop |
| T021 | ContactUserChannelSync Tool | 6 | Auto | yes | emailAddressData sets |
| T022 | Contact afterSave hook | 4 | Auto | yes | Unique name |
| T023 | User afterSave hook | 4 | Auto | yes | Unique name |
| T024 | PHPUnit + PHPStan | 4 | Auto | yes | Run suite |
| T025 | 004.1 owner-user-tests.md | 3 | Auto | no | Constitution XVII |
| T026 | Progress + dual-close note | 2 | Auto | no | Handoff |
| T027 | No push / no prod / no live mail | 1 | Auto | no | Gate |
