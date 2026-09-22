# Tasks: Contact multi-type, Lead convert + user, Contact email unique

**Input**: Design documents from `/specs/004.3-contact-types-lead-convert/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Complexity score**: **8 / 10** (enum→multiEnum copy, hard Contact email,
illegal type sets, Lead convert two-phase persist, Role sync by name).
Constitution XV: do not silently downgrade; this run stays on the current
model.

**Tests**: PHPUnit as in plan.md. Owner UAT is polish
(`checklists/owner-user-tests.md`). Live volunteer mail and prod apply =
Skip until the owner names them.

**Organization**: Spec user stories US1–US5. Unique Contact email (FR-014)
is Foundational because native convert uses `skipDuplicateCheck`.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/select-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-console-commands.md
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/modules.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1–US5 from spec.md
- Exact file paths required

---

## Phase 1: Setup

**Purpose**: Write surface and i18n keys

- [X] T001 Confirm no edits under `application/` or core client; all work in `custom/Espo/Modules/NonprofitEspocrm/` and `client/custom/modules/nonprofit-espocrm/`
- [X] T002 [P] Add Contact/Lead i18n for multi-type, illegal combination, unique Contact email, Lead Generic/Member labels in `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/en_US/Contact.json`, `it_IT/Contact.json`, `ru_RU/Contact.json`, and matching `Lead.json`

---

## Phase 2: Foundational (blocks all stories)

**Purpose**: multiEnum `contactType`, legal-set validator, Dynamic Logic `arrayAnyOf`, hard Contact email unique, enum copy command

**⚠️ CRITICAL**: Do not start US1–US5 until T016 (copy+rebuild) has run on DDEV

- [X] T003 [P] Add `custom/Espo/Modules/NonprofitEspocrm/Tools/ContactEmailUniqueness.php` (EmailAddress relation, no hardcoded table names; allow linked User only)
- [X] T004 [P] Add `custom/Espo/Modules/NonprofitEspocrm/Classes/FieldValidators/Contact/EmailAddress/UniqueAmongContacts.php` and wire `validatorClassNameList` on `emailAddress` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Contact.json`
- [X] T005 Add `custom/Espo/Modules/NonprofitEspocrm/Classes/DuplicateWhereBuilders/Contact.php` and `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/recordDefs/Contact.json` (`duplicateWhereBuilderClassName`, `updateDuplicateCheck: true`)
- [X] T006 [P] PHPUnit unique Contact email in `tests/unit/Espo/Modules/NonprofitEspocrm/ContactEmailUniqueTest.php`
- [X] T007 Add `custom/Espo/Modules/NonprofitEspocrm/Classes/ConsoleCommands/CopyContactTypeEnumToMulti.php` and register `copyContactTypeEnumToMulti` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/consoleCommands.json` (`listed: true`, `--apply`; ORM only)
- [X] T008 List colliding Contact emails on DDEV (ORM/EmailAddress); write counts into `.specify/progress/` — do not auto-merge
- [X] T009 Change `contactType` to multiEnum (`maxCount: 2`, `displayAsLabel`, `allowCustomOptions: false`, existing option keys) in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Contact.json`
- [X] T010 Add `custom/Espo/Modules/NonprofitEspocrm/Classes/FieldValidators/Contact/ContactType/LegalCombination.php` and wire it on `contactType` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Contact.json`
- [X] T011 [P] PHPUnit legal combinations in `tests/unit/Espo/Modules/NonprofitEspocrm/ContactTypeLegalCombinationTest.php`
- [X] T012 Replace Contact Dynamic Logic `in`/`equals` on `contactType` with `arrayAnyOf` (panels, volunteer/member/shared fields, `createCrmUser` including MemberContact) in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Contact.json`
- [X] T013 Make `custom/Espo/Modules/NonprofitEspocrm/Hooks/Contact/RestrictPersonnelTypeToAdmin.php` treat `contactType` as a list (Volunteer/Employee in the new set)
- [X] T014 [P] Update `tests/unit/Espo/Modules/NonprofitEspocrm/RestrictPersonnelTypeToAdminTest.php` for array types
- [X] T015 Teach `custom/Espo/Modules/NonprofitEspocrm/Tools/UserContactProfileSync.php` to read/write `contactType` as a list (no string overwrite of Volunteer+Member)
- [X] T016 `ddev exec php command.php rebuild` (soft); `copyContactTypeEnumToMulti` dry-run then `--apply`; confirm Volunteer Contacts still have type Volunteer as a one-item list

**Checkpoint**: Types are arrays; illegal mixes 400; duplicate Contact email 400; DDEV rows copied

---

## Phase 3: User Story 1 - Volunteer/Employee + Member (P1) 🎯 MVP

