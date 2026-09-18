# Feature Specification: Contact-first volunteer/employee CRM user

**Feature Branch**: `004-contact-first-crm-user`

**Created**: 2026-09-15

**Status**: Implemented locally; **owner UAT failed** on create-CRM-user (2026-09-17). Repair + remaining UX lives in [`../004.1-repair-create-crm-user/spec.md`](../004.1-repair-create-crm-user/spec.md). Owner will close **004 and 004.1 together** after 004.1 UAT Pass.

**Input**: Owner emergency feature (003 Google standalone implemented locally;
owner UAT for 003 **deferred** until 004 closes). Reverse today’s
User→Contact volunteering flow so the Contact is the person record; optionally
create a CRM login; store shift competences on the Contact (User may still
show notStorable mirrors); planner reads Contact; keep “delete user →
contact Inactive”.

Owner 2026-09-15: Q1 = **A** (keep dedicated CRM-user link). User-form mirrors
allowed (same pattern as hours/dates today). 003 closing tests after 004.

Research cite (Espo native):
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/customize-standard-fields.md

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Create volunteer/employee from Contact (Priority: P1)

A staff member creates a Contact with type Volunteer or Employee. They fill
person and profile fields on that Contact (hours, dates, occasional flag,
competences, fiscal code, …). A checkbox **Create CRM user** is visible and
**on by default**. If they leave it on, the User create screen opens with
name, email, and phone copied; they finish login fields (username, roles,
access email). If they turn it off, only the Contact is saved (person
without a login).

**Why this priority**: Today the staff member starts from User; the Contact
is a side effect. The owner wants the person record to be the start.

**Independent Test**: On local DDEV, create a Volunteer Contact with the
checkbox on; complete the User screen; both records exist, profile fields
are stored on the Contact; User may show the same values as mirrors.

**Acceptance Scenarios**:

1. **Given** staff create a Contact with type Volunteer or Employee, **When**
   the create screen is open, **Then** they see **Create CRM user** checked
   by default.
2. **Given** that checkbox is on, **When** they save the Contact, **Then**
   they get a User create screen with first name, last name, email, and
   phone already filled from the Contact.
3. **Given** that checkbox is off, **When** they save the Contact, **Then**
   no User is created and the Contact is still a valid Volunteer/Employee.
4. **Given** Contact type is not Volunteer and not Employee, **When** they
   create the Contact, **Then** the create-user checkbox is not offered.

---

### User Story 2 - Contact stores the person profile; User may mirror (Priority: P1)

Contact is the stored person profile (dates, hours, extra, fiscal code, birth
data, occasional flag, **competences**). The User screen MAY still show those
fields as **mirrors** (loaded from the linked Contact, not a second stored
copy) — the same pattern already used for hours/dates. Name, email, and phone
MAY stay stored on both. The User screen shows the linked Contact; the
Contact screen shows the linked User (dedicated CRM-user link).

**Why this priority**: Competences must live on Contact so the planner can
read them there; mirrors keep the User screen usable without a second
database copy.

**Independent Test**: Change competences on the Contact; User mirror (if
shown) matches after refresh; planner uses the Contact list. After
migration, User has no independent stored competence list.

**Acceptance Scenarios**:

1. **Given** a User linked to a Volunteer Contact, **When** staff change
   competences on the Contact, **Then** the planner follows the Contact
   list (not a leftover User-only store).
2. **Given** the same pair, **When** they open the Contact, **Then** they
   see the volunteer/employee panel including competences.
3. **Given** a Contact that is not Volunteer or Employee, **When** they
   open it, **Then** competences are hidden.
4. **Given** User still shows profile fields, **When** a reviewer inspects
   storage, **Then** those User fields are mirrors of Contact, not a
   second competence column after the migration step.

---

### User Story 3 - Shift planner uses Contact competences (Priority: P1)

Shift planning behaviour stays the same for staff and volunteers (who can
mark availability, who is eligible, empty list = all categories). The
planner reads the competence multi-select from the **Contact linked to that
User**, not from the User. Values stay the same list (meal distribution,
meal preparation, and the rest of that category list).

**Why this priority**: Planner is the reason competences cannot simply
disappear from User without a new home.

