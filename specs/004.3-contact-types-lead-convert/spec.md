# Feature Specification: Contact multi-type (Vol/Emp + Member) and Lead convert with CRM user

**Feature Branch**: `004.3-contact-types-lead-convert`

**Created**: 2026-09-18

**Status**: Draft

**Input**: Owner: a Contact must be able to hold more than one type at
once, with Volunteer and Employee mutually exclusive. Allowed
combinations for now are only Volunteer+Member and Employee+Member; no
other mixes. Associato (Member) uses the same create-CRM-user flow as
Volunteer. Duplicate person fields must appear once; when both Volunteer
and Member are selected, show both field sets and grant both User Roles.
Lead is a **type picker** (forms usually post one type). Staff may add
a second type by hand: only Volunteer+Member or Employee+Member. Lead
types: Volunteer, Employee, Member, Generic. Volunteer/member extra
fields live on the **Contact convert** form, not on the Lead. Convert
copies the type(s) onto Contact (both when two are set) so the matching
Contact fields appear immediately, and offers the **same** create-user
review as Contact create.

Parent: [`../004.2-create-user-review/spec.md`](../004.2-create-user-review/spec.md).
Grandparent:
[`../004-contact-first-crm-user/spec.md`](../004-contact-first-crm-user/spec.md).
004 / 004.1 / 004.2 remain owner-closed together; this amendment extends
that family after local UAT Pass and production transfer of existing
rows.

Research cite (Espo native; local clone opened this turn):
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/administration/layout-manager.md
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/web-to-lead.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/customize-standard-fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/modules.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata.md
https://github.com/espocrm/documentation/blob/master/docs/administration/formula.md
https://github.com/espocrm/documentation/blob/master/docs/administration/formula/array.md

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Volunteer (or Employee) who is also a Member (Priority: P1)

Staff open a Contact. They can set **more than one** type, but only these
sets are legal:

- any **single** existing type (Help-seeker, Colleague, Volunteer,
  Employee, Member/Associato, Association representative, Other, or empty);
- **Volunteer + Member**;
- **Employee + Member**.

Volunteer and Employee cannot be on together. Member cannot be combined
with Help-seeker, Colleague, Association representative, Other, or any
other pair. Three types at once are refused.

When Volunteer + Member (or Employee + Member) is selected, the form
shows **both** the volunteer/employee field set **and** the member field
set. Fields that exist on both sets (fiscal code, birth date/place, and
the like) appear **once**. Saving stores one person, not two Contacts.

If that Contact has (or is creating) a CRM login, the User receives
**both** Roles (Volunteer and Member, or Employee and Member). A User
may already hold several Roles natively; this feature only adds or
removes the Volunteer / Employee / Member roles to match the Contact
types, and does not strip unrelated Roles.

**Why this priority**: Today a Contact has exactly one type, so a
volunteer who is also an associato cannot be represented without a
duplicate person.

**Independent Test**: As admin, create or edit a Contact as Volunteer +
Member; both panels visible; shared fields once; save succeeds. Attempt
Volunteer + Employee and Volunteer + Help-seeker; save is refused with a
clear message. Linked User shows both Roles. Filters Volontari and
Associati both include that person.

**Acceptance Scenarios**:

1. **Given** an admin on Contact create or edit, **When** they select
   Volunteer and Member, **Then** both volunteer and member fields are
   visible and duplicated fields appear only once.
2. **Given** Volunteer + Member, **When** they save, **Then** one Contact
   exists with both types; no second Contact is created.
3. **Given** Volunteer is selected, **When** they also select Employee,
   **Then** the combination is not allowed (UI prevents it and the
   server refuses).
4. **Given** Member is selected, **When** they also select Help-seeker
   (or Colleague / Other / Association representative), **Then** the
   combination is not allowed.
5. **Given** a linked CRM User and Contact types Volunteer + Member,
   **When** they save, **Then** the User has Roles Volunteer and Member
   and still keeps any other Roles they already had.
6. **Given** that Contact, **When** staff open list filters Volontari
   and Associati, **Then** the person appears in both.
7. **Given** an admin on Contact **create** with no type yet, **When**
   they select Volunteer only (or Employee only), **Then** that type
   stays selected, the matching extra fields appear, and the type field
   MUST NOT go blank.

---

### User Story 2 - Member create-user uses the Volunteer review flow (Priority: P1)

