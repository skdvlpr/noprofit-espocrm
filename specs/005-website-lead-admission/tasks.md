# Tasks: Website admission leads and socio PDF

**Input**: Design documents from `/specs/005-website-lead-admission/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Unit tests for board permission, newsletter line, and PDF skip rules (constitution XIV). No HTML snapshot of the whole template.

**Organization**: US1 card, US2 integration user, US3 volunteer shape, US4 convert fields (native), US5 PDF, US6 board, US7 move PDF.

**Models**: Every row is **Auto** (this agent). Owner ordered implement immediately after the tasks commit, without a second model. Do not launch Fable or another advanced model.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [ ] T001 Confirm feature dir `specs/005-website-lead-admission` and that `application/` and `safehouse-community-site` stay untouched. [C1] Auto. test: no — inventory only.

## Phase 2: Foundational

- [ ] T002 Add Lead board fields and `admissionForm` file field in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Lead.json` (`admissionOutcome` and `admissionFeePaid` enums with `""`, `EmptyStringToNull`). Add the same `admissionForm` file field on Contact in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Contact.json`. Do not add `ItalianFiscalCode` to Lead. Set Lead `taxCode` maxLength 32. [C4] Auto. test: no — metadata; behaviour is tested via hooks.

## Phase 3: User Story 1 — Socio card (P1)

**Goal**: Fiscal code and birth fields visible; not required; bad 16-char code saves.

**Independent Test**: Detail and edit show the four fields beside type. `AAAAAA00A00A000A` saves.

- [ ] T003 [US1] Remove `layoutAvailabilityList` from Lead `taxCode`, `birthDate`, `birthPlace`, `birthProvince` and place them on `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Lead/detail.json` and `edit.json` beside `contactType`. Leave volunteer extras off those layouts. [C3] Auto. test: no — layout JSON.
- [ ] T004 [P] [US1] Confirm Italian labels in `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/Lead.json` (Codice Fiscale, Data di nascita, Luogo di nascita, Prov. di nascita) and add board labels in `en_US`, `it_IT`, and `ru_RU` Lead i18n. [C2] Auto. test: no — labels.

## Phase 4: User Story 6 — Board block (P1)

**Goal**: Exclusive outcome and fee; only admin or Role name Member may change them; hidden unless Associato.

**Independent Test**: Approvata then Respinta leaves only Respinta. Non-member save of the board is Forbidden.

- [ ] T005 [US6] Show panel `admissionBoard` only when `contactType` has `MemberContact` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Lead.json` (Dynamic Logic `has`). Put the five board fields on that panel in the Lead layouts. [C4] Auto. test: no — metadata.
- [ ] T006 [US6] Implement `custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/AdmissionBoard.php` and `Hooks/Lead/RestrictAdmissionBoard.php`: Forbidden unless logged-in admin or a Role **named** `Member`. Website user must still create a Lead with those fields untouched. [C6] Auto. test: yes — `tests/unit/Espo/Modules/NonprofitEspocrm/AdmissionBoardTest.php` catches a non-member write and an admin write. Cite hooks.md, acl.md, roles-management.md.

## Phase 5: User Story 5 — PDF (P1)

**Goal**: One admission PDF for Associato, newsletter box from the description line, blank signatures.

**Independent Test**: Description `Newsletter: acconsente` → consent yes. Volunteer Lead → no PDF. Second save → still one file.

- [ ] T007 [P] [US5] `custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/NewsletterConsent.php` parses `Newsletter: acconsente` / `non acconsente` / missing. Unit test `tests/unit/Espo/Modules/NonprofitEspocrm/NewsletterConsentTest.php`. [C3] Auto. test: yes — the three strings are the bug if swapped.
- [ ] T008 [US5] PDF data loader `custom/Espo/Modules/NonprofitEspocrm/Classes/Pdf/AdmissionNewsletterLoader.php` and `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/pdfDefs/Lead.json`. [C4] Auto. test: no — loader is a thin call into T007.
- [ ] T009 [US5] Seed the Lead PDF template (paper module HTML, fixed letterhead, blank signature lines, president label Matteo Grossi) via a small installer invoked from rebuild or first PDF sync, id stored without secrets. Template body in module code, not a one-off DB-only edit. [C7] Auto. test: no — visual match is owner UAT; unit-testing the whole HTML is forbidden.
- [ ] T010 [US5] `custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/AdmissionPdf.php` and `Hooks/Lead/SyncAdmissionPdf.php` (`lateAfterSave`): generate with `Espo\Tools\Pdf\Service`, replace `admissionForm`, skip Volunteer-only, skip Converted, skip the save that only stores the new file. [C8] Auto. test: yes — decision helper in `AdmissionPdf` (shouldGenerate) covered in `tests/unit/Espo/Modules/NonprofitEspocrm/AdmissionPdfPlanTest.php`. Cite printing-to-pdf.md, pdf-defs.md, hooks.md.

