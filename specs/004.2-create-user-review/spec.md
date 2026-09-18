# Feature Specification: Create-User review modal, admin-only personnel, unique login email, Contact mirror

**Feature Branch**: `004.2-create-user-review`

**Created**: 2026-09-18

**Status**: Draft

**Input**: Owner rejected 004.1 UAT. Checkbox-opens-drawer is the wrong
moment; password fields still appear; send-access-info is missing; Utente
CRM picker stays visible with Create user; Volunteer/Employee must be
admin-only; two Users can share one email; Contact profile (competences
and the rest) must show on User as a **reflection**, not a second stored
copy. Core Espo MUST NOT be edited. Pattern for the invite mail: gm-edu
Promote access-info (set-password link, never plaintext) — our flow is
Contact+User in one create, not Promote-after-Contact.

Parent: [`../004.1-repair-create-crm-user/spec.md`](../004.1-repair-create-crm-user/spec.md)
(004.1 UAT not accepted). Grandparent:
[`../004-contact-first-crm-user/spec.md`](../004-contact-first-crm-user/spec.md).
Owner closes **004, 004.1, and 004.2 together** after this list Pass.

Research cite (Espo native; local clone opened this turn):
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/administration/layout-manager.md
https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/development/modules.md
https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin creates Volunteer/Employee, then reviews the User (Priority: P1)

An **administrator** opens **create Contact** (not Modifica of an existing
person). They choose type Volontario or Dipendente. They see a real
checkbox **Crea utente CRM**, on by default. Ticking it does **not** open
any panel. While it is on, the **Utente CRM** picker is hidden (creating
and picking a User are mutually exclusive). They fill required person
fields including email, then click Save.

A side review panel then opens with the future login already filled from
the Contact (name, email, phone locked). Username is suggested. The Role
is already **Volunteer** or **Employee** matching the Contact type.
Password, confirm, and Generate are **absent**. A renamed checkbox is
visible and on: send the person an email with a **link to create their
password** (never a password in the message). The admin clicks Save on
that panel: one Contact and one User exist, linked. Assigned User on the
Contact stays the staff member. Cancel on the panel: nothing is stored
(no Contact without a User, no User without a Contact).

If they uncheck Crea utente CRM before Save, only the Contact is stored.

Non-administrators never see Volunteer/Employee as a type they can set,
and cannot create those Contacts.

**Why this priority**: 004.1 opened the User form too early (empty
Nessuno, password fields, missing invite checkbox) and still allowed
non-admins / edit-card confusion.

**Independent Test**: As admin, create Volunteer with checkbox on, Save,
review panel, Save panel. Contact shows Utente CRM. Mailbox (SMTP on
DDEV) has a set-password link and no password text. As a regular user,
create Contact: Volunteer/Employee types are not available.

**Acceptance Scenarios**:

1. **Given** an admin is on Contact **create**, **When** they set type
   Volunteer or Employee, **Then** Crea utente CRM is a real checkbox and
   on; no User panel opens yet.
2. **Given** that checkbox is on, **When** they look at the form, **Then**
   Utente CRM (Seleziona) is hidden.
3. **Given** required Contact fields including email are filled and the
   checkbox is on, **When** they click Save, **Then** a review panel opens
   with copied identity, pre-filled matching Role, no password fields, and
   the send-password-link checkbox visible and on.
4. **Given** that panel, **When** the admin clicks Save there, **Then**
   Contact and User both exist and Utente CRM points at the User.
5. **Given** that panel, **When** the admin clicks Cancel, **Then** neither
   Contact nor User is stored.
6. **Given** the checkbox is off, **When** they Save, **Then** Contact only;
   no review panel.
7. **Given** a non-admin, **When** they create a Contact, **Then** they
   cannot choose Volunteer or Employee.
8. **Given** an existing Contact, **When** staff use Modifica or the full
   edit screen, **Then** Crea utente CRM is not offered.

