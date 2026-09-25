# Research: Associato convert PDF and Italian section titles

**Date**: 2026-09-24

Docs opened this turn:

- https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
- https://github.com/espocrm/documentation/blob/master/docs/development/api/i18n.md
- https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
- https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md

## Decision: Copy the board by matching fields

**Decision**: Add the Lead board fields onto Contact with the same names and types: `admissionBoardDate`, `admissionOutcome`, `memberBookNumber`, `admissionFeePaid`, `admissionReceiptNumber`, `newsletterConsent`. Native convert copies them. Do not fork convert.

**Rationale**: Official convert copies a field only when the name and type match. Those fields exist on Lead only today, so the contact never receives them. `admissionForm` already matches and is the PDF file.

**Alternatives considered**:

- Read the answers only from the PDF. Staff could not correct or see them as fields on the contact.
- A custom convert action. Rejected. Core convert stays untouched.

## Decision: Show the board and the viewer on the contact

**Decision**: Contact detail and edit get a Consiglio Direttivo panel, visible only when `contactType` has MemberContact, plus the same PDF preview the lead already uses. The file field itself stays off the layouts. The preview reads the contact’s `admissionForm`.

**Rationale**: The lead already hides the raw file and shows Anteprima. The contact has the file attribute and no panel, so staff see nothing after convert.

**Alternatives considered**: A second generated PDF for the contact. The owner said one generated file is enough if both records can show it.

## Decision: Do not remove the lead PDF

**Decision**: Convert must not clear `admissionForm` on the lead and must not delete that file. `AdmissionPdfPlan::shouldRelease` is unused. `shouldDrop` already refuses when the lead is converted. Keep that, and do not add a clear step.

**Rationale**: The 005 contract said to remove the lead file after convert. The owner reversed that: the PDF may stay on the lead, and it must be visible on the contact.

## Decision: Italian section titles

**Decision**: Contact layout labels `Volunteer / Employee` and `Member` need Italian entries in Contact `labels`. User layout labels `Volunteering` and `Member` need the same in User `labels` (User Italian already has Volontariato and Associato; confirm they are the strings the layout uses). Titles: Volontario / Dipendente, Associato, Volontariato.

**Rationale**: Espo translates a layout label through the scope `labels` map for the user’s language. Contact Italian `labels` does not contain those keys, so the English layout text is what staff see.

**Alternatives considered**: Hardcoding Italian in the layout JSON. English users would then see Italian.
