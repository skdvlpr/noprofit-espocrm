# Tasks: Create-User review modal, unique email, Contact mirror

**Input**: Design documents from `/specs/004.2-create-user-review/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Complexity score**: **8 / 10** (intercept Save + review modal, admin ACL,
hard unique User email, profile load-only, SMTP UAT). Constitution XV:
do not silently downgrade; this run stays on the current model.

**Tests**: PHPUnit as listed in plan.md. Owner UAT is separate
(`checklists/owner-user-tests.md` in polish).

**Organization**: User stories from spec.md (all P1; implement US1 → US4).

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

**Purpose**: Confirm write surface and i18n keys

- [x] T001 Confirm no edits under `application/` or core client; all work in `custom/Espo/Modules/NonprofitEspocrm/` and `client/custom/modules/nonprofit-espocrm/`
- [x] T002 [P] Add Contact/User i18n keys for invite-link label and unique-email error in `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/en_US/Contact.json`, `it_IT/Contact.json`, `ru_RU/Contact.json`, and matching `User.json`

---

## Phase 2: Foundational

**Purpose**: Shared JS/PHP behaviour used by every story

- [x] T003 Stop opening the User drawer on `change:createCrmUser` in `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js`
- [x] T004 Hide `createCrmUser` when `!this.model.isNew()` and hide/clear `linkedUser` while the checkbox is on in `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js`
- [x] T005 Mirror T003–T004 in `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit-small.js`

**Checkpoint**: Checkbox no longer opens a panel; existing edit has no create-user checkbox

---

## Phase 3: User Story 1 - Review modal after Save (P1) 🎯 MVP

**Goal**: Admin Save on create opens a review panel; confirm persists Contact+User; cancel persists nothing

**Independent Test**: `#Contact/create` Volunteer, checkbox on, fill email, Salva → panel with role Volunteer; panel Salva → linked User; Cancel → no records

- [x] T006 [US1] Rewrite `client/custom/modules/nonprofit-espocrm/src/views/contact/modals/create-crm-user.js` as a review-only stash (no User POST, no open-on-checkbox)
- [x] T007 [US1] Intercept Contact create `save()` in `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js`: require email; open review modal; on confirm save Contact then POST User with `sourceContactId`; on User fail remove the new Contact; on cancel do not save
- [x] T008 [US1] Pre-fill `rolesIds` Volunteer/Employee by Contact type (ORM Role **name**, not hardcoded ids) in `client/custom/modules/nonprofit-espocrm/src/views/contact/modals/create-crm-user.js` or the Contact edit view
- [x] T009 [US1] Apply the same intercept on `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit-small.js`
- [x] T010 [US1] Update create-user tooltip copy in `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/en_US/Contact.json`, `it_IT/Contact.json`, `ru_RU/Contact.json` (no longer “opens on check”)

**Checkpoint**: US1 create path works without password story polish

---

## Phase 4: User Story 2 - Set-password link, no password fields

**Goal**: Review panel has no password UI; invite checkbox sends native set-password-link mail

**Independent Test**: Panel has no Password/Genera; invite checkbox labelled and on; SMTP mail has link and no password

- [x] T011 [US2] Hide password/confirm/generate/preview always; show `sendAccessInfo` when email exists; default on; customLabel in `client/custom/modules/nonprofit-espocrm/src/views/user/record/create-from-contact.js`
- [x] T012 [US2] Ensure User POST omits `password` (even if a leftover attr exists) in `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js`
- [x] T013 [P] [US2] Unit test payload omits password when sendAccessInfo true in `tests/unit/Espo/Modules/NonprofitEspocrm/` (extend existing sync or a small helper test if POST is JS-only — then assert JS payload builder if extracted)

**Checkpoint**: No plaintext password path on this panel

---

## Phase 5: User Story 3 - Unique User email

**Goal**: Second User with the same email cannot be saved

**Independent Test**: Create User with `kokshse196@proton.me` (kept `semen.koksharov`) → refused; fresh email succeeds

