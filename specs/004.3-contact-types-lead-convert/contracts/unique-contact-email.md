# Contract: Unique email among Contacts

**Feature**: `004.3-contact-types-lead-convert`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md

## Rule

A Contact MUST NOT be created or updated so that any of its email addresses
(primary or extra) equals an address already used by **another** Contact
(`deleted` false). Comparison is case-insensitive.

Allowed: the same address on Contact and **that Contact’s** linked User
(004.2).

Not sufficient: skippable native duplicate dialog alone. Native convert
creates Contact with `skipDuplicateCheck` — the hard validator MUST still
run.

## Implementation surface

- Keep `scopes.Contact.duplicateCheckFieldList` including `emailAddress`
  (warning dialog).
- `recordDefs.Contact.updateDuplicateCheck`: true.
- `duplicateWhereBuilderClassName` MAY stay General **or** a Contact
  builder that ORs email addresses; warning only.
- Hard `validatorClassNameList` on Contact `emailAddress` (same idea as
  `UniqueAmongUsers`). Query via EmailAddress entity + entity-email-address
  relation. MUST NOT hardcode SQL table names.
- Applies to Contact create/edit, quick create, convert, and API.

## Existing collisions

List colliding addresses on DDEV before relying on the hard validator in
UAT. Do not merge people automatically. Prod inventory only when the
owner names it.