On **Contact create**, Crea utente CRM behaves as in 004.2, and now also
for **Member** (Associato), including Member-only and Volunteer/Employee
+ Member. Checkbox on by default for those types; it does not open a
panel; Save opens the same side review; Cancel stores nothing; confirm
creates Contact then User; password fields stay absent; invite is a
set-password link. Review pre-fills **all** matching Roles (one or two).
Utente CRM picker stays hidden while the checkbox is on.

Volunteer and Employee types remain **admin-only**. Member-only create
is allowed for staff who can already create other non-personnel types.
A combination that includes Volunteer or Employee still requires admin.

Crea utente CRM remains **create-only**, not on Modifica of an existing
Contact. If staff later add Member to an existing Volunteer who already
has a User, the Member Role is added on save; they do not get a second
create-user panel.

**Why this priority**: Owner asked Associato to follow the same create
flow as volunteers, and to grant both Roles when both types are set.

**Independent Test**: Admin creates Member with checkbox on → review
shows Role Member → Save → linked User. Admin creates Volunteer +
Member → review shows both Roles. Non-admin cannot choose Volunteer or
Employee. Adding Member on an existing Volunteer+User adds the Member
Role without creating a second User.

**Acceptance Scenarios**:

1. **Given** Contact create with type Member (only), **When** Crea utente
   CRM is on and they Save, **Then** the 004.2 review panel opens with
   Role Member pre-filled.
2. **Given** Contact create with Volunteer + Member, **When** they Save
   the review panel, **Then** one User exists with both Roles.
3. **Given** a non-admin, **When** they create a Contact, **Then** they
   still cannot set Volunteer or Employee (alone or in a combination).
4. **Given** an existing Volunteer Contact already linked to a User,
   **When** an admin adds Member and saves, **Then** no create-user
   panel appears and the User gains Role Member.
5. **Given** Crea utente CRM on for Member, **When** they Cancel the
   review, **Then** neither Contact nor User is stored.

---

### User Story 3 - Lead type(s) only; volunteer/member fields wait for convert (Priority: P1)

A Lead carries **type**, not volunteer/member extra fields. Those fields
are filled on the **Contact** during convert.

Lead types: Volunteer, Employee, Member (Associato), Generic. Inbound
forms usually post **one** type. Staff MAY add a second type by hand,
with the same legal sets as Contact:

- any **single** of those four types (or empty);
- **Volunteer + Member**;
- **Employee + Member**.

Volunteer and Employee cannot be on together. Any other pair is refused.
Generic cannot be combined with another type.

The Lead form shows identity (name, email, phone, address, source, …)
and the type picker. It MUST NOT show volunteer hours/competences or
member join/positions panels.

Existing Leads without a type behave as **Generic** on convert.

**Why this priority**: Capture already sends a type; staff fill the
person profile once, on convert, instead of twice.

**Independent Test**: Create a Volunteer Lead; no volunteer/member
panels. Add Member by hand; both types stay. Convert: Contact has both
types and both Contact field sets visible. Volunteer + Employee on Lead
is refused. Employee-only Lead is offered.

**Acceptance Scenarios**:

1. **Given** a new Lead, **When** staff set type Volunteer (only),
   **Then** no volunteer or member extra panels appear on the Lead.
2. **Given** a Volunteer Lead, **When** staff add Member, **Then** both
   types stay selected and still no extra panels on the Lead.
3. **Given** type Generic or empty, **When** they open the Lead,
   **Then** neither volunteer nor member extra fields are shown.
4. **Given** a Lead, **When** they select Volunteer and Employee,
   **Then** the combination is not allowed.
5. **Given** a Lead, **When** they select Employee only, **Then** that
   type is allowed (same as Contact).

---

### User Story 4 - Convert Lead creates Contact (and optional User) like Contact create (Priority: P1)

On Lead detail, **Convert** remains the native action (Contact, and the
stock optional Account / Opportunity). Contact is the person outcome
for this feature.

Convert copies Lead identity and **Contact type(s)** onto the new
Contact (one type, or both when Volunteer+Member / Employee+Member).
Volunteer/member extra fields are **not** required on the Lead; staff
fill them on the convert Contact form. When two types copy, **both**
Contact field sets appear at once.