- [x] T014 [P] [US3] Duplicate where-builder in `custom/Espo/Modules/NonprofitEspocrm/Classes/DuplicateWhereBuilders/User.php` and wire `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/recordDefs/User.json`
- [x] T015 [US3] Hard unique validator in `custom/Espo/Modules/NonprofitEspocrm/Classes/FieldValidators/User/EmailAddress/UniqueAmongUsers.php` plus `entityDefs/User.json` validator list (cannot skip)
- [x] T016 [US3] PHPUnit in `tests/unit/Espo/Modules/NonprofitEspocrm/UserEmailUniqueTest.php`

**Checkpoint**: Duplicate User email is a hard error

---

## Phase 6: User Story 4 - Contact profile reflection

**Goal**: Competences and personnel profile display on User from Contact; User save does not write a second copy

**Independent Test**: Edit competences on Contact; User screen matches and is read-only

- [x] T017 [US4] Set volunteer/member profile fields `notStorable` + `readOnly` including `isOccasional` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/User.json`
- [x] T018 [US4] Stop `writeProfileToContact` for those fields in `custom/Espo/Modules/NonprofitEspocrm/Tools/UserContactProfileSync.php`; keep `loadFromContact` + `ContactProfileLoader`
- [x] T019 [US4] PHPUnit that User save does not copy competences onto Contact in `tests/unit/Espo/Modules/NonprofitEspocrm/UserContactProfileSyncTest.php`

**Checkpoint**: User screen is a mirror; Contact is the store

---

## Phase 7: Admin-only personnel types (US1 FR-007)

**Goal**: Non-admin cannot create Volunteer/Employee

- [x] T020 [US1] Filter `contactType` options when `!this.getUser().isAdmin()` in `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js` and `edit-small.js`
- [x] T021 [US1] Contact beforeSave hook `custom/Espo/Modules/NonprofitEspocrm/Hooks/Contact/RestrictPersonnelTypeToAdmin.php`
- [x] T022 [US1] PHPUnit in `tests/unit/Espo/Modules/NonprofitEspocrm/RestrictPersonnelTypeToAdminTest.php`

---

## Phase 8: Polish

- [x] T023 [P] Write `specs/004.2-create-user-review/checklists/owner-user-tests.md` and align `quickstart.md`
- [x] T024 `ddev exec php command.php rebuild`; inactivate leftover jobs; PHPUnit NonprofitEspocrm; PHPStan on new PHP
- [x] T025 Append `.specify/progress/` implement handoff; MUST NOT git commit/push unless asked

---

## Phase 9: UAT repair (same spec; no new feature folder)

Owner V1/V2 Fail items: duplicate name fields, missing salutation/occasional on
review, cron banner, Volunteers filter, access-info logo.

- [x] T026 Restore Dummy + local-safe `scheduled_job` Active; keep Google and volunteer-notify jobs Inactive; run `cron.php` once
- [x] T027 Remove duplicate `firstName`/`lastName` row from Contact `edit.json`; keep personName `name`
- [x] T028 Copy `salutationName` + volunteer-field preview into User review stash; lock `salutationName`
- [x] T029 NULL-safe Volunteers / VolunteersEmployees primary filters (`false` OR `null`)
- [x] T030 CID inline-attachment logo helper (`SafehouseLogoAttachment`) shared with shift mail; PNG already in `client/custom/.../img/`
- [x] T031 PHPUnit unit tests for layout, identity copy, filters, logo; PHPStan; rebuild without re-gating Dummy
- [x] T032 Browser UAT on DDEV (filters, create, review, Mailpit logo). Owner checklist stays unchecked until owner Pass
- [x] T033 Access-info / password-change-link `{{safehouseLogo}}` must survive Htmlizer `postProcessHtml` so Gmail gets CID (shift mail already CID-ok)

---

## Dependencies & Execution Order

- Setup → Foundational (blocks UI stories) → US1 modal → US2 mail UI → US3 email unique and US4 mirror can proceed after US1 (different files)
- T020–T022 can run after T004 (same edit.js as US1 — sequential with T007)

### Parallel

- T002 with T001
- T014 with T017
- T016, T019, T022 after their code

## MVP

T001–T010 + T011–T012 (usable create + invite). Then unique email, mirror, admin hook.

## Notes

- Ask before commit. Never push unless asked. Never edit `application/`.
- Path = Code. Formula rejected.