---

### User Story 2 - Invite is a set-password link; never a password in mail (Priority: P1)

The review panel never offers a password. The invite checkbox means: send
the native access email whose body has a unique link to set a password,
username, and CRM address. It MUST NOT contain a password. Administration
→ Users keep Espo’s stock wording; only this Contact-first panel uses the
renamed label (Italian primary, same sense as gm-edu Promote).

**Why this priority**: Owner screenshots still showed Password/Genera and
no invite checkbox. Core must stay untouched; the extension overrides this
panel only.

**Independent Test**: Create Volunteer+User with SMTP on. Mail has a
set-password link, logo, no password. Unchecking the invite box creates
the User without sending mail (still no password fields).

**Acceptance Scenarios**:

1. **Given** the review panel and a Contact email, **When** it opens,
   **Then** the invite checkbox is visible, labelled as send-a-link-to-
   create-password, and on.
2. **Given** that checkbox is on, **When** the User is created, **Then**
   the person receives one access email with a set-password link and no
   password text.
3. **Given** that checkbox is off, **When** the User is created, **Then**
   no access email is sent and still no password was collected.
4. **Given** the Contact has no email, **When** Crea utente CRM is on,
   **Then** Save does not create a User; staff must add an email first.

---

### User Story 3 - One login email per User (Priority: P1)

Staff cannot create (or save) a second User with an email that already
belongs to another User. The person Contact may share that same email
with **its** linked User. Native username uniqueness stays. Existing
today-created duplicates on DDEV were removed except the oldest account.

**Why this priority**: Owner showed three Users on one mailbox; native User
does not unique-check email.

**Independent Test**: Create a User with an email already on another User;
save is refused with a clear message. Create Volunteer+User using a fresh
email; succeeds. Contact and its linked User may share that email.

**Acceptance Scenarios**:

1. **Given** User A already has email X, **When** staff create User B with
   X (this panel or Administration → Users), **Then** save is refused.
2. **Given** a new Volunteer Contact with a new email, **When** admin
   completes the review panel, **Then** User is created with that email.
3. **Given** a linked Contact+User pair, **When** they share one email,
   **Then** that is allowed.

---

### User Story 4 - Contact profile is reflected on User, not stored twice (Priority: P1)

Competences, volunteer/employee hours and dates, occasional flag, fiscal
and birth fields, and member profile that belong on Contact MUST appear on
the linked User screen as a **read-only reflection** of the Contact. Staff
edit them on the Contact. The User record MUST NOT keep a second stored
copy of those values. Changing competences on Contact is what the planner
reads. Email and phone remain the shared channel lists for the person and
the login (already required by 004.1).

**Why this priority**: Owner: visible on both, not by copying fields, by
reflection instead of data duplicates. 004 already made Contact the source
of truth; leftovers (for example a stored occasional flag on User) must
not remain a second copy.

**Independent Test**: Set competences on Contact; User screen shows the
same after refresh and is not independently editable there. Planner uses
the Contact list. Database/User storage for those profile fields is not a
second source of truth.

**Acceptance Scenarios**:

1. **Given** a linked Volunteer, **When** staff change competences on the
   Contact, **Then** the User screen shows those values after refresh.
2. **Given** the User screen, **When** staff look at competences / hours /
   dates / occasional / fiscal / birth / member profile, **Then** they
   cannot save a different copy there; Contact remains the store.
3. **Given** a User with no Volunteer/Employee Contact, **When** they open
   the User, **Then** those reflected personnel fields are empty or hidden.
4. **Given** email or phone change on either linked record, **When** staff
   refresh the other, **Then** the channel lists still match (004.1).

---

### Edge Cases

- Help-seeker / other types: no Crea utente CRM, no review panel.
- Quick create of Contact: same rules as full create (checkbox, Save then
  panel, admin-only types).
- Existing Volunteer without a User: this checkbox is **not** on edit;
  admin links or creates a User from Administration → Users.
