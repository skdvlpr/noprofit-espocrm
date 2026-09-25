# Research: Restore contact–user channel and name sync

**Feature**: `007.1-restore-channel-sync`

Cite (opened this turn):

- https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
- https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
- https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md
- https://github.com/espocrm/documentation/blob/master/docs/development/coding-rules.md
- https://github.com/espocrm/documentation/blob/master/docs/development/metadata.md
- https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/formula.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
- https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
- https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md

Parent channel contract:
[`../004.1-repair-create-crm-user/contracts/email-phone-sync.md`](../004.1-repair-create-crm-user/contracts/email-phone-sync.md)

## Decision: Keep `ContactUserChannelSync`; widen who it runs for

**Decision**: Change `isPersonnelContact` to `ContactTypeSet::wantsCrmUser`
(Volunteer, Employee, or MemberContact / Associato, including legal
pairs). Keep the same afterSave hooks and
`nonprofitSkipContactUserChannelSync`.

**Rationale**: 004.1 copied full `emailAddressData` / `phoneNumberData`
both ways, but only when `hasPersonnel` (Volunteer/Employee). 004.3 gave
Associato a CRM user. Rossella is Member-only, so the Tool returns
before copy. Local save of the email already works; the counterpart is
never written.

**Alternatives considered**:

- New Tool for Associato — rejected; one identity copy.
- Formula after-save — rejected; Formula cannot copy Email/Phone *sets*
  (fields.md: Email is a set with Opted-out / Invalid / Primary; Phone
  the same plus Type), cannot set a custom SaveOption, cannot keep the
  empty-email guard. Workflows/BPM not installed.

## Decision: Copy prefix, first name, last name in the same Tool

**Decision**: When either side of a linked pair is saved, also copy
`salutation`, `firstName`, `lastName` if they differ. Do not copy
`middleName`, `userName`, or `assignedUserId`. Trigger when those
attributes change, not only when channels change.

**Rationale**: 004.1 contract listed name as out of live sync. Owner now
wants name plus every email and phone. personName on Contact and User is
the same field type (entity-defs / fields). Create already prefills
name; later edits drift.

**Alternatives considered**:

- User `ContactProfileLoader` already sets `linkedContactName` for
  display — that is a label, not the User’s own first/last name.
- Formula `firstName = linkedUser.firstName` — rejected; one-way, no
  skip flag, no type filter.

## Decision: Empty contact email does not wipe the user email

**Decision**: If the source email set is empty, do not write an empty
set onto the counterpart. Still copy phones and name if those changed.
If staff deleted extra emails but left at least one, copy the remaining
set.

**Rationale**: Spec default. Current `copyIfDifferent` would save an
empty `emailAddressData` and could clear the login address.

**Alternatives considered**: Block save when contact email is emptied —
rejected; staff may clear the contact field without meaning to lock the
user out.

## Decision: Uniqueness stays as 004.2 validators

**Decision**: Do not add another uniqueness class. `UniqueAmongContacts`
already ignores the linked User (conflict is another Contact).
`UniqueAmongUsers` ignores the linked Contact. Native duplicate-check
dialog is the skippable UI; hard validators stay.

**Rationale**: Local probe already saved the user’s address onto
Rossella’s contact. Spec FR-005 is already the 004.2 rule.

## Decision: Retired Member table is not this copy’s job

**Decision**: Channel/name copy does not query entity type `Member`.
Production `Table '…member' doesn't exist` is parent 007 US1 (leftover
after `DropRetiredPartyTables`). This amendment does not recreate the
table. If implement finds a runtime query of entity type `Member` in
NonprofitEspocrm, stop that query (metadata/scope check or remove the
call). Do not hunt GoogleIntegration calendar seeds in this feature
unless Contact save still 500s locally after the Tool change.

**Rationale**: Local Rossella save already succeeds. Owner asked for
sync restore, not a prod apply. 007 never got a plan; keep the 500 as a
known prod blocker, not a schema change here.

## Decision: Path = Code

Formula / Workflows / BPM / a new entity / client JS for this copy:
rejected. Tests: PHPUnit unit on the Tool (tests.md). Rebuild after
comment-only hook edits if metadata unchanged: still
`ddev exec php command.php rebuild` when PHP hook cache needs it
(commands.md).
