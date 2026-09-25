# Tasks: On-demand admission PDF (no stored file)

**Input**: Design documents from `/specs/005.3-on-demand-admission-pdf/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/live-admission-pdf.md, contracts/convert-no-pdf-file.md, quickstart.md

**Tests**: PHPUnit inside DDEV (constitution XIV). Owner UAT on local DDEV. Full `ddev exec bash bin/run-tests.sh` only before a later push. No production apply.

**Models**: Proposals only. Do not launch a stronger model until the owner names Launch or Replace per row. Feature complexity **5**. Highest task **C6** (live render on Contact + stop storing). Recommend **inherit / Auto** for implement.

**Docs to cite in code comments** (opened at plan):

- https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
- https://github.com/espocrm/documentation/blob/master/docs/development/metadata/pdf-defs.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/formula/ext.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
- https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
- https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
- https://github.com/espocrm/documentation/blob/master/docs/development/api.md
- https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
- https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
- https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-rebuild.md
- https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [X] T001 Confirm feature dir `specs/005.3-on-demand-admission-pdf`. Do not edit `application/`. Do not implement `006`. Do not apply production. [C1] Auto. test: no — inventory.

---

## Phase 2: Foundational

**Purpose**: Contact can be printed with the same HTML as Lead. Storage plan helpers describe “never store”.

**Checkpoint**: Contact Template + `pdfDefs` exist. Tests for “do not store on save” fail until US1 code.

- [X] T002 [P] Add `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/pdfDefs/Contact.json` with the same `AdmissionNewsletterLoader` as Lead. Cite pdf-defs.md. [C2] Auto. test: no — metadata.
- [X] T003 Extend `custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/AdmissionPdf.php` `ensureTemplate` to seed a second Template `entityType` Contact, name `Domanda di ammissione a socio (Contact)`, same body as Lead. Cite printing-to-pdf.md. [C4] Auto. test: no — seed; GET tests cover render.

---

## Phase 3: User Story 1 — Opening the card prints a fresh form (P1) 🎯 MVP

**Goal**: Associato Lead and Contact preview/download print from current fields. Save does not write `admissionForm`. Old Associati without a lead work.

**Independent Test**: Open Rossella contact: form loads, no file on the record. Change CF, refresh preview: new CF. Volunteer has no preview.

### Tests for User Story 1

> Write these FIRST. They MUST fail before T006.

- [X] T004 [US1] Rewrite `tests/unit/Espo/Modules/NonprofitEspocrm/AdmissionPdfPlanTest.php`: `shouldGenerate` is always false (no store on new, change, or missing file). `shouldDrop` still true only for leftover file on a non-Associato unconverted lead (cleanup path). Cite tests.md. [C4] Auto. test: yes — this file. Bug it would catch: save still stores a PDF.

### Implementation for User Story 1

- [X] T005 [US1] Change `custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/AdmissionPdfPlan.php` so storage generate is never requested. Keep a helper to detect leftover `admissionFormId` for wipe. Cite hooks.md. [C3] Auto. test: yes — T004.
- [X] T006 [US1] In `custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/AdmissionPdf.php` stop `generate()`/`saveEntity` of Attachment on `sync`. `render(Entity $record)` uses Lead vs Contact Template by entity type. `sync` may still clear leftover files (not write new ones). Cite printing-to-pdf.md and formula/ext.md (why Formula generate is not used). [C6] Auto. test: yes — T004 plus GET uses render. Why Auto: one existing Tool; Contact Template already T003.
- [X] T007 [US1] Change `custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/Api/GetContactAdmissionPdf.php` to stream `AdmissionPdf::render($contact)` like the Lead action. MUST NOT read `admissionFormId`. ACL read on the contact. Cite api.md and acl.md. [C5] Auto. test: no — owner UAT; Lead action already streams render.
- [X] T008 [P] [US1] Confirm `client/custom/modules/nonprofit-espocrm/src/views/lead/panels/pdf-preview.js` still hits the live GET URLs. No cache of a file id. Cite custom-views only if the file comment changes. [C1] Auto. test: no — already live URL.

**Checkpoint**: Preview works without a stored file. Convert may still copy an old file until US2.

---

## Phase 4: User Story 2 — Convert copies answers, not a document (P1)

**Goal**: After Associato convert, board answers are on the contact; `admissionForm` is empty on both records.

**Independent Test**: Convert Associato lead with board answers. Contact board matches. Neither has a file. Both previews open.

### Tests for User Story 2

> Write FIRST. MUST fail before T010. Same copy test file — do not parallel with T009.

- [X] T009 [US2] Extend `tests/unit/Espo/Modules/NonprofitEspocrm/ContactAdmissionCopyTest.php`: `fieldsToCopy` never includes `admissionForm`; board fields still copy when empty on contact. Add a small helper test that leftover file ids must be cleared (pure function if extracted). Cite tests.md. [C4] Auto. test: yes — this file.

### Implementation for User Story 2

- [X] T010 [US2] Update `custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/ContactAdmissionCopy.php` to never copy the file. In `AdmissionPdf::sync` / convert path, when Lead is Converted and both are Associato, clear `admissionForm` on Lead and Contact and `removeEntity` those Attachments (`SKIP_ALL` + skip PDF store). MUST NOT edit `application/Espo/Modules/Crm/Tools/Lead/ConvertService.php`. Cite sales-management.md and fields.md. [C5] Auto. test: yes — T009.

**Checkpoint**: Convert does not leave a PDF file. US3 still needed for records already converted.

---

## Phase 5: User Story 3 — Old stored forms are removed (P2)

**Goal**: Rebuild deletes leftover admission Attachments and nulls `admissionFormId`. Empty board fields may still copy from the converted lead.

**Independent Test**: After `ddev exec php command.php rebuild`, a contact that had `admissionFormId` no longer does; preview still opens.

- [X] T011 [US3] Change `custom/Espo/Modules/NonprofitEspocrm/Core/Rebuild/BackfillContactAdmissionFromLead.php` so it does not copy `admissionForm`. Keep empty board-field copy. Cite app-rebuild.md. [C3] Auto. test: no — board copy already unit-tested via T009.
- [X] T012 [US3] Add `custom/Espo/Modules/NonprofitEspocrm/Core/Rebuild/StripStoredAdmissionPdfs.php` and register it in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/rebuild.json`. ORM: Lead and Contact with non-empty `admissionFormId`, clear field, remove Attachment. Cite commands.md and app-rebuild.md. [C5] Auto. test: no — rebuild on DDEV; do not SQL table names.

