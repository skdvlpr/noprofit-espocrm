# Implementation Plan: Website admission leads and socio PDF

**Branch**: `main` (feature dir `005-website-lead-admission`; no new git branch) | **Date**: 2026-09-22 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/005-website-lead-admission/spec.md`

## Summary

The site already creates an Associato Lead after the socio emails succeed. This CRM change makes that Lead readable (fiscal code and birth fields on the card), refuses to create a Contact at that moment, and prints one *Domanda di ammissione a socio* PDF. Staff (admin or Member role) fill the board section with exclusive choices. Manual convert copies the same-named fields and moves the PDF onto the Associato Contact. A Volunteer payload stays optional and gets no PDF. The website repository is not modified.

Path split (constitution I):

| Behaviour | Path | Why | Rejected |
|-----------|------|-----|----------|
| Show fiscal code and birth on the Lead card | **Metadata** layouts; drop `layoutAvailabilityList: []` | Fields already exist and the API already accepts them | New fields; forking the API |
| No Italian fiscal-code check on Lead | **Metadata** — do not attach Contact’s validator; raise max length so a bad code is still stored | Site check is 16 letters/digits only; Contact pattern is stricter and would drop the Lead after the email | Reusing `ItalianFiscalCode` on Lead |
| No Contact on Lead create | **Do nothing** — convert stays manual | Native convert is already the only Contact create | Workflow / formula `record\create` Contact |
| Copy type, address, birth, fiscal code | **Native ConvertService** (same name and type) | Already copies `contactType`, address, and these fields | Fork `ConvertService` |
| Board Approvata/Respinta and Sì/No | **Metadata** enum with one value (empty / one option) | An enum cannot store both | Two booleans plus custom UI |
| Who may edit the board block | **Code** beforeSave hook: admin or Role name `Member` | Field ACL would need a hardcoded role id; the website user must keep creating Leads | Editing core ACL; a new role |
| Hide board block unless Associato | **Metadata** Dynamic Logic `has` on `contactType` | Same pattern as Contact panels | CSS |
| Admission PDF | **Metadata** PDF Template + **Code** `lateAfterSave` using `Espo\Tools\Pdf\Service` | Formula `ext\pdf\generate` does not run for a record that does not exist yet, and this project does not use Advanced Pack workflows | Before-save formula; editing `application/` |
| Newsletter tick from description | **Code** PDF `DataLoader` (not a stored field) | Spec forbids a new newsletter field; the site already writes `Newsletter: acconsente\|non acconsente` | Parsing inside the template only |
| Move PDF on convert | **Native file-field copy** plus **Code** clear on the Lead when status is Converted and the Contact is Associato | Convert copies a file field; it does not remove the source | Fork `ConvertService` |
| Volunteer without address | **Metadata** fields not required | Required birth/fiscal code would reject both volunteer and incomplete staff edits | Making socio fields required |

MUST NOT edit `application/` or the website repo. Local DDEV until the owner names prod. No CRM email of the PDF.

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10.0.3. Local PHP via DDEV only.

**Primary Dependencies**: Lead/Contact metadata, layouts, i18n (`it_IT`, `en_US`, existing `ru_RU`), Dynamic Logic, PDF Templates, `Espo\Tools\Pdf\Service`, hooks (`BeforeSave`, `LateAfterSave`), native Lead convert, file field, Roles by name.

**Storage**: MariaDB via ORM. New Lead/Contact columns for five board values and one file field. No new entity type. One Template record seeded by id kept in module config, same pattern as shift email templates.

**Testing**: DDEV PHPUnit for board exclusivity (enum), hook refusal for non-member, newsletter line parse, “do not generate when not Associato”, “do not regenerate when Converted”. Owner UAT for the PDF looking like the paper module. No live mail.

**Target Platform**: DDEV `https://nonprofit-espocrm.ddev.site`.

**Project Type**: EspoCRM module NonprofitEspocrm.

**Performance Goals**: One PDF per Associato save. Replacement, not a stack of files.

**Constraints**: Do not add contact types. Do not put `ItalianFiscalCode` on Lead. Do not create a Contact when the Lead is created. Do not edit the website. Signatures stay blank lines. President line prints “Matteo Grossi”.

**Scale/Scope**: One template, one Lead card change, one board panel, convert already in 004.3.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Gate | Result |
|------|--------|
| I Native-first, docs opened this turn, GitHub cites | Pass. PDF Template + Pdf Service + Dynamic Logic + enum. Code only where formula cannot see a saved id or a role name. |
| II Extensions only | Pass. `custom/Espo/Modules/NonprofitEspocrm` and `client/custom` only if the board panel must be read-only for non-members. |
| III One active feature | Pass. Owner ordered this feature after committing 004.3. 004.3 UAT stays open and is not closed here. |
| VII No prod, no push unless asked | Pass. |
| X DDEV rebuild | Pass. Implement runs soft rebuild. |
| XIV Tests for hook/parser | Pass. No snapshot of the whole HTML template. |
| XV Models | Pass. Tasks propose Auto. Owner already ordered this agent to implement without a second model. |
| XVII Handshake | Pass after implement: Russian checklist, wait. |
| XVIII DDEV PHP | Pass. |

Post-design: same gates. No violation table.

## Project Structure

### Documentation (this feature)

```text
specs/005-website-lead-admission/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── socio-lead-payload.md
│   ├── admission-pdf.md
│   └── convert-pdf.md
└── tasks.md
```

### Source Code (repository root)

```text
custom/Espo/Modules/NonprofitEspocrm/
├── Resources/metadata/entityDefs/Lead.json
├── Resources/metadata/entityDefs/Contact.json
├── Resources/metadata/clientDefs/Lead.json
├── Resources/metadata/pdfDefs/Lead.json
├── Resources/layouts/Lead/detail.json
├── Resources/layouts/Lead/edit.json
├── Resources/i18n/{en_US,it_IT,ru_RU}/Lead.json
├── Hooks/Lead/RestrictAdmissionBoard.php
├── Hooks/Lead/SyncAdmissionPdf.php
├── Classes/Pdf/AdmissionNewsletterLoader.php
└── Tools/Admission/
    ├── AdmissionBoard.php
    ├── AdmissionPdf.php
    └── NewsletterConsent.php

client/custom/modules/nonprofit-espocrm/src/views/lead/record/edit.js
tests/unit/Espo/Modules/NonprofitEspocrm/Admission*.php
```

**Structure Decision**: Module metadata plus two Lead hooks and a PDF data loader. Contact gains the same file field so native convert copies it. Lead record edit only to set the board fields read-only when the user is not admin and has no Role named Member (the hook is the enforcement).

## Complexity Tracking

None.
