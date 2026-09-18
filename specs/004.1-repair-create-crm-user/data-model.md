# Data model: Repair Contact-first CRM user create

**Feature**: `004.1-repair-create-crm-user`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md

No new entity types. Handshake fields from 004 stay.

## Contact

| Field | Role in 004.1 |
|-------|----------------|
| `contactType` | Volunteer / Employee → show create-user flow |
| `createCrmUser` | notStorable bool. Default **true** on **new** Volunteer/Employee. Hidden on **detail**. Visible on **edit** / **detailSmall** when type is Volunteer or Employee and `linkedUserId` empty |
| `linkedUser` / `linkedUserId` | Person identity (004). After success, detail shows this, not the checkbox |
| `emailAddress` + `emailAddressData` | Set of addresses; source/target of sync |
| `phoneNumber` + `phoneNumberData` | Set of numbers; source/target of sync |
| `assignedUserId` | Ownership only — never set to the new volunteer User |

## User

| Field | Role in 004.1 |
|-------|----------------|
| `sourceContactId` | notStorable handshake from 004; set on POST; hook links that Contact |
| `firstName`, `lastName`, `emailAddress`(+Data), `phoneNumber`(+Data) | Copied into drawer read-only; later editable on User; then sync |
| `userName`, `teams`, `roles` | Editable in drawer |
| `sendAccessInfo` | notStorable native; default **true** in this drawer when email present |
| `password` / `passwordConfirm` | Hidden when send-access on; omitted from POST in that case |
| `linkedContact` | 004 notStorable mirror of the person Contact |
| `activityCompetences` | 004 notStorable mirror — do not make storable again |

## Access email

Not an entity. System templates `accessInfo` and `passwordChangeLink`.
Payload from core: `userName`, `siteUrl` (may include change-password
query), optional `password` (MUST be absent for this flow). Logo via
helper, not `{{siteUrl}}` as image src.

## SaveOption

`nonprofitSkipContactUserChannelSync` — unique flag so a sync save does
not bounce. MUST NOT reuse Educational `educationalSkipEmailSync`.

## Validation

- User POST from this flow requires `sourceContactId` of a Volunteer or
  Employee Contact with empty `linkedUserId` (or already this User).
- Send access info requires a non-empty email (native).
- Sync only if Contact type is Volunteer or Employee and `linkedUserId`
  matches the User.

## State (create-user)

```text
type not Volunteer/Employee → checkbox hidden, no drawer
new Volunteer/Employee → checkbox on → drawer draft
drawer closed without confirm → checkbox off
Contact saved + draft present → User created → linked
Contact saved + checkbox off → Contact only
existing Volunteer/Employee, no User → checkbox available (default off
  so we do not surprise-edit); staff can turn on
already linked → checkbox hidden
```
