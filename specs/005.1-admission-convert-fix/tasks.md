# Tasks: Associato convert keeps the admission PDF

**Input**: Design documents from `/specs/005.1-admission-convert-fix/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/convert-board-pdf.md, quickstart.md

**Tests**: Unit test that a converted Associato lead keeps its PDF (constitution XIV). Local DDEV only. No production apply. Do not implement `006-availability-magic-links` in this list.

**Models**: Every row is **Auto**. Feature complexity is 4. Do not downgrade the model.

**Docs cited by this work** (opened this turn):

- https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
- https://github.com/espocrm/documentation/blob/master/docs/development/api/i18n.md
- https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
- https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [X] T001 Confirm feature dir `specs/005.1-admission-convert-fix`. Do not edit `application/`. Do not start magic-link code. [C1] Auto. test: no.

## Phase 2: Foundational

- [X] T002 Copy the six board fields from `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Lead.json` onto `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Contact.json` with the same names and types (`admissionBoardDate`, `admissionOutcome`, `memberBookNumber`, `admissionFeePaid`, `admissionReceiptNumber`, `newsletterConsent`). Keep empty-enum handling. `admissionForm` is already on Contact. Cite fields.md. [C4] Auto. test: no — metadata; convert behaviour is native once names match.

## Phase 3: User Story 1 — PDF and board on the contact (P1)

**Goal**: An Associato convert shows the board answers and the PDF on the contact. The lead PDF stays.

**Independent Test**: Convert one Associato lead. Contact board values match. PDF opens on both. A volunteer-only contact has neither.

- [X] T003 [US1] Add panel `admissionBoard` labelled Consiglio Direttivo to `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/detail.json` and `edit.json`, with the six board fields. In `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/Contact.json`, show that panel and the PDF bottom panel only when `contactType` has `MemberContact` (Dynamic Logic `has`, same as Lead). [C4] Auto. test: no — layout.
- [X] T004 [US1] Reuse `client/custom/modules/nonprofit-espocrm/src/views/lead/panels/pdf-preview.js` for Contact: the request URL follows the record’s entity type. Add a Contact read route next to the existing Lead admission-pdf route in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/routes.json` (or the module routes file that already serves the lead PDF) and a controller action that streams that contact’s `admissionForm` inline. Register the bottom panel on Contact detail and edit in `clientDefs/Contact.json`. Cite printing-to-pdf.md. [C6] Auto. test: no — owner opens the preview locally.
- [X] T005 [US1] In `custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/AdmissionPdf.php`, convert of an Associato lead must copy the file id onto the contact when the contact is Associato, and must not clear or delete the lead file. `shouldDrop` stays false for converted leads. Extend `tests/unit/Espo/Modules/NonprofitEspocrm/AdmissionPdfPlanTest.php` so a converted Associato with a file is not dropped. Cite hooks.md. [C5] Auto. test: yes — that test file.

**Checkpoint**: US1 is done when the local convert in `quickstart.md` shows the PDF on both records.

## Phase 4: User Story 2 — Italian section titles (P1)

**Goal**: Italian UI shows Volontario / Dipendente, Associato, and Volontariato on the named sections.

**Independent Test**: Italian contact card and user card. English still shows the English titles.

- [X] T006 [P] [US2] In `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/Contact.json` `labels`, set `Volunteer / Employee` to `Volontario / Dipendente` and `Member` to `Associato`. In `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/User.json` `labels`, keep `Volunteering` as `Volontariato` and `Member` as `Associato`. Add the same Italian strings for the panel names `personnelVolunteerEmployee`, `personnelMember`, `volunteeringProfile`, and `memberProfile` so either lookup hits Italian. Do not change the English layout strings in `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/detail.json` or `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/User/detail.json`. Cite i18n.md. [C3] Auto. test: no — labels. Mirror the Italian strings into `ru_RU` Contact and User label maps so Russian does not fall through to English: `Волонтёр / Сотрудник`, `Член`, `Волонтёрство`.

## Phase 5: Polish

- [X] T007 [P] Write `specs/005.1-admission-convert-fix/checklists/owner-user-tests.md` from `quickstart.md`. [C2] Auto. test: no.
- [X] T008 `ddev exec vendor/bin/phpunit tests/unit/Espo/Modules/NonprofitEspocrm/AdmissionPdfPlanTest.php`, `ddev exec vendor/bin/phpstan analyse -c phpstan.neon` on touched PHP, `ddev exec php command.php rebuild`. Inactivate Google Calendar Sync, Overlay, and Send Push Reminders if rebuild reactivated them. [C3] Auto. test: no — the test is the task.
- [X] T009 Append `.specify/progress/` . Do not commit, push, or apply production unless the owner asks in that turn. [C1] Auto. test: no.

## Dependencies

- T002 before T003 and T005
- T003 before T004
- T006 can run in parallel with T003 after T001
- T008 after T005

## Parallel opportunities

- T006 with T002 (different files) after T001

## Implementation strategy

1. T002 and T003 so the contact card has the board block.
2. T004 and T005 so the PDF is visible on the contact and still on the lead.
3. T006 titles.
4. Local rebuild and the owner checklist. Production stays on the current deploy.

## Notes

- One PDF file, shown on both records.
- Native convert copies the new Contact fields. Do not edit `application/Espo/Modules/Crm/Tools/Lead/ConvertService.php`.
