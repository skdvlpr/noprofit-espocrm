# Contract: Contact type combinations

**Feature**: `004.3-contact-types-lead-convert`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/api-search-params.md

## Field

`Contact.contactType` is multiEnum, max two values, no custom options, display as labels.

## Legal sets

- Empty or exactly one of the existing option keys.
- `{Volunteer, MemberContact}`
- `{Employee, MemberContact}`

Everything else MUST fail field validation (HTTP 400), including mass-update.

## UI

- Dynamic Logic `has` + `or` shows volunteer/employee vs member panels and shared vs exclusive fields ([data-model.md](../data-model.md)).
- Client MUST NOT offer Volunteer together with Employee.
- On Contact **create**, the first pick of Volunteer-only or Employee-only MUST stay selected (must not clear the field when the opposite type is removed from the option list).
- Non-admins MUST NOT be able to add Volunteer or Employee (hook + UI option filter, 004.2).

## Filters

Primary filters MUST use array containment so Volunteer+Member appears in both Volontari and Associati.
