# Implementation Plan: Contact shows the admission board and PDF

**Branch**: `005.2-contact-admission-panels` | **Date**: 2026-09-25 | **Spec**: [spec.md](./spec.md)

## Summary

005.1 put board fields on Contact and registered a PDF bottom panel in clientDefs. The Contact **bottomPanelsDetail layout** still lists only relationships, so the preview never appears (Food Parcel includes `pdfPreview` in that layout). Contacts converted earlier also have empty board columns. Add the preview to the layout and copy board answers plus the file from the converted lead.

Path = **Metadata** for the panel. Path = **Code** for a rebuild copy from Lead to Contact. Native convert stays. No Advanced Pack.

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10.0.3

**Primary Dependencies**: Layouts (`app.layouts`), rebuild action, existing admission PDF viewer

**Storage**: Contact board columns already exist locally after 005.1 rebuild. Copy values from Lead.

**Testing**: DDEV unit test for the copy rules. No production apply.

**Target Platform**: Local DDEV only

**Project Type**: Espo extension

**Constraints**: Do not edit `application/`. Do not clear the lead PDF. No production apply.

## Constitution Check

| Gate | Result |
|------|--------|
| I. Docs this turn | Pass. app-layouts.md, fields.md, printing-to-pdf.md, commands.md |
| II. Extensions only | Pass |
| VII. No prod apply | Pass |
| XI. No hard rebuild | Pass. Copy is a rebuild action after columns exist |

## Project Structure

```text
specs/005.2-contact-admission-panels/
custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Contact/bottomPanelsDetail.json
custom/Espo/Modules/NonprofitEspocrm/Core/Rebuild/BackfillContactAdmissionFromLead.php
```

## Complexity Tracking

No exception.