**Independent Test**: Set competences only on the Contact; User has none.
Planner eligibility and volunteer-stats match the Contact list. Empty
Contact competences still mean “all categories”.

**Acceptance Scenarios**:

1. **Given** a volunteer User whose Contact lists only meal distribution,
   **When** they open availability for a week, **Then** shifts that need
   other categories are blocked the same way as today when the User field
   had only that value.
2. **Given** the Contact competence list is empty, **When** they use the
   planner, **Then** they remain eligible for every category (today’s empty
   = all rule).
3. **Given** a User with no linked Volunteer/Employee Contact, **When** the
   planner needs competences, **Then** it does not invent User-only
   competences (treat as empty = all, or skip — same as a person with no
   profile).

---

### User Story 4 - Delete User still inactivates the Contact (Priority: P1)

Deleting a User does **not** delete the Contact. The Contact stays and
becomes Inactive. That rule MUST remain.

**Why this priority**: Owner confirmed current behaviour is correct.

**Independent Test**: Delete a volunteer User locally; Contact remains with
Inactive status; person data is not wiped.

**Acceptance Scenarios**:

1. **Given** a linked Volunteer Contact and User, **When** staff delete the
   User, **Then** the Contact still exists and status is Inactive.
2. **Given** that Inactive Contact, **When** staff open it, **Then** they
   can still read the person record.

---

### User Story 5 - Local rehearsal then production-safe competence move (Priority: P2)

Before dropping User competences in production: copy them onto the linked
Volunteer/Employee Contacts, prove the planner, then remove the User field.
First rehearsal is **local DDEV only**: wipe Users except admin and wipe
Contacts, copy a small sample from production (owner-gated), run the copy,
then remove User competences.

**Why this priority**: Production data must not lose competences. Local
wipe is destructive and must not run on production.

**Independent Test**: On DDEV after a sample copy, every sample volunteer
User’s old competence list equals the linked Contact list; planner uses
Contact; User field gone.

**Acceptance Scenarios**:

1. **Given** owner approval for local wipe, **When** the rehearsal runs,
   **Then** only the admin User remains among Users and Contacts are empty
   before the sample import.
2. **Given** sample volunteer/employee pairs from production on DDEV,
   **When** the copy step runs, **Then** Contact competences match what
   those Users had.
3. **Given** copy verified, **When** User competences become a Contact
   mirror (no independent User store), **Then** the planner still matches
   those people to the same shift categories.
4. **Given** production, **When** this feature is not yet approved to
   change production data, **Then** no wipe, no copy, and no field drop
   runs there.

---

### Edge Cases

- Help-seeker / other Contact types: no create-user checkbox.
- Volunteer Contact with create-user off: valid person, not in shift
  planner until a User is linked (planner still keys off Users).
- User created without going through Contact: out of the happy path; must
  not recreate the old “User save always writes a new Contact” loop for
  volunteer/employee if the person already exists.
- Empty competences = all shift categories (keep today’s rule).
- Name, email, phone may differ slightly between User and Contact after
  later edits; this feature does not require a live two-way sync of those
  three beyond the initial copy.
- Member contacts and Member role on User are **out of this feature’s
  create-user checkbox** (see Assumptions).
- Local wipe deletes **all** Contacts on DDEV including help-seekers; that
  is intentional for a clean rehearsal and MUST NOT run on production.
- Person identity: dedicated CRM-user link (`linkedUser`). Assigned User
  stays record owner (Espo **own** access), not the identity of the person.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Staff MUST create Volunteer and Employee person data on
  Contact, not on User.
- **FR-002**: On Contact create, if type is Volunteer or Employee, the
  screen MUST show **Create CRM user**, default **on**.
- **FR-003**: When that checkbox is on, after the Contact is saved the
  User create screen MUST open with first name, last name, email, and
  phone copied from the Contact.
- **FR-004**: When that checkbox is off, no User MUST be created.
- **FR-005**: Name, email, and phone MAY remain stored on both User and
  Contact. Other person-profile fields MUST be stored on Contact.
  User MAY display them as non-stored mirrors loaded from the linked
  Contact (including competences after migration).
