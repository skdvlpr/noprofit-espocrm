# Feature Specification: On-demand admission PDF (no stored file)

**Feature Branch**: `005.3-on-demand-admission-pdf`

**Created**: 2026-09-25

**Status**: Draft

**Input**: Owner: do not keep an admission PDF on disk. Rebuild it every
time staff open the card or download the form. Do not copy a document
from Lead to Contact. Convert may still copy person and board answers.
If convert still attaches a generated file, delete that copy. Existing
Associati without a website lead must see the same form from the contact
card. Parent: [`../005-website-lead-admission/spec.md`](../005-website-lead-admission/spec.md)
and [`../005.2-contact-admission-panels/spec.md`](../005.2-contact-admission-panels/spec.md).

Cite:

- https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
- https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/formula/ext.md

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Opening the card prints a fresh form (Priority: P1)

Staff open an Associato lead or an Associato contact, including people
who were never a website lead. The admission form appears in the preview
and opens as a download. The page shows the **current** name, address,
fiscal code, board answers, and newsletter choice. Empty fields stay
blank. Saving the card does not leave a PDF file on that record.

**Why this priority**: Stored files are why old Associati have a blank
preview, and they waste disk. The form is already an HTML printout.

**Independent Test**: Open Rossella (Associato, no website lead). The
form opens. Change the fiscal code, save, refresh the preview: the new
code is on the page. The contact has no admission file attached.

**Acceptance Scenarios**:

1. **Given** an Associato contact with no attached admission file,
   **When** staff open the card, **Then** the form preview loads from
   the contact’s current fields.
2. **Given** an Associato lead, **When** staff open the card or download
   the form, **Then** the preview matches the lead’s current fields and
   no file is stored on the lead.
3. **Given** staff change a printed field (name, address, fiscal code,
   or a board answer) and save, **When** they open the preview again,
   **Then** the form shows the new value without a second convert.
4. **Given** a volunteer-only contact or lead, **When** staff open it,
   **Then** there is no admission form preview.

---

### User Story 2 - Convert copies answers, not a document (Priority: P1)

Staff convert an Associato lead. The contact receives the person fields
and Consiglio Direttivo answers. Neither the lead nor the contact keeps
an admission PDF file afterwards. Opening either card still shows the
form.

**Why this priority**: Native convert copies a file field by duplicating
the attachment. That is the leftover “cache” the owner does not want.

**Independent Test**: Convert a local Associato lead that has board
answers. Contact board matches. Neither record has an admission file.
Both previews still open.

**Acceptance Scenarios**:

1. **Given** an Associato lead with board answers, **When** staff
   convert to an Associato contact, **Then** the contact shows those
   answers and neither record has an admission file.
2. **Given** convert would have copied an old stored form, **When**
   convert finishes, **Then** that copied file is gone from both records.
3. **Given** a volunteer convert, **When** it finishes, **Then** no
   admission form appears on the contact.

---

### User Story 3 - Old stored forms are removed (Priority: P2)

After this correction is applied locally, leftover admission PDF files
from earlier versions are deleted so they no longer sit on disk. Staff
do not convert again. Opening the same cards still shows a live form.

**Why this priority**: Stops the old files from accumulating; does not
block the preview if US1 is done first.

**Independent Test**: A contact that used to have an admission file no
longer has one after rebuild. Opening the card still shows the form.

**Acceptance Scenarios**:

1. **Given** a lead or contact that still has an old admission file,
   **When** staff apply this correction (local rebuild), **Then** that
   file is gone and the preview still works.

---

### Edge Cases

- An Associato with empty board answers still gets a form: identity
  filled where present, board ticks empty.
- Newsletter marks follow the same rules as today (stored choice or the
  website line in the description).
- Convert of a non-Associato contact does not invent an admission form.
- Food-parcel PDF behaviour is unchanged.
- Work is local DDEV. Production is not applied in this correction.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Opening or downloading the admission form for an Associato
  lead MUST print from the lead’s current fields and MUST NOT save a
  file on the lead.
- **FR-002**: Opening or downloading the admission form for an Associato
  contact MUST print from the contact’s current fields and MUST NOT
  save a file on the contact. This includes contacts with no lead.
- **FR-003**: Saving an Associato lead or contact MUST NOT create or
  replace an admission PDF file.
- **FR-004**: Convert MUST still copy matching person and board answers
  onto the Associato contact. Convert MUST NOT leave an admission PDF
  file on the lead or the contact.
- **FR-005**: If convert duplicates an old admission file, that copy
  MUST be deleted on both records.
- **FR-006**: Leftover admission PDF files from earlier versions MUST
  be removed when this correction is applied locally.
- **FR-007**: Non-Associato records MUST NOT show the admission form
  preview.
- **FR-008**: The printed page MUST keep the current paper layout
  (letterhead, three declarations, GDPR ticks, blank signature lines,
  president line Matteo Grossi).

### Key Entities

- **Lead**: Source of website application fields and, after staff edit,
  board answers. No stored admission file.
- **Contact**: Source of the same printed fields after convert or for
  members entered by staff. No stored admission file.
- **Admission form**: A printout built at view/download time from the
  record that is open.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On local DDEV, staff open one Associato who never came
  from a website lead and see the form on the contact within a few
  seconds, with no admission file on that contact.
- **SC-002**: After one field change and save, the next preview on that
  same card shows the new value without convert.
- **SC-003**: After one Associato convert, neither the lead nor the
  contact has an admission file, and both previews still open.
- **SC-004**: A volunteer-only contact still has no admission preview.
- **SC-005**: After local rebuild, a contact that previously had an
  admission file no longer has one, and the preview still opens.

## Assumptions

- Board answers, Dynamic Logic, Italian titles, and convert of the
  person plus CRM user stay as in 005.1 / 005.2 / 004.3.
- Native Print to PDF is already on demand; this product’s stored
  admission file was extra. Formula `ext\pdf\generate` always writes an
  attachment, so it is not used for view/download.
- `006-availability-magic-links` stays paused.
- Channel sync (007.1) is out of this spec.
- No production apply unless the owner asks in that turn.
- Signatures stay blank lines. No email of the PDF.