**Goal**: Legal two-type Contacts; both panels; shared fields once; filters include mixed people

**Independent Test**: Admin sets Volunteer+Member; both panels; tax/birth once; save; Volontari and Associati both list them. Volunteer+Employee and Member+Help-seeker refused

- [X] T017 [P] [US1] Keep shared tax/birth on Overview; exclusive fields only in volunteer vs member panels in `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/edit.json`, `detail.json`, `detailSmall.json`
- [X] T018 [US1] In `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js` block Volunteer+Employee (and other illegal mixes) in the type field; keep admin-only Volunteer/Employee options
- [X] T019 [P] [US1] Mirror T018 in `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit-small.js`
- [X] T020 [US1] Switch primary filters to array containment in `custom/Espo/Modules/NonprofitEspocrm/Classes/Select/Contact/PrimaryFilters/Volunteers.php`, `VolunteersOccasionali.php`, `VolunteersEmployees.php`, `Employees.php`, `Associati.php`
- [X] T021 [P] [US1] PHPUnit filters in `tests/unit/Espo/Modules/NonprofitEspocrm/VolunteersPrimaryFilterTest.php` (Volunteer+Member matches Volunteers and Associati)

**Checkpoint**: US1 mixed Contact works without create-user/Lead work

---

## Phase 4: User Story 2 - Member create-user + Role sync (P1)

**Goal**: Member (and combos) use 004.2 review; both Roles pre-filled; edit of types syncs Roles, no second User

**Independent Test**: Create Member + checkbox → review Role Member. Volunteer+Member → both Roles. Add Member on existing Volunteer+User → Role Member, no create-user checkbox

- [X] T022 [US2] Show `createCrmUser` for MemberContact and legal combos on create only in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Contact.json` and `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js` (and `edit-small.js`)
- [X] T023 [US2] Pre-fill all matching Role names (Volunteer and/or Employee and/or Member) on the review in `client/custom/modules/nonprofit-espocrm/src/views/modals/create-crm-user.js` and/or `client/custom/modules/nonprofit-espocrm/src/views/user/record/create-from-contact.js`
- [X] T024 [US2] Add `custom/Espo/Modules/NonprofitEspocrm/Hooks/Contact/SyncRolesToLinkedUser.php` (afterSave; Role by **name**; add/remove only Volunteer/Employee/Member)
- [X] T025 [P] [US2] PHPUnit role sync in `tests/unit/Espo/Modules/NonprofitEspocrm/SyncRolesToLinkedUserTest.php`

**Checkpoint**: Member create-user and later type edits match Roles

---

## Phase 5: User Story 3 - Lead single type + fields (P1)

**Goal**: Lead has one type Volunteer/Member/Generic and matching extra fields

**Independent Test**: Volunteer Lead has type only (no extra panels); staff can add Member; Volunteer+Employee refused; Employee offered

- [X] T026 [US3] Add Lead `contactType` multiEnum `maxCount: 2` (Volunteer, Employee, MemberContact, Other) in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Lead.json`
- [X] T027 [US3] Lead Dynamic Logic `arrayAnyOf` for volunteer vs member extras in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Lead.json`
- [X] T028 [US3] Module Lead layouts (`detail`, `edit`, `detailSmall`) under `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Lead/` and point `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/layouts.json` `module` NonprofitEspocrm
- [X] T029 [P] [US3] Lead i18n option labels Volunteer / Member / Generic in `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/{en_US,it_IT,ru_RU}/Lead.json`

**Checkpoint**: Lead form is single-type with the right extras (convert not required yet)

---

## Phase 6: User Story 4 - Convert Lead + optional User (P1)

**Goal**: Native convert copies fields; Crea utente CRM uses 004.2 review; Cancel persists nothing

**Independent Test**: Convert Volunteer Lead + checkbox → Contact Volunteer + User Volunteer, Lead Converted. Cancel review → Lead unchanged, 0 Contact/User. Generic checkbox off → Contact Other. Duplicate Contact email fails convert

- [X] T030 [US4] Contact `detailConvert` layout (type, extras, `createCrmUser`) in `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/detailConvert.json` and `app/layouts.json`
- [X] T031 [US4] Custom convert view `client/custom/modules/nonprofit-espocrm/src/views/lead/convert.js` extending stock convert; wire `clientDefs.Lead` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Lead.json`. MUST NOT edit `application/Espo/Modules/Crm/Tools/Lead/ConvertService.php`
- [X] T032 [US4] Convert: Crea utente CRM default on for Volunteer/Member, off for Generic; checkbox does not open a panel; on → 004.2 review then `Lead/action/convert` then User POST; Cancel → no convert POST
- [X] T033 [US4] Map Generic User to no Volunteer/Employee/Member Roles; Volunteer convert still admin-only via Contact validators/hooks
- [X] T034 [P] [US4] Unit-test convert defaults / payload helper if extracted in `tests/unit/Espo/Modules/NonprofitEspocrm/` (JS-only path: extract a small mapper and test it)

