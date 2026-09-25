# Tasks: Contact shows the admission board and PDF

**Input**: `/specs/005.2-contact-admission-panels/`

**Models**: Auto. Complexity 3.

**Docs**: app-layouts.md, fields.md, printing-to-pdf.md, commands.md

## Phase 1: Setup

- [X] T001 Confirm `specs/005.2-contact-admission-panels`. Do not edit `application/`. Do not start 006. [C1] Auto. test: no.

## Phase 2: User Story 1 (P1)

**Goal**: Associato contact shows board + PDF, including already converted people.

- [X] T002 [US1] Add `pdfPreview` to `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/bottomPanelsDetail.json`. Add `bottomPanelsEdit.json` with `pdfPreview` and map it in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/layouts.json`. Cite app-layouts.md. [C3] Auto. test: no — layout.
- [X] T003 [US1] Add `custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/ContactAdmissionCopy.php` that copies empty board fields and empty `admissionForm` from a Converted Associato lead onto its contact. Unit test `tests/unit/Espo/Modules/NonprofitEspocrm/ContactAdmissionCopyTest.php`. [C5] Auto. test: yes.
- [X] T004 [US1] Register rebuild action `custom/Espo/Modules/NonprofitEspocrm/Core/Rebuild/BackfillContactAdmissionFromLead.php` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/rebuild.json`. Cite commands.md. [C4] Auto. test: no — uses T003.

## Phase 3: Polish

- [X] T005 [P] Owner checklist `specs/005.2-contact-admission-panels/checklists/owner-user-tests.md`. [C2] Auto. test: no.
- [X] T006 PHPUnit for T003, PHPStan on new PHP, `ddev exec php command.php rebuild`. Inactivate Google Calendar Sync, Overlay Sync, Send Push Reminders if rebuild reactivated them. [C3] Auto. test: no.
- [X] T007 Progress note. No commit, push, or production apply unless asked. [C1] Auto. test: no.

## Dependencies

T002 and T003 after T001. T004 after T003. T006 after T004.
