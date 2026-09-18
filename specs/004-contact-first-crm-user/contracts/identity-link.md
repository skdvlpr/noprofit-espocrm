# Contract: Contact ↔ User identity vs ownership

**Feature**: `004-contact-first-crm-user`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md

## Identity (this person is this login)

- Contact field: `linkedUser` / `linkedUserId`.
- UI labels stay “CRM user” / “Utente CRM” (existing i18n).
- Contact detail panel `userLink` keeps `linkedUser` (+ `isUser` as today).
- User detail MUST show the linked Contact (existing `linkedContacts` or
  equivalent visible link). Do not replace this with Assigned User.

## Ownership (who owns the record for ACL own)

- Contact `assignedUser` is Espo **own**.
- Staff creating a Volunteer/Employee Contact keep themselves (or the
  default assignee) as Assigned User.
- Linking a new CRM User MUST NOT overwrite `assignedUserId` with that
  User’s id.

## Derived flag

- `isUser` remains read-only, true when `linkedUserId` or `portalUserId`
  is set (`Hooks/Contact/SyncIsUser`).

## Invariants

1. Q1 = A: identity ≠ Assigned User.
2. Portal User (`portalUser`) is independent; create-CRM-user checkbox
   creates a **regular** User (`users-management.md` Regular), not portal.
3. User delete inactivates Contacts found by `linkedUserId` **or**
   `portalUserId`; it does not clear identity by switching to Assigned User.
