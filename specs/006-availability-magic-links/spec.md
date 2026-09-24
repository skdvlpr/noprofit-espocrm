# Feature Specification: Shift planner review and availability links without login

**Feature Branch**: `006-availability-magic-links`

**Created**: 2026-09-24

**Status**: Draft

**Input**: Owner: before new behaviour, assess the current shift planner
against official product behaviour and fix defects that are wrong.
Then, when staff request volunteer availability, each volunteer receives
their own secure link by email and can answer without signing in to the
CRM. The link works for 7 days or until staff send a newer one. Each
tick is saved immediately. Resending
the request to one or more volunteers creates new links and emails only
those people.

Research cite (official behaviour; local clone opened this turn):
https://github.com/espocrm/documentation/blob/master/docs/development/entry-points.md
https://github.com/espocrm/documentation/blob/master/docs/administration/emails.md
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/acl.md

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Planner defects are found and fixed first (Priority: P1)

The owner receives a written assessment of the current shift planner:
what matches official behaviour, and what is incorrect or unsafe. Each
defect is named in plain language, with what a staff member or volunteer
sees today and what should happen instead.

Defects that make the planner wrong, unsafe, or impossible to extend
with personal links are fixed before any volunteer is sent a no-login
link. The assessment does not expand into unrelated CRM areas.

**Why this priority**: New links on a broken planner would copy the
defects into every email.

**Independent Test**: Read the assessment. Each listed defect is either
fixed, with a before/after check, or explicitly deferred with a reason
the owner can accept. No availability email uses a no-login link until
the in-scope fixes are done.

**Acceptance Scenarios**:

1. **Given** the current planner, **When** the assessment is delivered,
   **Then** it lists concrete defects and correct behaviours, not a
   vague “looks fine”.
2. **Given** a defect marked in scope, **When** the fix is applied,
   **Then** the old wrong outcome no longer happens and the rest of
   planning still works.
3. **Given** the assessment, **When** magic links are not finished,
   **Then** staff can still request availability the way they do today.

---

### User Story 2 - A volunteer answers from their own email link (Priority: P1)

Staff request availability for a plan. Each volunteer in that request
receives an email that contains their own link. They do not need a CRM
password.

Opening the link shows only that volunteer’s availability choices for
that plan: the same shift-choice dialog as in the CRM, and nothing
else. They cannot open another person’s answers or the rest of the CRM
from that link.

The link stops working when either happens first:

- staff send that volunteer a newer link;
- the link reaches the end of its lifetime (7 days from the moment it
  was created).

An expired or replaced link shows a clear message and does not change
any availability. Guessing or reusing someone else’s link does not work.

**Why this priority**: Volunteers should not be blocked by a CRM login
just to say which shifts they can do.

**Independent Test**: Send a request to two volunteers. Each email has
a different link. Volunteer A ticks a shift without signing in.
Volunteer B’s link still works. A can tick another shift on the same
link. A link older than 7 days does nothing.

**Acceptance Scenarios**:

1. **Given** a plan with two volunteers, **When** staff request
   availability, **Then** each volunteer gets one email and the links
   are not the same.
2. **Given** a valid link, **When** the volunteer opens it without
   signing in, **Then** they see only their own shifts for that plan
   and can tick shifts. Each tick is stored immediately.
3. **Given** a valid link, **When** the volunteer ticks a shift,
   **Then** that choice is stored at once, the page stays on the shift
   list, and they can change another shift without asking for a new
   link.
4. **Given** a link created 8 days ago and never submitted, **When** it
   is opened, **Then** it is rejected.
5. **Given** volunteer A’s link, **When** someone tries to use it as
   volunteer B, **Then** they cannot see or save B’s availability.

---

### User Story 3 - Resend replaces links only for the people selected (Priority: P1)

Staff can already resend an availability request to one volunteer or to
several. That action keeps its current audience: only the people staff
picked.

For each selected person, the previous link stops working and a new
link is created. The email goes only to those people. Volunteers who
were not selected keep their current link and are not emailed again.

**Why this priority**: A lost email must be replaceable without
resetting the whole cohort or leaving an old link alive.

**Independent Test**: Request availability for three volunteers. Resend
to two of them. Those two get new emails and their old links fail. The
third volunteer’s old link still accepts a tick, and they receive no new email.

**Acceptance Scenarios**:

1. **Given** three volunteers already emailed, **When** staff resend to
   two, **Then** only those two receive a new email.
2. **Given** that resend, **When** one of the two opens their previous
   link, **Then** it is rejected.
3. **Given** that resend, **When** the volunteer who was not selected
   opens their original link, **Then** they can still tick a shift.
4. **Given** a resend, **When** the new link is used, **Then** the
   answer is stored as that volunteer’s availability, the same as a
   first submission.

---

### Edge Cases

- A volunteer with no email address is skipped. Staff can see that this
  person was not mailed. No link is created for an empty address.