- **FR-006**: Competences (the same multi-select used today on User for
  shift categories) MUST exist on Contact and MUST be visible only when
  type is Volunteer or Employee.
- **FR-007**: Shift planning MUST use those Contact competences for the
  User who is the volunteer/employee actor (via the dedicated CRM-user
  link). Empty list MUST keep today’s “all categories” meaning.
- **FR-008**: Deleting a User MUST leave the linked Contact in place and
  set it Inactive.
- **FR-009**: Contact MUST show the linked User; User MUST show the
  linked Contact.
- **FR-010**: Person identity MUST be the dedicated CRM-user link
  (Contact → User). Assigned User MUST NOT be the identity of the
  volunteer/employee (it remains “who owns this record” for access).
  The derived “is user” flag MAY stay hidden or remain as today.
- **FR-011**: Before removing User competences in production, existing
  volunteer/employee User competence values MUST be copied onto the
  linked Contacts and verified. Local DDEV rehearsal (wipe except admin,
  import a sample from production) MUST happen first and is owner-gated.
  Production wipe/import/drop MUST NOT run without an explicit owner
  approval for that exact action.
- **FR-012**: This feature MUST NOT change Google calendar, Prima Nota, or
  food-parcel identity rules except where they already read Contact.
- **FR-013**: Local verification MUST use DDEV. Production deploy remains
  approval-gated.

### Key Entities

- **Contact**: The person. Type Volunteer / Employee / others. Holds
  volunteer-employee profile, including competences after this feature.
  Status Active/Inactive. May link to a CRM User.
- **User**: The login. Name, email, phone, username, roles, teams. Linked
  to at most one person Contact for this flow.
- **Shift plan / availability**: Still about Users as actors; eligibility
  uses the linked Contact’s competences.
- **Competences**: Multi-select of shift categories (meal distribution,
  meal preparation, and the rest of that existing list). Empty = all.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A staff member can create a Volunteer Contact and a CRM
  login in one sitting in under three minutes, filling profile fields
  only on the Contact.
- **SC-002**: After migration, a reviewer finds the competence list for a
  volunteer stored on the Contact; the User has no independent stored
  competence list (a User mirror is allowed).
- **SC-003**: For a sample of at least five volunteer Users, planner
  eligibility after the move matches eligibility from the old User
  competence list (same categories allowed/blocked).
- **SC-004**: After deleting a volunteer User, the Contact is still
  findable and Inactive within one refresh.
- **SC-005**: Production competence copy does not run until the local
  rehearsal has been reported Pass.

## Assumptions

- Owner 2026-09-15: Q1 = **A** — keep dedicated CRM-user link; Assigned
  User is ownership only
  (https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md).
- Owner 2026-09-15: User MAY keep notStorable mirrors fetched from
  Contact (hours/dates already work this way; competences join that
  pattern after the move). Planner MUST still read Contact, not a User
  column.
- Owner 2026-09-15: 003 Google/WF owner tests are **deferred** until 004
  is closed; they remain required (U1–U6 on
  `specs/003-google-standalone/checklists/owner-user-tests.md`).
- 004 is the active feature. Plan/tasks/implement proceed here; 003 is
  implemented and committed locally (`9f8864c`), not UAT-closed.
- Today (research 2026-09-15): saving a User with Volunteer, Employee, or
  Member **role** creates or updates a Contact (`linkedUser` + often
  Assigned User = that User), copies profile fields Contact-ward, and
  loads them back onto User as non-stored mirrors. Competences exist
  **only on User** and the shift planner reads them from User. Deleting
  the User sets linked Contacts to Inactive. Occasional flag already
  mirrors Contact → User.
- Empty competences = all shift categories (keep).
- Name, email, phone may remain on both records; other profile fields
  are stored on Contact (User mirrors allowed).
- Member Contact / Member role on User is out of the new create-user
  checkbox; do not expand this feature to “create login from Member
  Contact” unless the owner asks.
- Shift planner keeps Users as invitees; it does not switch the actor to
  Contact records in this feature.
- Local wipe + sample import from production is a DDEV rehearsal only.
- Creating a User still uses Espo access-info / password behaviour
  (https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md).