- Volunteer Lead → Contact type Volunteer; volunteer fields visible to fill.
- Employee Lead → Contact type Employee; employee fields visible to fill.
- Member Lead → Contact type Member; member fields visible to fill.
- Volunteer+Member Lead → Contact both types; both field sets visible.
- Employee+Member Lead → Contact both types; both field sets visible.
- Generic Lead → Contact type Other; no volunteer/member extra fields.

Staff can tick **Crea utente CRM** on that convert screen. Behaviour is
**identical** to Contact create (004.2 + Member from this spec): checkbox
does not open a panel; Convert/Save with it on opens the same review
side panel; Roles follow the Contact type(s) that will be created;
password fields absent; invite is a set-password link; Cancel leaves
the Lead unconverted and stores **no** Contact and **no** User. Confirm
creates Contact and User, links them, marks the Lead Converted, and
shows the native Converted To panel.

If they close the review (convert or Contact create) without confirming,
a later Convert or Save MUST open the review again. The action MUST NOT
silently do nothing.

Crea utente CRM defaults **on** for Volunteer and Member Leads, **off**
for Generic and for Employee-only unless staff turn it on. Generic +
User (if they turn it on) creates a login **without** Volunteer /
Employee / Member Roles.

Volunteer or Employee Lead convert that would create those Contact types
is **admin-only**, same as creating that Contact type. Member and Generic
convert follow who can already convert Leads and create those Contact
types.

Unique User email, Assigned User = converting staff, and Contact
profile reflection on User stay as in 004.2.

**Why this priority**: Owner must be able to take a form Lead to a
Contact and a login in one sitting, filling volunteer/member data once
on convert.

**Independent Test**: Convert a Volunteer Lead with checkbox on; review;
fields for volunteer on the Contact panel; Confirm; Contact is Volunteer;
User has Role Volunteer; Lead is Converted. Close review then Convert
again: review reopens. Convert Volunteer+Member: Contact has both types
and both panels. Convert Generic with checkbox off: Contact Other, no
User.

**Acceptance Scenarios**:

1. **Given** a Volunteer Lead, **When** an admin converts with Crea
   utente CRM on and confirms the review, **Then** the Contact is
   Volunteer, volunteer fields were available on convert, the User has
   Role Volunteer, and the Lead status is Converted.
2. **Given** that review panel, **When** they Cancel, **Then** the Lead
   is unchanged and no Contact or User exists. **When** they Convert
   again, **Then** the review opens again.
3. **Given** a Member Lead, **When** they convert with Crea utente CRM
   on, **Then** the Contact is Member with member fields visible on
   convert and the User has Role Member.
4. **Given** a Generic Lead, **When** they convert with Crea utente CRM
   off, **Then** a Contact of type Other is created, no User, Lead
   Converted.
5. **Given** a Generic Lead, **When** they turn Crea utente CRM on and
   confirm, **Then** a User exists without Roles Volunteer, Employee, or
   Member.
6. **Given** a non-admin, **When** they convert a Volunteer Lead,
   **Then** they cannot complete a Volunteer Contact + User that way.
7. **Given** convert, **When** Account or Opportunity stay checked as in
   stock Espo, **Then** those native outcomes still work; this feature
   does not remove them.
8. **Given** a Lead with Volunteer + Member, **When** they convert,
   **Then** the Contact has both types and both field sets are visible
   on the convert form.
9. **Given** Contact create with Crea utente CRM on, **When** they
   close the review without confirming and Save again, **Then** the
   review opens again (same as convert).
10. **Given** a Volunteer or Volunteer+Member convert form (employee
    **Tipo contratto** hidden/empty), **When** they confirm the user
    review, **Then** convert MUST succeed; empty `contractType` MUST
    NOT fail backend `valid`.

---

### User Story 5 - User screen mirrors the combined Contact profile (Priority: P2)

When a linked Contact is Volunteer + Member (or Employee + Member), the
User detail shows a **read-only reflection** of **both** field sets,
with the same de-duplicated shared fields. Staff still edit on the
Contact. Planner still reads competences from the Contact. Empty
competence list still means all categories (004).

**Why this priority**: Owner: visual more than a second data store;
mirrors must follow the combined person.

**Independent Test**: Set competences and join date on a Volunteer +
Member Contact; open the User; both values visible, not independently
editable there.

**Acceptance Scenarios**:

1. **Given** Volunteer + Member Contact linked to a User, **When** staff
   open the User, **Then** volunteer and member reflected fields are
   both visible, shared fields once, read-only.
2. **Given** a change on the Contact, **When** they reopen the User,
   **Then** the reflection matches.