- Two clicks on a still-valid link show the same shift list, including
  ticks already saved.
- Two browsers ticking the same shift: the later tick wins for that
  shift. The link stays valid until it expires or is replaced.
- Resend while the volunteer has the old page open: a tick on the old
  page fails. They must use the new email.
- A plan with no published shifts still cannot send a request.
- Expired links are not reused. A later new request creates a new link.
- The public page does not show other volunteers’ names, comments, or
  contact details beyond what that person needs to mark their own shifts.
- The volunteer availability email already goes out. This feature
  changes that template only: the message offers the personal link to
  the shift dialog, and it no longer offers a CRM login or a link to
  the planner. Send and read that message on the local site, where
  outbound mail is already configured, before any production send.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The shift planner must be assessed against official
  product behaviour before no-login links are sent. The assessment
  lists each defect, what goes wrong, and whether it is fixed in this
  feature.
- **FR-002**: Defects in the assessment that are wrong, unsafe, or
  block personal links must be fixed before a volunteer can answer
  through a no-login link.
- **FR-003**: An availability request must create one personal link per
  volunteer who has an email address, and the email must contain that
  link and no one else’s.
- **FR-004**: Opening a valid link must not require a CRM sign-in and
  must not sign the volunteer into the CRM.
- **FR-005**: The page behind the link shows only the same shift-choice
  dialog staff already use for availability in the CRM. Nothing else
  from the CRM is on that page: no menu, no plan record, no description,
  no comment box, no extra buttons. The volunteer only ticks shifts.
- **FR-005a**: On a phone the dialog uses the full screen width, the
  shift rows stay readable, and ticking a shift does not require
  horizontal scrolling.
- **FR-005b**: Each tick is saved immediately. There is no separate Save
  step. The volunteer can tick and untick more shifts on the same open
  link.
- **FR-006**: A link stops accepting answers 7 days after creation, or
  when staff send that volunteer a newer link. It does not die on the
  first tick, because the answer is saved as they go.
- **FR-007**: An expired, replaced, or unknown link must
  not change availability and must explain that the link is no longer
  valid.
- **FR-008**: Resending to a chosen set of volunteers must invalidate
  only their current links, create new ones, and email only that set.
- **FR-009**: Volunteers not included in a resend must keep their
  current link and must not be emailed by that resend.
- **FR-010**: Staff still choose who is asked. This feature does not
  change who is eligible, how shifts are published, or how people are
  assigned after availability is known.
- **FR-011**: The public page is in Italian, consistent with the rest
  of the product UI.

### Key Entities

- **Week plan**: The planning record staff already use. A request is
  always for one plan.
- **Volunteer**: A person with a CRM user who can be asked for
  availability. They need an email address to receive a link.
- **Availability answer**: The shifts that volunteer says they can do,
  plus an optional comment. One current answer per volunteer per plan.
- **Personal link**: A secret address for one volunteer and one plan.
  It is created on send or resend, and it dies on expiry or when a
  newer link replaces it.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The assessment names every in-scope planner defect found,
  and each one is either fixed or explicitly deferred before the first
  no-login email is treated as ready.
- **SC-002**: In a two-volunteer test, the two email links differ, and
  each person can tick a shift without a CRM password in under two minutes
  after opening the mail.
- **SC-003**: After 7 days, and after a newer link was sent, the old
  link changes nothing. A wrong link changes nothing. While the link
  is still live, each tick is stored without a separate save action.
- **SC-004**: Resend to 2 of 3 volunteers produces exactly 2 new emails
  and exactly 2 new working links. The third person’s previous link
  still works and they get no new mail.
- **SC-005**: A volunteer cannot read or change another volunteer’s
  availability by editing the link.

## Assumptions

- Link lifetime is 7 days from creation. That matches a weekly plan.
  A different lifetime needs an owner decision later; it is not a
  blocker for this spec.
- A newer link for the same volunteer and plan replaces the older one.
  The older one stops working immediately.
- The public page is the CRM availability dialog and nothing around
  it. Ticks save themselves. The link stays usable for 7 days or until
  staff replace it, so the volunteer can correct a tick. The in-CRM
  dialog for signed-in staff keeps its current Save button.
- The volunteer already exists as the same person staff select today.
  This feature does not create volunteer records from the link.
- Who receives the first request stays as it is today. Only the way
  they open the form changes.
- The assessment covers the shift planner (plans, shifts, availability
  requests, the emails those requests send, and assignment that depends
  on those answers). It does not audit the rest of the CRM.
- Local outbound mail is already configured. Implementation includes
  sending the changed volunteer template on the local site and checking
  that the received message contains only that person’s link to the
  shift dialog. Production send and production apply stay off until the
  owner names them.
- The public website repository is out of scope. Links are opened from
  the email, on the CRM address.
- 004.3 and 005 owner checks stay separate. This spec does not close
  them.