**Checkpoint**: Convert+user matches Contact create; skippable duplicate cannot create a second Contact email

---

## Phase 7: User Story 5 - Combined User reflection (P2)

**Goal**: User detail shows both volunteer and member fields, shared once, read-only from Contact

**Independent Test**: Volunteer+Member Contact competences + joinDate appear on User; not independently editable there

- [X] T035 [US5] Ensure User detail volunteer + member panels use the same de-dupe as Contact in `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/User/detail.json` and `ContactProfileLoader` in `custom/Espo/Modules/NonprofitEspocrm/Classes/FieldProcessing/User/ContactProfileLoader.php`
- [X] T036 [P] [US5] PHPUnit loader includes both field sets in `tests/unit/Espo/Modules/NonprofitEspocrm/UserContactProfileSyncTest.php`

**Checkpoint**: Combined profile is a mirror only

---

## Phase 8: Polish

- [X] T037 [P] Write `specs/004.3-contact-types-lead-convert/checklists/owner-user-tests.md` aligned with `quickstart.md` (Italian labels; prod/mail Skip)
- [X] T038 `ddev exec php command.php rebuild` (soft); `ddev exec vendor/bin/phpstan analyse -c phpstan.neon`; `ddev exec vendor/bin/phpunit tests/unit`; inactivate leftover DDEV Google/push jobs if rebuild re-activated them
- [X] T039 Append `.specify/progress/` implement handoff; MUST NOT git commit/push unless asked; MUST NOT prod-apply this amendment unless the owner names it
- [X] T040 [US1] Hotfix create-form Volunteer/Employee: in `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js` copy `selected` from the model and `setOptionList(options, true)` so the first pick does not clear (edit-small inherits). Cite fields.md + custom-views.md. Owner confirmed create Volontario / combo Associato+Volontario.
- [X] T041 [US3] Lead type-only layouts (no volunteer/member panels); `maxCount: 2`; Employee option; LegalCombination + Lead record edit picker; same legal combos as Contact
- [X] T042 [US4] Convert copies one or both types onto Contact `detailConvert` so matching field sets appear; no extra fields required on Lead
- [X] T043 [US4] After CRM-user review Cancel, Convert / Contact-create Save MUST reopen the review (`clearView('createCrmUser')`)
- [X] T044 [US4] Convert empty `contractType` (Volunteer): optional enum `""` + EmptyStringToNull; strip empty from convert payload; formula `array\includes` for multi-type
- [X] T045 [US2] User `isActive` false → linked Contact `personnelStatus` Inactive (`Hooks/User/InactivateLinkedContacts` AfterSave, SKIP_ALL); same as delete

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Immediate
- **Foundational (Phase 2)**: After Setup — **BLOCKS** US1–US5 (especially T016)
- **US1 (Phase 3)**: After Phase 2 — MVP
- **US2 (Phase 4)**: After US1 (create-user sits on mixed types)
- **US3 (Phase 5)**: After Phase 2; can overlap US2 (different files: Lead vs Contact JS)
- **US4 (Phase 6)**: After US2 + US3 (review modal + Lead fields)
- **US5 (Phase 7)**: After US1 (needs mixed types)
- **Polish**: After desired stories

### User Story Dependencies

- **US1**: After Foundational
- **US2**: After US1
- **US3**: After Foundational; parallel with US2 if staffed
- **US4**: After US2 and US3
- **US5**: After US1

### Parallel Opportunities

- T002, T003, T004, T006, T011, T014, T021, T025, T029, T034, T036, T037
- US3 Lead metadata while US2 Contact JS, after T016

### Parallel example: Foundational uniqueness

```bash
Task: "ContactEmailUniqueness.php"
Task: "UniqueAmongContacts.php + entityDefs"
Task: "ContactEmailUniqueTest.php"
```

---

## Implementation Strategy

### MVP (US1 only)

1. Phase 1–2 including DDEV type copy
2. Phase 3 mixed Contact + filters
3. STOP and validate US1 on DDEV

### Incremental

1. US2 Member create-user + Role sync
2. US3 Lead types
3. US4 Convert + user
4. US5 User mirror
5. Polish + owner UAT

### Notes

- Path = Metadata (multiEnum, Dynamic Logic) + Code (validators, hooks, convert view). Formula rejected for unique email and user-visible illegal types (plan.md).
- Ask before every git commit; never push unless asked.
- Local DDEV only. No `--hard` rebuild. No new prod records.
