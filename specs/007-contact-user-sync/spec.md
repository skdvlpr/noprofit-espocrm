# Feature Specification: Contact and user stay in step

**Feature Branch**: `007-contact-user-sync`

**Created**: 2026-09-25

**Status**: Draft

**Input**: Changing an Associato contact’s email failed on production (red error). Staff put a stub address on the contact. The linked user’s email did not follow. Volunteer and member fields on the user card must match the contact. Checked locally with the same person (Rossella / Fagioli). Specify only; no plan or implementation in this turn.

Local probe 2026-09-25 (same ids as production):

- Putting the user’s address onto the Associato contact **saved** locally. The pair may share that address.
- Changing the contact to a stub address **did not** change the user. The user kept the real address.
- Production save of that contact logged `Table 'member' doesn't exist`, not an email-uniqueness message. That is why the screen showed Internal server error while staff were editing the email.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Saving the contact does not fail on a retired member table (Priority: P1)

Staff edit an Associato contact (email or any other field) and save. The save succeeds. They do not see Internal server error.

**Why this priority**: Today a save can fail before any email rule runs, so staff cannot fix the address at all.

**Independent Test**: Open the same Associato contact used on production. Change a harmless field and save. It succeeds. No “member table” failure.

**Acceptance Scenarios**:

1. **Given** an Associato contact linked to a user, **When** staff save the contact, **Then** the save succeeds.
2. **Given** that save, **When** the retired member table is gone, **Then** nothing in the product still queries it.

---

### User Story 2 - Email and phone on the contact update the linked user (Priority: P1)

Staff change the email or phone on a contact that has a CRM user (Associato, Volunteer, or Employee). The linked user shows the same email and phone. Staff do not have to edit the user card.

**Why this priority**: The contact is the person record. The user login must keep the same channels.

**Independent Test**: On the local copy of Rossella, set the contact email to a stub, save, open the user: the user email is the stub. Set it back to the user’s previous address, save: both show that address.

**Acceptance Scenarios**:

1. **Given** an Associato contact linked to a user, **When** staff change the contact email and save, **Then** the user email matches.
2. **Given** a Volunteer or Employee contact linked to a user, **When** staff change the contact email or phone, **Then** the user matches (this already works for those types; it must keep working).
3. **Given** a contact with no linked user, **When** staff change the email, **Then** no user is created or changed.

---

### User Story 3 - The linked pair may share one email (Priority: P1)

The contact and its own linked user may use the same email. Staff are blocked only when **another** contact or **another** user already owns that address.

**Why this priority**: Staff thought the red error meant “the user already has this email”. That pair must be allowed.

**Independent Test**: Set the Associato contact to the linked user’s address. Save succeeds. Set it to an address used by a different person. Save is refused with a clear message, not Internal server error.

**Acceptance Scenarios**:

1. **Given** the linked user already has address A, **When** staff put A on that contact, **Then** save succeeds.
2. **Given** another contact already has address B, **When** staff put B on this contact, **Then** save is refused with a readable uniqueness message.
3. **Given** another user already has address C, **When** staff put C on this user, **Then** save is refused the same way.

### Edge Cases

- Two-way: changing the user email also updates the linked contact (today this is only for Volunteer/Employee; Associato must be included).
- Volunteer + Associato on one contact: still one email, copied once.
- Empty email on the contact does not wipe a required user login without a clear warning. [Default: empty contact email leaves the user email as-is.]
- Member profile fields already shown on the user (tax code, birth, join/leave, positions, notes) stay a live reflection of the contact. This spec does not add address onto the user card.
- Local only until the owner asks for production.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Saving a contact must not query the retired member table.
- **FR-002**: Email and phone on a contact with a linked CRM user copy to that user for Associato, Volunteer, and Employee.
- **FR-003**: Email and phone on that user copy back to the contact for the same types.
- **FR-004**: The contact and its linked user may share one email.
- **FR-005**: A different contact or a different user owning the address still blocks the save, with a normal validation message.
- **FR-006**: Volunteer and member fields already displayed on the user card continue to come from the contact.

### Key Entities

- **Contact**: Source of person data, including email and phone.
- **User**: Linked login. Channels follow the contact. Volunteer/member panels remain a reflection of the contact.
- **Retired Member table**: Must not be consulted.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On local DDEV, saving Rossella’s contact never shows Internal server error.
- **SC-002**: Changing her contact email updates the linked user on the next open, in under a minute.
- **SC-003**: Putting the user’s existing address on her contact succeeds. Putting another person’s address fails with a readable message.

## Assumptions

- Specify only in this turn. No `/speckit-plan`, tasks, or implementation until the owner asks.
- `005.2` and `006` are not implemented as part of this spec.
- Empty contact email does not clear the user email.
- Address stays on the contact card only.
