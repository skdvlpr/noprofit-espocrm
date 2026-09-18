# Feature Specification: Repair Contact-first CRM user create

**Feature Branch**: `004.1-repair-create-crm-user`

**Created**: 2026-09-17

**Status**: Implemented locally; **owner UAT not accepted** (2026-09-18).
Correction lives in [`../004.2-create-user-review/spec.md`](../004.2-create-user-review/spec.md).
Owner closes **004, 004.1, and 004.2 together** after 004.2 UAT Pass.

**Input**: Owner UAT of `004-contact-first-crm-user` failed: Volunteer/Employee
Contact can be saved, but no CRM User is created and the Contact shows no
User link. Owner asked to **include the repair** in this amendment, plus the
intended create flow (side panel, access email with set-password link, email
and phone stay in sync). After the repair and a check of the new behaviour,
the owner will close **both 004 and 004.1** if they confirm everything is OK.

Parent: [`../004-contact-first-crm-user/spec.md`](../004-contact-first-crm-user/spec.md)
(competences, planner, delete→Inactive, identity link). This amendment does
**not** redo the competence copy.

Research cite (Espo native):
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Create Volunteer or Employee with a CRM login that actually appears (Priority: P1)

A staff member creates (or edits) a Contact of type Volunteer **or**
Employee. They see **Create CRM user** as a real checkbox, on by default,
as soon as that type is chosen — on the full create form and on the small
create form. Checking it opens a **side panel** for the User. Name, email,
and phone already filled on the Contact are copied into the matching User
fields and are **not editable in that panel** (staff can change them later
on the User record). Login fields (username, roles, teams, send-access)
remain editable. Saving produces **one Contact and one User**, linked both
ways. Unchecking the box closes the panel; saving then stores only the
Contact.

The same path works for an **existing** Volunteer/Employee Contact that has
no User yet (the failed UAT Contact must be recoverable without recreating
the person).

**Why this priority**: Without a working login create, 004’s person-first
flow is unusable. Owner UAT already showed a saved Employee with no User
and no pointer.

**Independent Test**: Create a Volunteer with the box on, complete the side
panel, save. Open the Contact: CRM user is set. Repeat for Employee. Repeat
from an existing Volunteer/Employee with empty CRM user. Uncheck the box:
Contact only.

**Acceptance Scenarios**:

1. **Given** staff open Contact create and set type Volunteer or Employee,
   **When** the form is still unsaved, **Then** they see **Create CRM user**
   checked by default (not a dash, not only after save).
2. **Given** that checkbox is on, **When** they click it (or it is already
   on), **Then** a side panel for the User appears with first name, last
   name, email, and phone copied from the Contact and those copied fields
   locked in the panel.
3. **Given** the side panel is open, **When** they save the Contact,
   **Then** a User exists, the Contact **CRM user** field points at that
   User, Assigned User on the Contact is still the staff owner, and the
   User shows the linked Contact.
4. **Given** the checkbox is off, **When** they save, **Then** no User is
   created and no side panel remains.
5. **Given** type is Help-seeker or other non Volunteer/Employee, **When**
   they create the Contact, **Then** the checkbox and side panel are not
   offered.
6. **Given** an existing Volunteer or Employee Contact with no CRM user,
   **When** staff edit it, turn the checkbox on, complete the side panel,
   and save, **Then** a User is created and linked (same rules as create).

---

### User Story 2 - Invite the new user with a set-password link, not a raw password (Priority: P1)

When creating that User, even if the password fields are empty, staff see
**Send access info** and it is on when an email is present. The person
receives an access email with username, a link to the CRM, and a **link to
set their own password**. The email MUST NOT contain a plaintext password.
The email looks like other Safe House volunteer emails: organisation logo
and clear layout (Italian primary).

**Why this priority**: Owner rejected sending a raw password as primitive
and unsafe. Native Espo already prefers an empty password plus access-info
link
(https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md,
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md).

**Independent Test**: Create Volunteer + User with email, empty password,
Send access info on. The mailbox shows a branded access email with a
set-password link and no password text. Opening the link lets them set a
password and sign in.

**Acceptance Scenarios**:

