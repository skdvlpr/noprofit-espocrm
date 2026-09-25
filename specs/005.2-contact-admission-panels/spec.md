# Feature Specification: Contact shows the admission board and PDF

**Feature Branch**: `005.2-contact-admission-panels`

**Created**: 2026-09-25

**Status**: Draft

**Input**: Correction of `005.1-admission-convert-fix`. After convert, the Associato contact still has no Consiglio Direttivo block and no PDF preview. Convert itself and user create are fine. The PDF and board answers must be visible on the contact, including people already converted.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The contact card shows the board and the PDF (Priority: P1)

Staff open an Associato contact that came from a lead. They see Consiglio Direttivo with the same answers as the lead, and they can open the admission PDF from the contact the same way as on the lead.

This includes contacts that were converted before this correction.

**Why this priority**: The convert already copies the person. Without the board and the PDF on the card, the membership file is missing.

**Independent Test**: Open a converted Associato contact. The board block is there. The PDF preview opens. The lead still has its PDF.

**Acceptance Scenarios**:

1. **Given** an Associato contact from a lead that had board answers and a PDF, **When** staff open the contact, **Then** they see Consiglio Direttivo with those answers and can open the PDF.
2. **Given** a contact converted before this correction, **When** staff open it after the correction, **Then** the board answers and the PDF are present without converting again.
3. **Given** a contact that is not Associato, **When** staff open it, **Then** there is no board block and no admission PDF.

### Edge Cases

- A converted lead that never had a PDF still gets one generated on the lead, and the contact shows that file.
- A volunteer-only contact is unchanged.
- Work stays on the local site. Production is not applied in this correction.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: An Associato contact shows a Consiglio Direttivo block with meeting date, outcome, members’ book number, fee paid, receipt number, and newsletter choice.
- **FR-002**: That contact shows the admission PDF preview and an open-PDF action.
- **FR-003**: Already converted Associato contacts receive the board answers and the PDF from their lead without a second convert.
- **FR-004**: The lead keeps its PDF.
- **FR-005**: Non-Associato contacts do not show the board or the admission PDF.

### Key Entities

- **Lead**: Source of board answers and the generated PDF.
- **Contact**: Must show the same board answers and PDF when Associato.
- **Admission PDF**: One generated form, visible on both records.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On local DDEV, one converted Associato contact shows all six board answers matching the lead, and the PDF opens from the contact.
- **SC-002**: A contact converted before this correction shows the same after rebuild, without converting again.
- **SC-003**: A volunteer-only contact has neither the board block nor the PDF preview.

## Assumptions

- Convert of the person and CRM user already works. This correction only makes the board and PDF visible on the contact.
- Italian section titles from 005.1 stay.
- `006-availability-magic-links` stays paused.
- No production apply unless the owner asks.
