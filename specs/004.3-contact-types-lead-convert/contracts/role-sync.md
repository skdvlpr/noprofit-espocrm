# Contract: User Roles follow Contact types

**Feature**: `004.3-contact-types-lead-convert`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md

## On create-user review

Pre-fill Roles whose names match the Contact types that will be saved
(Volunteer and/or Employee and/or Member). Admin may still change Roles
on the panel (004.2).

## On later Contact edit

If `linkedUser` is set and `contactType` changes:

- Add missing Volunteer / Employee / Member Roles that the types require.
- Remove those three Roles when the type is no longer present.
- MUST NOT create a second User.
- MUST NOT strip unrelated Roles (Teams-inherited or other named Roles).

No Crea utente CRM on edit (004.2).

## Lookup

Resolve Role records by **name** through ORM. MUST NOT hardcode Role ids.
