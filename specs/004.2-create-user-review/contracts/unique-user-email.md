# Contract: Unique email among Users

**Feature**: `004.2-create-user-review`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md

## Rule

A User MUST NOT be created or updated so that any of its email addresses
(primary or extra) equals an address already used by **another** User
(`deleted` false). Comparison is case-insensitive.

Allowed: the same address on Contact and **that Contact’s** linked User.

Not sufficient: skippable native duplicate dialog alone.

## Implementation surface

- `recordDefs.User.duplicateWhereBuilderClassName` in NonprofitEspocrm
  (create warning).
- Hard validator or User beforeSave that throws Conflict/BadRequest
  (cannot skip).
- Applies to this review panel **and** Administration → Users create/edit
  (same entity). MUST NOT patch core User service.

## Local cleanup (already done)

Kept `semen.koksharov`. Removed today-created `tster`, `testantidub` on
DDEV only.
