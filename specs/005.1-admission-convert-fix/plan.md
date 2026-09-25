# Implementation Plan: Associato convert keeps the admission PDF

**Branch**: `005.1-admission-convert-fix` | **Date**: 2026-09-24 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/005.1-admission-convert-fix/spec.md`

## Summary

Correction of 005. The Consiglio Direttivo answers exist only on Lead, so convert cannot place them on Contact. Contact already has an admission file field, but the card does not show it, and the lead preview is the only viewer. Put the same board fields on Contact so native convert copies them, show the board block and the PDF viewer on the Associato contact, and leave the lead PDF in place.

Section titles on the contact and user cards are English strings in the layouts. Italian labels for those exact strings are missing on Contact. Add them.

Path = **Metadata** for fields, layouts, and labels. A small code change only to stop any convert path from clearing the lead file, and to reuse the existing lead PDF viewer on Contact. Advanced Pack is not involved.

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10.0.3, module `NonprofitEspocrm`

**Primary Dependencies**: Native Lead convert (same field name and type), entityDefs, layouts, i18n, existing admission PDF viewer

**Storage**: New Contact columns for the board answers, created by rebuild from metadata. The PDF stays one file record shared by the lead and the contact.

**Testing**: DDEV. Unit test that convert does not clear the lead file and that an Associato contact is the only place the board block is for. No production apply.

**Target Platform**: Local DDEV `https://nonprofit-espocrm.ddev.site` only

**Project Type**: Espo extension under `custom/` and `client/custom/`

**Performance Goals**: One convert copies the board answers and shows the existing PDF. No second file.

**Constraints**: Do not edit `application/`. Do not delete the lead PDF on convert. Italian UI. No production apply. `006-availability-magic-links` is not part of this work.

**Scale/Scope**: Associato convert and four section titles.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Gate | Result |
|------|--------|
| I. Docs opened this turn | Pass. fields.md, i18n.md, printing-to-pdf.md, hooks.md. |
| I. Native convert, no core edit | Pass. Matching Contact fields, no ConvertService fork. |
| I. No hardcoded SQL | Pass. |
| II. Extensions only | Pass. |
| VII. No prod apply | Pass. Local only. |
| VIII. No commit in this command | Pass. |
| XI. New Contact columns | Pass. Rebuild creates them. No hard rebuild. |

Post-design: same gates. Sharing one PDF file between lead and contact is the owner’s choice and does not add a second stored form.

## Project Structure

### Documentation (this feature)

```text
specs/005.1-admission-convert-fix/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── convert-board-pdf.md
└── tasks.md
```

### Source Code (repository root)

```text
custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/Contact.json
custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/
custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/
custom/Espo/Modules/NonprofitEspocrm/Tools/Admission/AdmissionPdf.php
client/custom/modules/nonprofit-espocrm/src/views/lead/panels/pdf-preview.js
```

## Complexity Tracking

No constitution exception.