1. **Given** the side panel has an email copied from the Contact, **When**
   staff create the User with empty password, **Then** Send access info is
   visible and on by default.
2. **Given** Send access info is on, **When** the User is created, **Then**
   the person gets one access email whose body has a set-password (or
   password-change) link and does not include the account password.
3. **Given** that email, **When** a reviewer compares it to a shift
   availability email, **Then** it uses the same Safe House mark and a
   similarly readable layout.
4. **Given** the Contact has no email, **When** staff still want a login,
   **Then** Send access info cannot be used until an email exists (they
   must add an email or set a password themselves — no silent send).

---

### User Story 3 - Email and phone stay in sync between the person and the login (Priority: P1)

Once a Volunteer or Employee Contact is linked to a User, changing an
email or phone on the User updates the Contact automatically (including
when the person has more than one email or phone). Changing them on the
Contact updates the User the same way. Staff do not keep two conflicting
lists by hand.

**Why this priority**: Owner requires the identity fields copied at create
to remain one person, not two diverging copies. Email and phone are sets
(primary plus extras)
(https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md).

**Independent Test**: On a linked pair, change the User primary email and
one extra email; Contact matches after refresh. Change a Contact phone;
User matches. No duplicate send-storm or overwrite of unrelated Contacts.

**Acceptance Scenarios**:

1. **Given** a linked Volunteer or Employee pair, **When** staff change the
   User’s primary email or another address on that User, **Then** the
   Contact’s email set matches after one refresh.
2. **Given** the same pair, **When** staff change a phone on the Contact,
   **Then** the User’s phone set matches after one refresh.
3. **Given** a Help-seeker or a Contact with no CRM user, **When** email or
   phone changes, **Then** no other person’s User is rewritten.
4. **Given** a sync already applied, **When** the other record is saved
   without a real change, **Then** the pair does not loop or flicker.

---

### Edge Cases

- Unchecking Create CRM user after the side panel opened: panel closes;
  save does not create a User.
- Closing the side panel without confirming login fields: treat as
  checkbox off unless staff turn it on again.
- Staff fill a password in the panel anyway: access email still MUST NOT
  contain that password; the invite path is the set-password link.
- Contact email/phone empty at checkbox time: copied User fields stay
  empty and locked until staff edit the Contact, then reopen or refresh
  the panel; they cannot type over locked copies in the panel.
- Several emails or phones: the whole set stays aligned (primary flag
  included), not only the first value.
- Quick create (small form) and full create both show the checkbox when
  type is Volunteer or Employee.
- After save, the Contact **detail** shows the User link, not a leftover
  empty “Create CRM user” dash as the only clue.
- Member Contact / Member role: still out of this checkbox (004
  assumption unchanged).
- Competence copy, planner, and delete-User→Contact Inactive: already
  specified in 004; this amendment does not reopen them unless the repair
  accidentally breaks them (regression: they MUST still hold).
- Production copy/rebuild of competences: still Skip until the owner names
  that action.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Staff MUST be able to create a CRM User from a Volunteer
  **and** from an Employee Contact. Both types share this flow.
- **FR-002**: On Contact create and edit, when type is Volunteer or
  Employee and no User is linked yet, the form MUST show **Create CRM
  user** as a checkbox, default on for **new** Contacts of those types.
- **FR-003**: The checkbox MUST be visible while creating, after the type
  is Volunteer or Employee — including the small create form — not only
  on the saved detail screen.
- **FR-004**: Turning the checkbox on MUST open a side User panel. Turning
  it off MUST dismiss that panel and MUST NOT create a User on save.
- **FR-005**: The side panel MUST copy first name, last name, email, and
  phone from the Contact. Those copied fields MUST be read-only **in the
  panel**. The same fields MAY be edited later on the User record.
- **FR-006**: Saving with the checkbox on MUST create exactly one User,
  set the Contact’s CRM-user link to that User, and MUST NOT replace
  Assigned User with the new volunteer/employee.
- **FR-007**: An existing Volunteer/Employee Contact with no CRM user MUST
  be able to gain a User through the same checkbox + side panel (repair
  of already-saved people).
