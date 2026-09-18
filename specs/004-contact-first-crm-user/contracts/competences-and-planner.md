# Contract: Competences storage and planner read path

**Feature**: `004-contact-first-crm-user`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md

## Catalog (unchanged values)

Reuse User `activityCompetences` options and
`translation`: `ActivityOfferSlot.options.category`. Do not invent a second
option list.

Empty array **or** missing Contact **or** User with no Volunteer/Employee
Contact → planner treats as **all categories** (today:
`$competences === []` in `AvailabilityWorkflow`).

## Write path (source of truth)

- Staff edit competences on **Contact** (Volunteer / Employee panel).
- Contact layout `personnelVolunteerEmployee` MUST include
  `activityCompetences` (module layouts already mapped in
  `metadata/app/layouts.json` for Contact detail).
- Dynamic Logic: visible when `contactType` in Volunteer, Employee
  (same pattern as `weeklyHours`).

## User mirror

- User field remains on the volunteering panel **if** the User screen
  still shows the profile (owner: mirrors allowed).
- After migration: `"notStorable": true` on User.
- `ContactProfileLoader` loads Contact values onto User (add
  `activityCompetences` to `VOLUNTEER_FIELDS` or a dedicated load list).
- `recordDefs/User.json` `readLoaderClassNameList` MUST start with
  `"__APPEND__"` when extending
  (https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md).

## Planner read path (MUST)

`ShiftPlanningSupport::getUserCompetences(User $user)`:

1. Find Contact where `linkedUserId` = `$user->getId()` (ORM repository,
   not SQL table name). Prefer Volunteer/Employee if several.
2. Read `activityCompetences` from that Contact.
3. Return string list; non-array → `[]`.
4. MUST NOT rely on `$user->get('activityCompetences')` as storage
   (Record loaders are not applied on raw EntityManager get).

Invitees remain Users. Do not switch ActivityInvite to Contact ids.

## Visibility

- Non Volunteer/Employee Contact: field hidden (Dynamic Logic).
- User without volunteer/employee **role** flags: User mirror hidden
  (existing User `dynamicLogic`).
