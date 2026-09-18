# Contract: Admin-only Volunteer / Employee Contact

**Feature**: `004.2-create-user-review`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md

Admin = Espo `User::isAdmin()` (type admin), not a Role named Admin.

## UI

Non-admin Contact forms: `contactType` options MUST NOT include
`Volunteer` or `Employee`. Other types remain.

## Server

Contact beforeSave: if `contactType` is Volunteer or Employee and the
current user is not admin → Forbidden. Covers create and type change.

## Out of scope

- Non-admin editing other fields on an existing Volunteer they can already
  read (if ACL allows). They still cannot **set** the type to
  Volunteer/Employee.
- Creating CRM Users: native User create remains admin (Espo).
