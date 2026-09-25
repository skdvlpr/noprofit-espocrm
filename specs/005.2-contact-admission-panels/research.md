# Research: why the contact still had no PDF or board

Docs opened this turn:

- https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
- https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md

## Decision: Put pdfPreview in the Contact bottom layout

**Decision**: Add `pdfPreview` to `layouts/Contact/bottomPanelsDetail.json`, same as Food Parcel. Register `bottomPanelsEdit` the same way if the edit card should show it.

**Rationale**: Contact `app.layouts` points `bottomPanelsDetail` at the module file. That file listed only opportunities, cases, and payments. Espo then uses that file instead of building panels from clientDefs. Lead has no such override, so the Lead preview worked. Food Parcel’s layout file contains `pdfPreview`.

**Alternatives considered**: Remove the Contact bottomPanelsDetail override. That would drop the payments tab break.

## Decision: Copy from converted Lead on rebuild

**Decision**: A rebuild action finds Converted leads with `createdContactId`, and if the contact is Associato, copies the six board fields and `admissionForm` when the contact value is empty.

**Rationale**: Native convert copies matching fields only at convert time. People converted before the Contact columns existed still have empty board fields. The lead still holds the answers and the PDF.

**Alternatives considered**: Ask staff to convert again. They already have a contact and a user.

## Reviewed and not a convert bug

Convert of name, type, address, tax code, birth, and CRM user already works. This correction does not change that.