---

### Edge Cases

- Existing Contacts with one type keep that one type after this feature;
  staff may add Member to Volunteer or Employee later.
- Existing Leads with no type convert as Generic → Contact Other.
- Removing Member from a Volunteer + Member Contact removes Role Member
  from the linked User and hides member fields; Volunteer data stays.
- Replacing Volunteer with Employee (single type change, not a combo) is
  allowed for admins; Volunteer + Employee together is not.
- Convert with Crea utente CRM on but no email: same as 004.2 — cannot
  finish the User until email exists.
- Duplicate User email still refused (004.2). Duplicate **Contact**
  email is refused the same way (hard, not a skippable warning). A
  Contact MAY share an address with **its** linked User only.
- Convert to a Contact whose email already belongs to another Contact
  MUST fail; staff resolve the existing person instead of creating a
  second Contact.
- Lead already Converted: stock Espo, no second convert.
- Quick create Contact: same type-combination rules and create-user
  rules as full create.
- List/kanban of Contacts: types display as multiple labels when two
  are set.
- Mass-update of Contact type must obey the same legal combinations
  (illegal mix refused).
- On Contact **create**, choosing Volunteer-only or Employee-only MUST
  keep that type on the form (must not clear the picker) and MUST show
  the matching extra fields. First pick of those types is the same rule
  as later picks.
- Lead has no volunteer/member extra panels; staff fill those on convert.
- Closing the CRM-user review without confirm, then Convert or Save
  again, MUST reopen the review.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: A Contact MUST be able to hold multiple types, but the
  only legal multi-type sets MUST be Volunteer+Member and
  Employee+Member. Volunteer and Employee MUST be mutually exclusive.
  Any other combination MUST be refused in the form and on save.
- **FR-002**: Every current single Contact type MUST remain selectable
  alone (including empty). Existing one-type Contacts MUST keep that
  type until staff change it.
- **FR-003**: When Volunteer+Member or Employee+Member is selected, the
  Contact form MUST show both corresponding field sets. Fields that
  belong to both sets MUST appear once.
- **FR-004**: Crea utente CRM on Contact **create** MUST follow 004.2
  (checkbox does not open a panel; Save opens review; Cancel persists
  nothing) and MUST also apply to Member-only and to the two legal
  combinations. Review MUST pre-fill every matching Role (Volunteer
  and/or Employee and/or Member).
- **FR-005**: Only Espo administrators MAY set Contact type Volunteer
  or Employee (alone or in a combination). Member-only MUST remain
  available to staff who can create other non-Volunteer/Employee
  Contacts.
- **FR-006**: Crea utente CRM MUST NOT appear on edit of an existing
  Contact. Adding or removing Member/Volunteer/Employee on a Contact
  that already has a User MUST add or remove only those three Roles on
  that User and MUST NOT create a second User.
- **FR-007**: A Lead MAY hold one type or the same legal two-type sets
  as Contact (Volunteer+Member or Employee+Member). Lead types are
  Volunteer, Employee, Member, and Generic. The Lead form MUST NOT show
  volunteer or member extra field sets.
- **FR-008**: Convert Lead MUST copy identity and Contact type(s) onto
  the new Contact (one or both legal types). Matching Contact extra
  fields MUST appear on the convert form so staff can fill them there.
  Generic → Contact Other. The Lead record stays (status Converted) per
  native convert.
- **FR-009**: Convert Lead MUST offer Crea utente CRM with the same
  review, invite, and cancel-persists-nothing behaviour as Contact
  create. Closing the review without confirm MUST let a later Convert
  or Contact-create Save open the review again. Default on for
  Volunteer and Member Leads, off for Generic. Generic + User MUST NOT
  receive Roles Volunteer, Employee, or Member.
- **FR-010**: Convert of a Volunteer or Employee Lead that creates those
  Contact types MUST be limited to administrators, matching FR-005.
- **FR-011**: Linked User personnel profile MUST remain a read-only
  reflection of the Contact, including both field sets when two types
  are set. Planner still reads competences from Contact.
- **FR-012**: Unique User email, Assigned User = creating/converting
  staff, two-way email/phone on a linked pair, and no core Espo edits
  remain in force from 004.1 / 004.2.
- **FR-013**: Contact list filters for volunteers, employees, and
  members MUST include people who have that type even when a second
  legal type is also set.
