# Data model: 004.2 create-user review

**Feature**: `004.2-create-user-review`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md

No new entity types.

## Contact

| Field | Change |
| :--- | :--- |
| `createCrmUser` | Unchanged: bool, notStorable, default true, `layoutDetailDisabled`. UI only when `isNew()`. |
| `linkedUser` | Hidden while `createCrmUser` on a new record. |
| `contactType` | Volunteer / Employee settable only by admin (hook + UI options). |
| `activityCompetences` and volunteer/member profile | Unchanged: **stored here**. |

## User

| Field | Change |
| :--- | :--- |
| `isOccasional` and other volunteer/member profile fields | `notStorable` + `readOnly`. Loaded from Contact. |
| `sourceContactId` | Unchanged handshake for create. |
| `emailAddress` | Unique among Users (validator). May match linked Contact. |
| `sendAccessInfo` | Panel-only custom label; stock User create unchanged. |

## Roles

Look up by **name** `Volunteer` / `Employee`. Do not hardcode ids.

## Validation

- New User: email required on this panel; must not exist on another User.
- Contact create with Crea utente CRM: email required.
- Non-admin: cannot save `contactType` Volunteer or Employee.

## State

1. Create form, checkbox off → Contact only.
2. Create form, checkbox on, Save → review panel (nothing stored).
3. Review Save → Contact then User, `linkedUserId` set.
4. Review Cancel → back to unsaved create form.