## Phase 6: User Story 3 — Volunteer shape (P2)

**Goal**: Name, email, phone, description, type Volunteer saves with no PDF and no required birth fields.

- [ ] T011 [US3] Do not set `required` on address, `taxCode`, or birth fields in Lead entityDefs. Volunteer Lead must not satisfy `AdmissionPdf::shouldGenerate`. Covered by T010 test. [C2] Auto. test: no — assertion lives in T010.

## Phase 7: User Story 4 and 7 — Convert (P1)

**Goal**: Native copy of type, address, birth, fiscal code, and file. Then remove the file from the Lead only when the Contact is Associato. Invalid fiscal code still fails on Contact.

- [ ] T012 [US4] Rely on native convert for same-named fields. Do not edit `application/Espo/Modules/Crm/Tools/Lead/ConvertService.php`. Do not create a Contact from the Lead hook. [C2] Auto. test: no — absence of a create-Contact call.
- [ ] T013 [US7] On Converted Lead with `createdContactId` and Contact type including `MemberContact`, clear Lead `admissionForm` and delete that attachment. Do not generate a replacement. If Contact is not Associato, leave the Lead file. Logic in `AdmissionPdf` / `SyncAdmissionPdf`. [C7] Auto. test: yes — `shouldReleaseFromLead` in `AdmissionPdfPlanTest.php`.

## Phase 8: User Story 2 — Integration user (P1)

- [ ] T014 [US2] On DDEV, confirm the existing website API user can create a Lead with the socio payload. If Lead create is missing, grant it on that user’s existing role only (no new user, no secrets in git, no board-field grant). Record the result in `.specify/progress/` without credentials. [C5] Auto. test: no — environment role; hook tests cover board refusal.

## Phase 9: Polish

- [ ] T015 [P] Write `specs/005-website-lead-admission/checklists/owner-user-tests.md` aligned with `quickstart.md`. [C2] Auto. test: no.
- [ ] T016 `ddev exec vendor/bin/phpunit` for the new tests, `ddev exec vendor/bin/phpstan analyse -c phpstan.neon` on the new PHP files, `ddev exec php command.php rebuild`. Inactivate Google Calendar Sync, Overlay, and Send Push Reminders if rebuild reactivated them (ORM update, no hardcoded table names). [C3] Auto. test: no — the tests are the task.
- [ ] T017 Append `.specify/progress/` implement handoff. Do not push. Do not apply prod. [C1] Auto. test: no.

## Dependencies

- T002 before T003, T005, T010, T013
- T006 before board UAT
- T007 before T008 before T010
- T009 before T010
- T010 before T013
- T014 after T002 (payload must be accepted)
- T016 last

## Parallel

- T004 with T003
- T007 with T005

## MVP

User Story 1 (card) plus US5 (PDF) plus US6 (board). Convert move (US7) is required before the feature is handed to the owner, because the owner asked for it in the same spec.

## Model table

| Task | Work | C | Model | Why |
|------|------|---|-------|-----|
| T001 | Touch nothing outside the module | 1 | Auto | Inventory |
| T002 | Board + file metadata | 4 | Auto | Field defs |
| T003 | Layouts | 3 | Auto | JSON layout |
| T004 | i18n | 2 | Auto | Labels exist |
| T005 | Dynamic Logic panel | 4 | Auto | Same `has` pattern as Contact |
| T006 | Board hook | 6 | Auto | Role-by-name; unit test |
| T007 | Newsletter parse | 3 | Auto | Three strings |
| T008 | PDF loader | 4 | Auto | Thin wrapper |
| T009 | Template HTML | 7 | Auto | Must match the paper module; owner UAT |
| T010 | Generate/replace PDF | 8 | Auto | Loop guard; owner said this agent implements |
| T011 | Fields stay optional | 2 | Auto | Don’t add required |
| T012 | Don’t fork convert | 2 | Auto | Native copy |
| T013 | Release file from Lead | 7 | Auto | Must not regenerate |
| T014 | API user check | 5 | Auto | No secrets |
| T015–T017 | Checklist, tests, progress | 1–3 | Auto | Mechanical |
