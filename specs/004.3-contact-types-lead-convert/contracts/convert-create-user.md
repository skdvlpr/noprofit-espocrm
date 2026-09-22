# Contract: Convert Lead + create CRM user

**Feature**: `004.3-contact-types-lead-convert`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/modules.md

## UI

Override Lead convert in `client/custom` (extend stock convert view). MUST
NOT edit `application/Espo/Modules/Crm/Tools/Lead/ConvertService.php` or
core `modules/crm/views/lead/convert`.

Crea utente CRM on the Contact convert panel:

- Default **on** if Lead type is Volunteer or MemberContact.
- Default **off** if Generic (`Other`).
- Toggling MUST NOT open a panel.

## Persist

- Checkbox **off**: one native `Lead/action/convert` as today.
- Checkbox **on**: Convert/Save opens the **same** 004.2 review
  `dialog dialog-record`. Confirm → convert POST then User create
  (roles from resulting Contact types; Generic → none of
  Volunteer/Employee/Member). Cancel / dismiss → **no** convert POST,
  Lead status unchanged, 0 Contact, 0 User.
- No password fields; invite = set-password link (004.2).
- Unique emails: User among Users, Contact among Contacts.
- Assigned User on Contact = converting staff.
- Volunteer type still admin-only (Contact create Forbidden).

## Mapping

| Lead type | Contact types | User Roles if created |
|-----------|---------------|------------------------|
| Volunteer | `["Volunteer"]` | Volunteer |
| MemberContact | `["MemberContact"]` | Member |
| Other | `["Other"]` | none of the three |
