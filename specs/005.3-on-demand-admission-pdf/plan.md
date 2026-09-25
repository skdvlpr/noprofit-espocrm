# Implementation Plan: On-demand admission PDF (no stored file)

**Branch**: `005.3-on-demand-admission-pdf` | **Date**: 2026-09-25 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/005.3-on-demand-admission-pdf/spec.md`

## Summary

Stop storing the Associato admission PDF. Native Print to PDF already
builds from a Template at print time and returns bytes; it does not
write an Attachment unless the caller saves one. Lead preview already
renders live. Contact preview still streams `admissionForm`. Save hooks
still write a file so convert can copy it.

This correction: generate on GET for Lead **and** Contact; never write
`admissionForm`; convert copies board/person fields only; delete any
file convert still duplicates; rebuild deletes leftover files.

Path = **Code** for generate-on-GET and leftover delete. Path =
**Metadata** for a Contact PDF Template + `pdfDefs.Contact`. Formula
`ext\pdf\generate` rejected: it always returns an attachment id (stores
on disk). Advanced Pack not used.

`006` stays paused. Local DDEV only.

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10.0.3, module `NonprofitEspocrm`.
Local PHP **DDEV only**.

**Primary Dependencies**: `Espo\Tools\Pdf\Service::generate` (returns
`Result`, no Attachment), PDF Template per entity type, `pdfDefs`
DataLoader, existing admission preview routes, native Lead convert
(same field name/type; file fields copy via `getCopiedAttachment`),
RebuildAction.

**Storage**: MariaDB via ORM. **No new entity types.** `admissionForm`
file field may remain unused (layout already off). Leftover attachments
deleted. No hard rebuild.

**Testing**: DDEV PHPUnit: no generate-on-save; convert/rebuild clears
file ids; Contact render uses Contact entity type. Owner UAT on local
Associato without a lead. No production apply.

**Target Platform**: Local DDEV `https://nonprofit-espocrm.ddev.site`.

**Project Type**: EspoCRM module NonprofitEspocrm (`custom/` +
`client/custom/` only if preview URL already works).

**Performance Goals**: One Dompdf pass per preview or download. One
page HTML. MUST NOT write Attachment on that request.

**Constraints**: MUST NOT edit `application/`. MUST NOT fork
`ConvertService`. MUST NOT email the PDF. Food-parcel PDF unchanged.
Italian UI primary.

**Scale/Scope**: AdmissionPdf Tool, two GET actions, Lead lateAfterSave,
convert leftover clear, rebuild strip. One extra Template row
(`entityType` Contact).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I Official-docs / native-first | Pdf Service generate; Template entityType match; convert file copy is native; Formula generate stores — rejected | PASS — research |
| I Path stated | Path = Code (GET + leftover wipe). Metadata for Contact Template/`pdfDefs` | PASS |
| I No hardcoded SQL table names | ORM only | PASS |
| II Extensions only | `custom/` + existing client preview | PASS |
| III One active spec | Amendment `005.3` of parent `005` | PASS |
| IV Doc-backed planning | GitHub blob cites | PASS |
| VII / XVIII | Local DDEV; no prod apply | PASS |
| VIII Git | No commit/push in this command | PASS |
| XI Schema | No new columns; leftover files deleted, field may stay unused | PASS |
| XIV Tests | DDEV PHPUnit | PASS |

Post-design re-check: same gates. Design does not fork ConvertService,
does not use Formula generate, does not edit core.

## Project Structure

### Documentation (this feature)

```text
specs/005.3-on-demand-admission-pdf/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── live-admission-pdf.md
│   └── convert-no-pdf-file.md
└── tasks.md
```

### Source Code (repository root)

```text
custom/Espo/Modules/NonprofitEspocrm/
├── Tools/Admission/AdmissionPdf.php
├── Tools/Admission/AdmissionPdfPlan.php
├── Tools/Admission/Api/GetLeadAdmissionPdf.php
├── Tools/Admission/Api/GetContactAdmissionPdf.php
├── Tools/Admission/ContactAdmissionCopy.php
├── Hooks/Lead/SyncAdmissionPdf.php
├── Classes/Pdf/AdmissionNewsletterLoader.php
├── Resources/metadata/pdfDefs/Lead.json
├── Resources/metadata/pdfDefs/Contact.json   # add
├── Resources/metadata/app/rebuild.json
└── Core/Rebuild/StripStoredAdmissionPdfs.php  # replace file-copy backfill

client/custom/modules/nonprofit-espocrm/src/views/lead/panels/pdf-preview.js
  # already hits live GET; keep

tests/unit/Espo/Modules/NonprofitEspocrm/
├── AdmissionPdfPlanTest.php
└── ContactAdmissionCopyTest.php
```

**Structure Decision**: Same Tool renders Lead or Contact. Food parcel
stays its own service.

## Phase 0

See [research.md](./research.md).

## Phase 1

- [data-model.md](./data-model.md)
- [contracts/live-admission-pdf.md](./contracts/live-admission-pdf.md)
- [contracts/convert-no-pdf-file.md](./contracts/convert-no-pdf-file.md)
- [quickstart.md](./quickstart.md)

## Complexity Tracking

No constitution exception.
