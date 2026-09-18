# Contract: Contact profile reflection on User

**Feature**: `004.2-create-user-review`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md

Parent 004: competences live on Contact; planner reads Contact via
`linkedUserId`. User MAY show mirrors.

## Source of truth

Contact stores: `activityCompetences`, `isOccasional`, hours, dates,
extra, tax/birth, member positions/notes.

User shows the same values via `ContactProfileLoader` (`readLoader`
`__APPEND__`). Fields on User: `notStorable`, `readOnly`.

## MUST NOT

- Copy those values from User save back onto Contact
  (`writeProfileToContact` for volunteer/member profile fields).
- Keep `User.isOccasional` as a stored second copy.
- Change planner to read `$user->get('activityCompetences')` as storage.

## MAY

- Keep 004.1 email/phone **channel** sync (login identity).
- Keep `linkedContact` read-only link on User for navigation.

## Visibility

Existing User dynamic logic (volunteer/employee/member role flags) still
hides panels when the User has no matching role.