- **FR-008**: When the copied email is present, **Send access info** MUST
  be available and default on even if the password is empty.
- **FR-009**: Access email for this flow MUST include a set-password (or
  password-change) link and MUST NOT include a plaintext password.
- **FR-010**: Access (and password-change) emails for this flow MUST use
  the Safe House mark and a clear layout consistent with volunteer
  availability emails. Italian is the primary language of the message.
- **FR-011**: After a Volunteer/Employee Contact is linked to a User,
  email address **sets** MUST stay in sync both ways (User → Contact and
  Contact → User), including extra addresses, not only the primary.
- **FR-012**: Phone number **sets** MUST stay in sync both ways the same
  way as email.
- **FR-013**: Sync MUST NOT run for Contacts that are not Volunteer or
  Employee, and MUST NOT attach or rewrite a User that is not the linked
  CRM user.
- **FR-014**: After a successful create, the Contact screen MUST show the
  linked User (CRM user / Utente CRM). Staff MUST NOT need to hunt in
  Administration > Users to confirm the login exists.
- **FR-015**: This amendment MUST NOT undo 004 competence storage, planner
  reading Contact, or delete-User → Contact Inactive.
- **FR-016**: Local verification first. Production competence copy,
  production rebuild, and production mail tests against live volunteers
  remain Skip until the owner names that exact action.
- **FR-017**: When the owner confirms 004.1 UAT Pass, **004 and 004.1**
  MUST both be marked closed with a note that 004 create-User UAT failed
  and was repaired here.

### Key Entities

- **Contact**: The person (Volunteer or Employee in this flow). Holds
  profile and competences (004). May link to one CRM User.
- **User**: The login. Username, roles, teams, access email. Linked to
  the person Contact. Name, email set, and phone set stay aligned with
  that Contact after create.
- **Access email**: Message that lets a new user reach the CRM and set a
  password without receiving the password in the message.
- **Email set / phone set**: Primary plus additional addresses/numbers on
  Contact and User
  (https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A staff member creates a Volunteer Contact with a CRM login
  in one sitting in under three minutes; on first open of the saved
  Contact the User link is present (not empty).
- **SC-002**: The same sitting works for Employee (same rules, same
  side panel).
- **SC-003**: In ten repair attempts (create or “add User to existing
  Contact”), at least nine produce both records and a visible CRM-user
  link on the Contact without visiting Administration > Users first.
- **SC-004**: Access emails from this flow contain a set-password link
  and contain zero password strings; a reviewer can tell they are Safe
  House mail (logo + readable layout) compared with a shift availability
  message.
- **SC-005**: After changing one of several emails (or phones) on the
  User, the linked Contact matches within one refresh; the reverse
  direction matches the same way.
- **SC-006**: Owner confirmation of this amendment (including the 004
  create-User repair) is the gate to close **both** 004 and 004.1. A
  passing automated check does not replace that confirmation.

## Assumptions

- Owner 2026-09-17: 004 UAT Fail on create-User; this `004.1` hotfix
  **includes the repair** and the new side-panel / access-email / sync
  behaviour. Owner will close **004 and 004.1 together** after they
  confirm UAT Pass on 004.1.
- Owner 2026-09-17: email and phone sync is **both directions** for
  linked Volunteer/Employee pairs.
- Owner 2026-09-17: copied name/email/phone are read-only **only in the
  create side panel**; later User edit is allowed.
- 004 competence migration already ran on the local CRM; do not wipe or
  re-import production data in this amendment.
- Identity link remains `linkedUser` / CRM user; Assigned User remains
  ownership
  (https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md).
- Native Espo **Send access info** with empty password sends a
  set-password link; specifying a password would send it in the mail —
  this flow MUST keep the password empty for the invite and MUST NOT
  put a password in the message
  (https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md).
- Product UI Italian primary; English secondary; existing Russian strings
  may be updated where Contact/User labels already exist.
- 003 Google/WF owner UAT remains **required after 004 and 004.1 close**.
- LearnHouse / gm-edu-specific identity is out of scope; only the
  person-then-login and email/phone alignment ideas apply.
- Member create-login checkbox stays out of scope.
- Local CRM only until the owner names production.
