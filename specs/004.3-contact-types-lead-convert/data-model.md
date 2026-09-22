# Data model: 004.3 contact types + Lead convert

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md

No new entity types. Soft rebuild after metadata. Type-value copy is a listed console command, not `rebuild --hard`.

## Contact

| Field | Type | Notes |
|-------|------|--------|
| `contactType` | multiEnum | Options stay: `HelpSeeker`, `Colleague`, `Volunteer`, `Employee`, `MemberContact`, `AssociationRepresentative`, `Other`. `maxCount: 2`. `displayAsLabel`. No custom options. |
| `createCrmUser` | bool notStorable | Visible on **create** (and convert Contact panel) when types include Volunteer, Employee, or MemberContact and no `linkedUserId`. Default true for those types. |
| Volunteer/employee extras | existing | `isOccasional`, dates, hours, `contractType`, `extra`, `activityCompetences` |
| Member extras | existing | `joinDate`, `leaveDate`, `positionsHeld`, `notes` |
| Shared person | existing | `taxCode`, `birthDate`, `birthPlace`, `birthProvince` — one layout row |
| `emailAddress` | email | Hard unique among Contacts (see contracts). |
| `linkedUser` | link | Unchanged. |

### Type validation

- Length 0 or 1: any current option or empty.
- Length 2: exactly `{Volunteer, MemberContact}` or `{Employee, MemberContact}` (order irrelevant).
- Volunteer ∩ Employee = illegal.
- Any other pair / three values = illegal.

### Type → Role names

| Contact type key | User Role name |
|------------------|----------------|
| Volunteer | Volunteer |
| Employee | Employee |
| MemberContact | Member |

## Lead

| Field | Type | Notes |
|-------|------|--------|
| `contactType` | multiEnum | `maxCount: 2`. Options: `Volunteer`, `Employee`, `MemberContact`, `Other`. Labels: Volunteer, Employee, Member, Generic. Same legal combos as Contact. |
| Volunteer/member extras | unused on Lead UI | Leftover defs may remain in metadata; layouts omit them. Fill on convert Contact. |
| `emailAddress` | email | Stock skippable duplicate among Leads. Convert still **hard-fails** if the Contact email is taken. |

Empty Lead type → UI default `Other` (Generic).

## User

No new stored profile fields. Reflection loader already maps Contact volunteer + member fields. Create-from-contact review Role list = all matching names. Role link-multiple updated on Contact type change (hook).

## Convert

Native outcomes: Account, Contact, Opportunity (unchanged list). Contact create during convert runs Record Service validation (unique email, legal types, admin types) even though ConvertService skips the duplicate **dialog**.

## State: Lead

`New` / in-process → `Converted` only after a successful convert POST. Cancel review MUST leave status unchanged.

## Migration

1. Inventory Contact emails that collide (DDEV always; prod when named). Owner cleans; no auto-merge.
2. Copy `contactType` enum strings to multiEnum arrays (console, ORM).
3. Soft rebuild.
4. Do not drop leftover User columns in this spec.

## Filters

Volunteers / Employees / Associati / VolunteersEmployees: `arrayAnyOf` the relevant type key(s), plus existing occasional rules.
