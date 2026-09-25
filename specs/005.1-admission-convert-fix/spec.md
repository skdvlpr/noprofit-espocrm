# Feature Specification: Associato convert keeps the admission PDF

**Feature Branch**: `005.1-admission-convert-fix`

**Created**: 2026-09-24

**Status**: Draft

**Input**: Correction of `005-website-lead-admission`. Converting an Associato lead must carry the admission data, including the PDF, onto the Contact and show it with the Consiglio Direttivo block. The PDF must stay visible. Volunteer and member section titles must be Italian.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Converted Associato shows the form and the board (Priority: P1)

Staff convert a lead whose type includes Associato. The new contact shows the same Consiglio Direttivo answers that were on the lead, and the admission PDF can be opened from that contact the same way it is opened from the lead.

The PDF also stays visible on the converted lead. Staff do not lose the file on either record.

**Why this priority**: The form is the document the board signs. A convert that hides it blocks the membership file.

**Independent Test**: Fill the board block and confirm the PDF on an Associato lead. Convert. The contact shows those board answers and the PDF. The lead still shows the PDF.

**Acceptance Scenarios**:

1. **Given** an Associato lead with board answers and a PDF, **When** staff convert it to a contact that is also Associato, **Then** the contact shows the board block and the PDF.
2. **Given** that convert, **When** staff open the lead again, **Then** the PDF is still there.
3. **Given** a lead that is not Associato, **When** staff convert it, **Then** the contact does not gain an empty board block or an admission PDF.
4. **Given** a contact that is not Associato, **When** staff open it, **Then** the board block and the admission PDF stay hidden.

---

### User Story 2 - Section titles are Italian (Priority: P1)

On the contact, the volunteer/employee section and the member section show Italian titles when the interface language is Italian. The same is true for the volunteering and member sections on the user card.

**Why this priority**: The rest of the card is Italian. English section titles look unfinished.

**Independent Test**: Set the interface to Italian. Open a contact and a user. The section titles are Italian, not “Volunteer / Employee”, “Member”, or “Volunteering”.

**Acceptance Scenarios**:

1. **Given** Italian language, **When** staff open a contact, **Then** the volunteer/employee section reads “Volontario / Dipendente” and the member section reads “Associato”.
2. **Given** Italian language, **When** staff open a user, **Then** the volunteering section reads “Volontariato” and the member section reads “Associato”.
3. **Given** English language, **When** staff open the same cards, **Then** the English titles remain.

### Edge Cases

- Convert of an Associato lead whose PDF was never generated still produces the PDF on the lead and shows that same PDF on the contact.
- A second save of the converted lead does not create a second PDF and does not clear the contact’s copy.
- Board answers already stored on the lead are the ones that appear on the contact. Empty board answers stay empty.
- This correction is checked on the local site only. Production is unchanged until the owner asks.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Converting an Associato lead copies the Consiglio Direttivo answers onto the contact: meeting date, outcome, members’ book number, fee paid, receipt number, and newsletter choice.
- **FR-002**: After that convert, the contact shows those answers in a Consiglio Direttivo block, and only when the contact is Associato.
- **FR-003**: After that convert, staff can open the admission PDF from the contact the same way they open it from the lead.
- **FR-004**: The PDF stays visible on the converted lead. Convert does not remove it.
- **FR-005**: A lead that is not Associato does not receive a board block or an admission PDF on the contact.
- **FR-006**: With the interface in Italian, the contact sections for volunteer/employee and member, and the user sections for volunteering and member, use the Italian titles in User Story 2.
- **FR-007**: Other lead fields that already copy on convert keep doing so. This correction does not change who may edit the board, and it does not convert leads by itself.

### Key Entities

- **Lead**: The application. Associato leads already have the board block and the PDF.
- **Contact**: The person created by convert. Must show the same board answers and the same PDF when they are Associato.
- **Admission PDF**: One generated form. It may be shown on both the lead and the contact. It is not deleted on convert.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In a local convert of one Associato lead, all six board answers on the contact match the lead, and the PDF opens from both records.
- **SC-002**: After that convert, the lead PDF is still present. A second open does not add another file.
- **SC-003**: A non-Associato convert leaves the contact without the board block and without an admission PDF.
- **SC-004**: In Italian, the four section titles in User Story 2 match the Italian wording. None of them stay in English.

## Assumptions

- “All lead data” means the fields convert already copies, plus the board answers and the PDF that were missing on the contact.
- The PDF is one generated file shown in both places. It is not rebuilt into a second document on convert.
- Italian titles are Volontario / Dipendente, Associato, and Volontariato, as above.
- Work stays on the local site. No production apply in this correction.
- `006-availability-magic-links` stays as it is. This correction does not implement magic links.
