# Contract: Review modal (create Contact + User)

**Feature**: `004.2-create-user-review`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md

## Trigger

Contact **create** (full + small). `createCrmUser` on. Type Volunteer or
Employee. Email present. User clicks Save.

MUST NOT open on checkbox toggle. MUST NOT open on existing-record edit.

## Panel

- Class: `dialog dialog-record` (theme side drawer).
- Identity fields locked from Contact.
- `rolesIds` pre-filled Volunteer or Employee by Contact type.
- No password / confirm / generate.
- Invite checkbox: custom label, default on.
- Footer Save / Cancel.

## Persist

- Cancel: no Contact POST, no User POST.
- Save: Contact POST then User POST with `sourceContactId`,
  `sendAccessInfo` as checked, **no** `password`. `linkFromSourceContact`
  sets only `linkedUserId` (not Assigned User).
- User POST failure: show error; if Contact was just created in this
  attempt, remove it so Cancel-like leftover does not remain.

## Mutually exclusive pick

While checkbox on: hide `linkedUser`, clear id.
