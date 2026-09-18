# Contract: Create CRM user from Contact

**Feature**: `004-contact-first-crm-user`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/frontend/view-setup-handlers.md
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md

## UI (Contact create / edit)

| Rule | Value |
|------|--------|
| Field | `createCrmUser` (bool, notStorable, default **true**) |
| Visible | `contactType` in `Volunteer`, `Employee` only |
| Hidden | Help-seeker, MemberContact, Colleague, Other, empty type |
| When off | Save Contact only; no User modal |
| When on, no `linkedUserId` | After successful Contact save, open User **create** dialog |
| Prefill | `firstName`, `lastName`, `emailAddress`, `phoneNumber` from Contact |
| Handshake | Pass Contact id (`sourceContactId` or equivalent notStorable on User) |
| Already linked | Do not open a second User create |

Labels: Italian primary + English (`en_US`, `it_IT`). Suggested English:
“Create CRM user”. Italian: choose a short label consistent with existing
“Utente CRM” wording at implement (do not invent a second identity term).

User create still uses Espo access-info checkbox and password behaviour
(https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md).

Implementation hook: Contact `recordViews.edit` (and create uses edit) or
`viewSetupHandlers` `record/edit` with `__APPEND__`. Prefer extending the
existing Nonprofit Contact record views rather than a second Global handler.

## Server (User afterSave)

`Hooks/User/SyncContactProfile` → `UserContactProfileSync`:

1. Portal User → return (existing).
2. `SKIP_ALL` → return (existing).
3. If `sourceContactId` present and Contact exists: set that Contact’s
   `linkedUserId` to the new User; update profile **from Contact** (Contact
   is already the store); **do not** `getNewEntity('Contact')`; **do not**
   set `assignedUserId` to the new User.
4. Else if Contacts already exist for `linkedUserId`: update profile
   fields that the User form still mirrors (existing VOLUNTEER/MEMBER
   lists; add competences to the **load** list; write-back only for fields
   still editable on User).
5. Else if Volunteer or Employee role and no Contact: **do not** auto-create
   a Contact (Contact-first).
6. Member role without Contact: MAY keep today’s create (out of checkbox
   scope).

## Loop guard

Saving the Contact after linking MUST NOT open another User create.
Saving the User MUST NOT create a sibling Contact for the same person.

## Out of contract

- Creating a portal user from Contact.
- Member Contact “Create CRM user” checkbox.
- Live two-way sync of name/email/phone after the initial copy.