- **FR-014**: Two Contacts MUST NOT share the same email address
  (primary or extra, case-insensitive), with the same hardness as User
  email uniqueness. A Contact MAY share an address only with **its**
  linked User. Native skippable duplicate warnings are not enough.
- **FR-015**: On Contact create, selecting Volunteer as the only type
  or Employee as the only type MUST keep that selection on the form and
  MUST show the matching extra fields. The type MUST NOT become empty
  as a side effect of hiding the mutually exclusive opposite type.
- **FR-016**: Convert MUST succeed when employee-only `contractType`
  is empty or hidden (Volunteer / Member). Optional enum MUST allow
  empty (`""` + EmptyStringToNull). Convert payload MUST NOT send an
  empty `contractType` unless Employee is selected.
- **FR-017**: Unchecking User **Is Active** MUST set linked Contact
  `personnelStatus` Inactive, same as deleting the User. Contact
  itself stays. Re-checking Is Active does not have to reactivate.

### Key Entities

- **Contact**: Person record; one or two types under the legal set;
  source of truth for volunteer, employee, and member profile fields.
- **Lead**: Prospect from a form or staff entry; one type or a legal
  two-type set; type(s) copy to Contact on convert; extra volunteer/
  member fields are filled on convert, not on the Lead; remains after
  convert with status Converted.
- **User**: Login; may hold multiple Roles; Volunteer / Employee /
  Member Roles follow Contact types; profile fields are reflections.
- **Role**: Named Volunteer, Employee, Member as today.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Staff can represent a volunteer who is also a member as
  **one** Contact in a single save, with both field sets visible, in
  under two minutes of form time once data is known.
- **SC-002**: 100% of attempted illegal type mixes (including Volunteer
  + Employee and Member + Help-seeker) fail to save in owner tests.
- **SC-003**: From a Volunteer or Member Lead, an admin can produce a
  Contact with matching type(s) and fill extra fields on convert, plus
  an optional User with the matching Role(s), without entering those
  extra fields on the Lead.
- **SC-004**: Cancel on the convert review leaves 0 new Contacts and 0
  new Users; the Lead is still not Converted. Convert again after cancel
  opens the review again.
- **SC-005**: A Volunteer + Member Contact appears in both volunteer
  and member list filters in owner tests.
- **SC-006**: On the User screen, combined profile fields match the
  Contact after refresh; staff cannot keep a conflicting stored copy
  there.
- **SC-007**: In owner tests, a Lead can hold Volunteer+Member or
  Employee+Member, and any other two-type mix is refused.
- **SC-008**: Creating or saving a second Contact with an email already
  on another Contact fails every time in owner tests; converting a Lead
  into that situation also fails; a linked Contact+User pair may keep
  one shared address.
- **SC-009**: In owner tests, Volunteer-only and Employee-only Contact
  create keep the chosen type on the first selection and show the extra
  fields without requiring a second pick.
- **SC-010**: Volunteer+Member convert with empty Tipo contratto
  creates Contact + User. Unchecking User Is Active sets that Contact
  Inactive.

## Assumptions

- Member on Contact is today’s Associato. Lead type **Member** maps to
  that Contact type.
- Generic Lead maps to Contact type **Other**.
- Leads without a type, including historical rows, are treated as
  Generic.
- Account and Opportunity checkboxes on Convert stay native and
  optional; this feature does not redesign B2C/Opportunity labels.
- Building or changing public web forms / Lead Capture payloads is out
  of this spec; those forms can already post a single Lead type once
  the field exists.
- Role names Volunteer, Employee, Member already exist; this spec does
  not create extra Role records.
- 004.2 create-user rules (review panel, set-password link, unique
  User email, admin-only Volunteer/Employee, no create-user on edit) stay
  unless this spec explicitly widens them (Member + convert + unique
  Contact email).
- Existing Contacts that already share an email MUST be listed on DDEV
  (and prod only when the owner names it) before the hard unique rule
  ships; this spec does not auto-merge people.
- Production apply of this amendment waits until the owner names it;
  work starts on DDEV.
- 003 owner UAT remains deferred until the 004 family is owner-closed.
- Unrelated Contact types (Help-seeker, Colleague, Association
  representative) stay single-type only in this version; future mixes
  need another spec.
- Duplicate shared fields means fiscal code, birth date, birth place,
  birth province, and any other person field that both volunteer and
  member forms already show; volunteer-only and member-only fields stay
  in their sets.