- Duplicate username: native unique username still blocks save.
- Invite off + no password: User exists but cannot sign in until an admin
  later sends a password-change link (native User menu).
- Cancel review panel after validation errors on Contact: still no records.
- Non-admin who tampers with type Volunteer/Employee: server refuses.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Crea utente CRM MUST appear only on Contact **create**
  (full and small), only for Volunteer/Employee, default on, and MUST NOT
  open a panel when toggled.
- **FR-002**: While Crea utente CRM is on, Utente CRM picker MUST be hidden
  and any picked User cleared.
- **FR-003**: Save on create with the checkbox on MUST open a review panel
  after required Contact fields (including email) are valid; it MUST NOT
  persist Contact or User until the admin saves that panel.
- **FR-004**: Review panel MUST copy name/email/phone from the Contact as
  locked; MUST pre-fill Role Volunteer or Employee from Contact type;
  MUST omit password, confirm, and generate.
- **FR-005**: Review panel MUST show a renamed invite checkbox (Italian:
  send the user an email with a link to create a password), default on
  when email exists, and that path MUST send the native set-password-link
  access mail, never a password in the body.
- **FR-006**: Administration → Users stock create MUST keep Espo’s own
  send-access-info label and behaviour (extension MUST NOT patch core
  files).
- **FR-007**: Only Espo **admin** users MAY create or set Contact type
  Volunteer or Employee; other users MAY create other Contact types.
- **FR-008**: Two Users MUST NOT share the same email address; Contact MAY
  share email with its linked User.
- **FR-009**: Competences and the Volunteer/Employee/Member profile shown
  on User MUST be a read-only reflection of the linked Contact, not a
  second stored copy. Planner reads Contact.
- **FR-010**: Email and phone sets MUST stay aligned both ways on a linked
  pair (004.1).
- **FR-011**: Assigned User on the Contact MUST remain the creating staff
  member; the new User MUST NOT steal that assignment.
- **FR-012**: Product code MUST live only under `custom/` and
  `client/custom/`. MUST NOT change Espo core (`application/`, vendored
  Espo). Overrides belong in NonprofitEspocrm.

### Key Entities

- **Contact**: Person record; source of truth for personnel profile and
  competences; optional dedicated CRM-user link.
- **User**: Login; username unique; email unique among Users; shows
  Contact profile as reflection; receives set-password-link mail.
- **Role**: Volunteer / Employee pre-filled from Contact type.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can create a Volunteer with a login in one sitting
  (create form → Save → review → Save) without seeing password fields.
- **SC-002**: 100% of access mails from this panel contain a set-password
  link and 0% contain a password.
- **SC-003**: A non-admin cannot produce a Volunteer or Employee Contact.
- **SC-004**: Attempting a second User with an existing User email fails
  every time in owner UAT.
- **SC-005**: After changing competences on Contact, the User screen
  matches on the next open/refresh; staff cannot keep a conflicting User
  copy.
- **SC-006**: Owner completes the 004.2 checklist without using Modifica
  to create a User.

## Assumptions

- Admin means Espo user type Admin (`isAdmin`), not a custom Role named
  Admin.
- Role on the review panel is pre-filled and still changeable by the admin.
- SMTP is configured on local DDEV for the access-mail test.
- Italian UI labels stay as they are except the renamed invite checkbox on
  this panel.
- Email/phone two-way sync from 004.1 stays; that is login identity, not
  the personnel-profile reflection.
- Existing Contacts without a User are out of this create checkbox; admin
  uses Administration → Users if needed.
- Local DDEV cleanup of two today-created duplicate Users
  (`tster`, `testantidub`) keeping `semen.koksharov` is already done; this
  spec does not delete production Users.
- 003 Google/WF owner UAT still waits until 004 / 004.1 / 004.2 close.
- Production copy/rebuild and live volunteer mail stay Skip until the
  owner names those actions.