**Checkpoint**: Leftover files gone locally after rebuild.

---

## Phase 6: Polish

- [X] T013 [P] Write `specs/005.3-on-demand-admission-pdf/checklists/owner-user-tests.md` from `quickstart.md` (Italian labels; prod Skip). [C2] Auto. test: no — owner UAT script.
- [X] T014 `ddev exec vendor/bin/phpunit tests/unit/Espo/Modules/NonprofitEspocrm/AdmissionPdfPlanTest.php tests/unit/Espo/Modules/NonprofitEspocrm/ContactAdmissionCopyTest.php`, PHPStan on changed PHP, `ddev exec php command.php rebuild`. Inactivate Google Calendar Sync, Overlay, and Send Push Reminders if rebuild reactivated them (ORM). [C3] Auto. test: no — tests are T004/T009.
- [X] T015 Append `.specify/progress/` implement handoff. Do not commit, push, or apply production unless the owner asks in that turn. [C1] Auto. test: no.

---

## Dependencies & Execution Order

- Setup T001 → Foundational T002 / T003 (parallel) → US1 tests T004 then T005–T008 → US2 T009 then T010 → US3 T011 then T012 → Polish T013–T015.
- T004 before T005/T006. T009 before T010. T007 needs T003/T006.
- MVP = Phases 1–3 (live preview, no store). Convert leftover wipe is US2.

### Parallel example (Foundational)

```bash
Task: T002 pdfDefs/Contact.json
Task: T003 Contact Template seed in AdmissionPdf.php
```

Do not parallel T004 with T005.

---

## Implementation Strategy

1. Setup + Foundational (Contact Template).
2. US1 — live Contact GET, stop store — **MVP / owner can already open old Associati**.
3. US2 — convert wipe.
4. US3 — rebuild strip leftovers.
5. Polish + UAT. No prod until named.

## Notes

- Path = Code (GET + wipe). Formula `ext\pdf\generate` rejected (stores).
- Complexity table: all Auto. Owner: reply **keep / Launch / Replace** only if a row should not use inherit.
