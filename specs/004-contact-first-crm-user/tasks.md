---
description: "Task list for Contact-first volunteer/employee CRM user"
---

# Tasks: Contact-first volunteer/employee CRM user

**Input**: Design documents from `/specs/004-contact-first-crm-user/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

**Runtime (owner 2026-09-16):** Local DDEV is a **production dump** (owner
authorized wipe of local records + full prod DB import). Code + copy +
User `notStorable` ran on that dump. MUST NOT rebuild/copy/rsync on
`crm.safehouse.community` until the owner names that exact action. After
any DDEV rebuild, keep scheduled jobs Inactive so cron cannot hit prod
Google/email/push.

**Tests**: Constitution XIV — propose PHPUnit for Tools that can create a duplicate Contact, steal Assigned User, or keep planner on the User column. Owner may drop test rows after the complexity table. Stupid tests (i18n snapshots, metadata dumps) are not included.

**Organization**: User stories from spec.md (US1–US5). Cite Espo:
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/client-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-console-commands.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/frontend/view-setup-handlers.md
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
https://github.com/espocrm/documentation/blob/master/docs/development/acl.md

**Complexity**: Each task lists `[C#]`, proposed model, and `test: yes/no`. Proposals are **not** a launch order. Implement on Auto unless the owner names **Launch** / **Replace** for that row.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: User story label (US1–US5) on story-phase tasks only

## Path Conventions

Espo module at repository root: `custom/Espo/Modules/NonprofitEspocrm/`, `client/custom/modules/nonprofit-espocrm/`, `tests/unit/Espo/Modules/NonprofitEspocrm/`. Local PHP via **DDEV only**.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm 004 artifacts and the local-only gate before metadata changes.

- [x] T001 Confirm `specs/004-contact-first-crm-user/{spec,plan,research,data-model,quickstart}.md` and `contracts/` exist; `.specify/feature.json` FEATURE_DIR is this folder [C1] Auto test: no — inventory
- [x] T002 Confirm DDEV healthy; owner 2026-09-16 authorized local wipe + full prod dump import. Do **not** rebuild/copy on `crm.safehouse.community` [C1] Auto test: no — gate

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Contact competence column + create-user / handshake fields + layouts + i18n + loader `__APPEND__` + DDEV rebuild. **⚠️ CRITICAL**: Complete before story PHP/JS. Do **not** set User `activityCompetences` `notStorable` yet (US5 copy first).

**Independent Test**: After rebuild, Contact entityDefs include `activityCompetences` and notStorable `createCrmUser`; User has notStorable `sourceContactId`; Contact detail layout shows competences in the volunteer panel.

- [x] T003 Add storable `activityCompetences` (same multiEnum options + `translation` as User) and notStorable bool `createCrmUser` (`default` true) to `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Contact.json` [C4] Auto test: no — schema via rebuild; bug would be missing column
- [x] T004 [P] Add notStorable `sourceContactId` (varchar, all layouts disabled) to `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/User.json` [C3] Auto test: no — handshake flag only
- [x] T005 [P] Add `createCrmUser` and `activityCompetences` strings to `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/{en_US,it_IT,ru_RU}/Contact.json` (en+it required; ru to match existing Contact files). English: “Create CRM user”; Italian consistent with “Utente CRM” [C3] Auto test: no — i18n
- [x] T006 Put `activityCompetences` on panel `personnelVolunteerEmployee` and `createCrmUser` on Overview or that panel in `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/detail.json` (`app/layouts.json` already maps Contact detail to NonprofitEspocrm) [C3] Auto test: no — layout
- [x] T007 Add Dynamic Logic in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Contact.json`: `createCrmUser` and `activityCompetences` visible when `contactType` in `Volunteer`,`Employee` (same pattern as `weeklyHours`) [C4] Auto test: no — native Dynamic Logic
- [x] T008 Prefix `readLoaderClassNameList` with `"__APPEND__"` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/recordDefs/User.json` [C2] Auto test: no — metadata merge
- [x] T009 `ddev exec php command.php rebuild` so Contact competence column exists; User `activity_competences` column **still exists** [C3] Auto test: no — rebuild

**Checkpoint**: DDEV Contact can store competences; create-user checkbox metadata exists; User competence column not dropped.

---

## Phase 3: User Story 1 - Create volunteer/employee from Contact (Priority: P1) 🎯 MVP

**Goal**: Volunteer/Employee Contact create shows **Create CRM user** (default on). Save opens User create with name/email/phone copied and `sourceContactId`. Hook links `linkedUserId` and does **not** create a second Contact or overwrite Assigned User. Checkbox off / other types: no User. Members: no checkbox.

**Independent Test**: DDEV: create Volunteer with checkbox on → one Contact + one User linked via CRM user; Assigned User is still the staff owner. Checkbox off → Contact only. Help-seeker: no checkbox.

### Tests for User Story 1

- [x] T010 [US1] Add `tests/unit/Espo/Modules/NonprofitEspocrm/UserContactProfileSyncTest.php` covering: (1) `sourceContactId` sets that Contact `linkedUserId` and does not `getNewEntity('Contact')`; (2) Volunteer/Employee with no linked Contact does not auto-create; (3) `assignedUserId` on the Contact is unchanged. Mock `EntityManager`. Bug these catch: duplicate person row; volunteer becomes Assigned User. [C6] Auto test: yes — duplicate Contact / ACL own collision. Write first; must fail until T011.

### Implementation for User Story 1

- [x] T011 [US1] Change `custom/Espo/Modules/NonprofitEspocrm/Tools/UserContactProfileSync.php` (and `Hooks/User/SyncContactProfile.php` if the flag must be read from the User entity): implement contracts/create-user-from-contact.md + identity-link.md. Keep portal skip and `SKIP_ALL`. Do not rename the hook class (unique name). [C8] claude-opus-5-thinking-high test: yes — covered by T010
- [x] T012 [P] [US1] Register Contact `recordViews.edit` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Contact.json` as `nonprofit-espocrm:views/contact/record/edit` [C3] Auto test: no — wiring
- [x] T013 [US1] Add `client/custom/modules/nonprofit-espocrm/src/views/contact/record/edit.js`: after successful save, if `createCrmUser` and type Volunteer/Employee and no `linkedUserId`, open `views/modals/edit` for User with `firstName`,`lastName`,`emailAddress`,`phoneNumber`,`sourceContactId`. Do not open for Help-seeker/MemberContact. Cite modal.md / users-management.md (access info stays on User form). [C7] claude-opus-5-thinking-high test: no — UI; owner UAT V1/V2

**Checkpoint**: Contact-first create path exists; User-first Volunteer Contact insert is gone.

---

## Phase 4: User Story 2 - Contact stores profile; User may mirror (Priority: P1)

**Goal**: Competences visible on Volunteer/Employee Contact. User volunteering panel MAY keep mirrors. Loader fills User `activityCompetences` from the linked Contact. User detail shows the linked Contact (FR-009). User column still storable until US5.

**Independent Test**: Edit competences on Contact; User screen (if panel shown) matches after refresh via loader. Non Volunteer/Employee: competences hidden.

### Implementation for User Story 2

- [x] T014 [US2] Add `activityCompetences` to load (and User-form write-back to **existing** linked Contact only) in `custom/Espo/Modules/NonprofitEspocrm/Tools/UserContactProfileSync.php` `VOLUNTEER_FIELDS` / `loadFromContact`; `Classes/FieldProcessing/User/ContactProfileLoader.php` already calls that [C5] Auto test: no — loader path proven via T010 + UAT SC-002; no extra JSON roundtrip test
- [x] T015 [P] [US2] Add notStorable User link `linkedContact` (or populate from `findPrimaryContact`) and a row on `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/User/detail.json` plus `entityDefs/User.json` so User shows the person Contact [C4] Auto test: no — FR-009 layout
- [x] T016 [P] [US2] Update `custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanningInstaller.php` so Contact volunteer panel is provisioned with `activityCompetences`; MUST NOT re-add a storable User competence column after US5 [C4] Auto test: no — installer string checks today; keep that style

**Checkpoint**: Contact is the editor of competences; User can still show a mirror; identity link visible both ways.

---

## Phase 5: User Story 3 - Shift planner uses Contact competences (Priority: P1)

**Goal**: `getUserCompetences` reads Contact where `linkedUserId` = User id (Volunteer/Employee preferred). Empty / missing Contact = all categories. MUST NOT use `$user->get('activityCompetences')` as storage. Invitees stay Users.

**Independent Test**: Contact lists only meal distribution; User entity in memory has none; availability blocks other categories. Empty Contact list = all.

**Depends on**: T003 (Contact field). On this DDEV instance, run T025 copy **before** relying on live volunteer data (planner will not fall back to User).

### Tests for User Story 3

- [x] T017 [US3] Add `tests/unit/Espo/Modules/NonprofitEspocrm/ContactActivityCompetencesTest.php` (extracted reader used by `ShiftPlanningSupport`): Contact list returned; no Contact → `[]`; User attribute ignored. [C5] Auto test: yes — wrong eligibility. Write first; fail until T018.

### Implementation for User Story 3

- [x] T018 [US3] Change `custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanning/ShiftPlanningSupport.php` `getUserCompetences` to ORM-find Contact by `linkedUserId` (no hardcoded table names) per contracts/competences-and-planner.md [C6] Auto test: yes — T017

**Checkpoint**: Planner source of truth is Contact.

---

## Phase 6: User Story 4 - Delete User still inactivates Contact (Priority: P1)

**Goal**: Keep `afterRemove` behaviour. Do not delete the Contact.

**Independent Test**: Delete volunteer User on DDEV; Contact remains Inactive.

### Implementation for User Story 4

- [x] T019 [US4] Verify `custom/Espo/Modules/NonprofitEspocrm/Hooks/User/InactivateLinkedContacts.php` still sets `personnelStatus` Inactive for `linkedUserId` OR `portalUserId` with `SKIP_ALL`; add a one-line cite to hooks.md `afterRemove` if missing. **No behaviour change** unless a bug is found. [C2] Auto test: no — unchanged hook; UAT V4

**Checkpoint**: US4 is a keep-existing-path check, not a rewrite.

---

## Phase 7: User Story 5 - Local competence copy then User column drop (Priority: P2)

**Goal**: Owner-authorized prod dump into DDEV, then copy User → linked Volunteer/Employee Contact, then User field `notStorable` + DDEV rebuild. Production copy/rebuild remain **Skip** until named.

**Independent Test**: After apply, Contact lists match former User lists; after notStorable, ORM no longer stores User competences (soft rebuild may leave the MariaDB column); planner reads Contact.

### Tests for User Story 5

- [x] T020 [US5] Add copy mapper tests in `tests/unit/Espo/Modules/NonprofitEspocrm/ContactActivityCompetencesTest.php`: User list written to linked Volunteer Contact; skip when no Contact; leftover column when ORM empty and Contact empty; do not wipe Contact; skip Help-seeker. [C5] Auto test: yes — migrator. Write first; fail until T022.

### Implementation for User Story 5

- [x] T021 [P] [US5] Add `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/consoleCommands.json` listed command (e.g. `copyUserActivityCompetences`) → `Espo\Modules\NonprofitEspocrm\Classes\ConsoleCommands\CopyUserActivityCompetences` [C3] Auto test: no — metadata
- [x] T022 [US5] Implement `custom/Espo/Modules/NonprofitEspocrm/Classes/ConsoleCommands/CopyUserActivityCompetences.php` (`Espo\Core\Console\Command`): dry-run default + apply flag; ORM only; Volunteer/Employee Contacts with `linkedUserId` [C6] Auto test: yes — T020
- [x] T023 [US5] `ddev exec php command.php copyUserActivityCompetences` dry-run then apply on DDEV **after** prod dump import [C4] Auto test: no — operational
- [x] T024 [US5] Set `"notStorable": true` on User `activityCompetences` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/User.json`; `ddev exec php command.php rebuild` (soft rebuild may leave leftover User column; copy can still read it) [C4] Auto test: no — rebuild; only after T023
- [x] T025 [US5] Local wipe+prod dump **done** (owner 2026-09-16). Production copy/drop **Skip** until named; record in `.specify/progress/019-implement-contact-first-crm-user.md` [C1] Auto test: no — gate

**Checkpoint**: Local DDEV has Contact as the stored competence list (prod data). Production CRM untouched.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: DDEV verify, 004 owner UAT artifact, 003 reminder, no push.

- [x] T026 `ddev exec vendor/bin/phpunit tests/unit/Espo/Modules/NonprofitEspocrm` (and PHPStan if the project job is quick) [C4] Auto test: yes — run the tests from T010/T017/T020
- [x] T027 [P] Write English `specs/004-contact-first-crm-user/checklists/owner-user-tests.md` aligned with quickstart V1–V5 (local only; prod Skip) [C3] Auto test: no — UAT script
- [x] T028 [P] Append `.specify/progress/018-tasks-contact-first-crm-user.md` (or implement log when implement runs); remind 003 `checklists/owner-user-tests.md` U1–U6 still required **after 004 closes** [C2] Auto test: no — handoff
- [x] T029 Confirm git push and production rebuild/copy were **not** done [C1] Auto test: no — gate

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Immediate
- **Foundational (Phase 2)**: After Setup; BLOCKS US1–US5 code
- **US1 (Phase 3)**: After Phase 2
- **US2 (Phase 4)**: After Phase 2; T014 touches same `UserContactProfileSync.php` as T011 — do T014 **after** T011
- **US3 (Phase 5)**: After T003; T018 after T017; live DDEV eligibility after T023
- **US4 (Phase 6)**: Independent of US1–US3 (verify-only)
- **US5 (Phase 7)**: After T003/T009; T024 after T023; T023 after T022
- **Polish**: After desired stories

### User Story Dependencies

- **US1 (P1)**: After Foundational — create-user flow
- **US2 (P1)**: After US1 sync rewrite (same Tools class)
- **US3 (P1)**: After Contact field exists; copy (T023) before trusting live data
- **US4 (P1)**: Independent keep-path
- **US5 (P2)**: Copy + notStorable; wipe/prod skipped

### Parallel Opportunities

- T004 / T005 with T003 (different files)
- T012 with T011 (clientDefs vs PHP) after T007 exists
- T015 / T016 after T014 starts (different files)
- T021 with T020 (metadata vs test)
- T027 / T028 with T026 notes
- US4 (T019) anytime after Phase 1

### Parallel Example: Foundational

```bash
Task: "User.json sourceContactId"
Task: "Contact i18n createCrmUser + activityCompetences"
```

### Parallel Example: US2

```bash
Task: "User detail linked Contact layout"
Task: "ShiftPlanningInstaller Contact panel provision"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1–2 metadata + DDEV rebuild
2. T010 fail → T011 sync → T012–T013 User modal
3. STOP: DDEV V1/V2 (create with/without checkbox)

### Incremental Delivery (all local)

1. US1 → Contact-first login
2. US2 → Contact competences UI + User mirror
3. US5 copy on current DDEV (no wipe) → then US3 planner on live rows
4. US4 verify delete-inactivate
5. US5 notStorable + rebuild
6. Polish PHPUnit + 004 owner-user-tests (003 UAT still later)

### Parallel Team Strategy

One agent, sequential on shared PHP files (`UserContactProfileSync.php`). Parallel only [P] rows.

---

## Notes

- [P] = different files, no incomplete-task dependency
- Do not edit `application/`
- Do not change Contact Formula (`Resources/metadata/formula/Contact.json`) unless a bug in monthlyHours/status appears
- Do not change User `isOccasional` storage (out of scope)
- Do not `git commit` / `git push` unless the owner asks
- Suggested models are proposals, never a launch order
- After 004 implement-done: owner UAT for **004**, then remind **003** U1–U6

## Complexity owner table (constitution XV)

| ID | Work | C | Proposed model | test | Why |
|----|------|---|----------------|------|-----|
| T001 | Confirm 004 artifacts | 1 | Auto | no | Mechanical |
| T002 | Local-only / no wipe | 1 | Auto | no | Gate |
| T003 | Contact entityDefs competences + checkbox | 4 | Auto | no | Copy existing User multiEnum |
| T004 | User `sourceContactId` | 3 | Auto | no | notStorable flag |
| T005 | Contact i18n | 3 | Auto | no | Three JSON files |
| T006 | Contact detail layout | 3 | Auto | no | Existing panel |
| T007 | Contact Dynamic Logic | 4 | Auto | no | Same as weeklyHours |
| T008 | recordDefs `__APPEND__` | 2 | Auto | no | Docs one-liner |
| T009 | DDEV rebuild | 3 | Auto | no | Constitution X |
| T010 | PHPUnit sync (duplicate Contact) | 6 | Auto | yes | Mock EM; named bugs |
| T011 | UserContactProfileSync rewrite | 8 | claude-opus-5-thinking-high | yes | Duplicate Contact + own vs identity |
| T012 | clientDefs Contact edit view | 3 | Auto | no | One JSON key |
| T013 | Contact edit.js User modal | 7 | claude-opus-5-thinking-high | no | after:save + modal attributes |
| T014 | Load/write competences on existing Contact | 5 | Auto | no | Extend VOLUNTEER_FIELDS |
| T015 | User shows linked Contact | 4 | Auto | no | notStorable link + layout |
| T016 | Installer Contact panel | 4 | Auto | no | Mirror existing User provision |
| T017 | PHPUnit planner read Contact | 5 | Auto | yes | Ignores User column |
| T018 | `getUserCompetences` ORM Contact | 6 | Auto | yes | Indexed `linkedUserId` |
| T019 | Keep InactivateLinkedContacts | 2 | Auto | no | Verify-only |
| T020 | PHPUnit copy mapper | 5 | Auto | yes | Migrator |
| T021 | consoleCommands.json | 3 | Auto | no | New metadata file |
| T022 | Copy console command | 6 | Auto | yes | ORM + dry-run |
| T023 | DDEV copy apply (no wipe) | 4 | Auto | no | Operational |
| T024 | User competences notStorable + rebuild | 4 | Auto | no | After copy |
| T025 | Skip wipe + skip prod | 1 | Auto | no | Gate |
| T026 | DDEV PHPUnit | 4 | Auto | yes | Run suite |
| T027 | 004 owner-user-tests.md | 3 | Auto | no | Constitution XVII |
| T028 | Progress + 003 reminder | 2 | Auto | no | Handoff |
| T029 | No push / no prod | 1 | Auto | no | Gate |
