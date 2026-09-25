# Feature Specification: Restore contact–user channel and name sync

**Feature Branch**: `007.1-restore-channel-sync`

**Created**: 2026-09-25

**Status**: Draft

**Input**: Owner: locally Rossella’s email can be saved, but live sync
between the contact and the linked user has stopped. It used to copy the
person’s channels. Restore that behaviour, and also keep first name, last
name, every email, and every phone aligned. PDF and convert admission
data are fine; out of this spec.

Parent: [`../007-contact-user-sync/spec.md`](../007-contact-user-sync/spec.md)
(email save and Associato gap). Original channel rules:
[`../004.1-repair-create-crm-user/contracts/email-phone-sync.md`](../004.1-repair-create-crm-user/contracts/email-phone-sync.md)
and User Story 3 in
[`../004.1-repair-create-crm-user/spec.md`](../004.1-repair-create-crm-user/spec.md).
004.3 gave Associato the same CRM user as Volunteer, but the old channel
copy still ran only for Volunteer and Employee. 004.1 explicitly left
name off live sync; this correction adds name.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Emails and phones match again, both ways (Priority: P1)

A contact with a CRM user (Volunteer, Employee, Associato, or an allowed
pair) and that user stay one person for channels. Staff change any email
or any phone on the contact: after refresh the user has the same set
(every address, every number, which one is primary, opted-out, invalid).
Staff change them on the user: the contact matches.

A help-seeker, a contact with no user, a portal user, or a system user
does not rewrite anyone else.

**Why this priority**: This is the 004.1 behaviour that staff still
expect. Associato must be included because they now have a login
(Rossella).

**Independent Test**: On Rossella (Associato + linked user), add a second
email and a second phone on the contact, save, open the user: both sets
match. Change the user’s primary email; the contact follows. A
help-seeker email change leaves users untouched.

**Acceptance Scenarios**:

1. **Given** a linked Associato pair, **When** staff change the contact’s
   primary email and add another email, **Then** the user’s email set
   matches after one refresh.
2. **Given** the same pair, **When** staff add or change phones on the
   user, **Then** the contact’s phone set matches after one refresh.
3. **Given** a linked Volunteer or Employee pair, **When** the same
   edits are made, **Then** the sets still match (004.1 must not
   regress).
4. **Given** a help-seeker or a contact with no CRM user, **When** email
   or phone changes, **Then** no user is rewritten.

---

### User Story 2 - Name stays aligned (Priority: P1)

Prefix, first name, and last name on the contact and the linked user
stay the same after either card is saved.

**Why this priority**: Staff asked to restore “as it was” and add name.
Create already copies name; later edits currently drift.

**Independent Test**: Change first name on Rossella’s contact, save, open
the user: the first name matches. Change last name on the user; the
contact matches.

**Acceptance Scenarios**:

1. **Given** a linked pair, **When** staff change first name, last name,
   or prefix on the contact, **Then** the user shows the same after
   refresh.
2. **Given** the same pair, **When** they change those fields on the
   user, **Then** the contact matches.
3. **Given** a contact with no linked user, **When** the name changes,
   **Then** no user is created or rewritten.

### Edge Cases

- Several emails or phones: the whole set stays aligned, including which
  row is primary.
- Volunteer + Associato (or Employee + Associato): still one pair, one
  copy.
- Empty contact email does not clear the user’s login email. Empty extra
  rows may be removed on both if staff deleted them.
- The pair may share one email. Another contact or another user already
  using that address is still refused with a readable message, not
  Internal server error.
- Portal and system users are never written.
- Username is not copied. Assigned user on the contact is not stolen.
- Volunteer hours, competences, and member board/PDF fields are not part
  of this copy (they already have their own rules).
- Local only until the owner names production.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: After save of a linked Volunteer, Employee, or Associato
  contact, the linked user’s full email set and full phone set match the
  contact.
- **FR-002**: After save of that user, the contact’s full email set and
  full phone set match the user.
- **FR-003**: Prefix, first name, and last name copy both ways on the
  same linked pairs.
- **FR-004**: Help-seeker, unlinked contact, portal user, and system user
  do not trigger this copy.
- **FR-005**: The contact and its own linked user may share an email.
  A different person owning the address still blocks the save with a
  normal uniqueness message.
- **FR-006**: This copy must not create a user or a contact, must not
  change assigned user, and must not loop (one counterpart save that
  does not copy again).

### Key Entities

- **Contact**: Person record. Source staff usually edit.
- **User**: Linked login. Must show the same name and the same email and
  phone sets.
- **Email set / phone set**: Every row, not only the primary.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On local Rossella, after one contact save, the user shows
  the same name and every email and phone within one refresh (under a
  minute).
- **SC-002**: After one user save, the contact matches the same way.
- **SC-003**: A Volunteer pair still matches (no 004.1 regression). A
  help-seeker save does not change any user.
- **SC-004**: Putting the linked user’s existing email on that contact
  succeeds. Putting another person’s email fails with a readable
  message.

## Assumptions

- 004.1 copied the full email and phone sets, both ways, for Volunteer
  and Employee only. This correction keeps that and extends it to
  Associato, and adds prefix / first / last name.
- 007’s production “member table” save error is still real on production
  but is not the local gap: locally the email saves and the copy is what
  is missing. The uniqueness story in 007 FR-004/FR-005 stays.
- Specify only in this command. Plan and implementation wait for the
  owner.
- `005.2` PDF/board work stays closed for this spec. `006` stays paused.
- Empty primary email on the contact does not wipe the user’s email.
