# Data model: Contact-first volunteer/employee CRM user

**Feature**: `004-contact-first-crm-user`  
**Date**: 2026-09-15

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md

No new entity types. Changes are fields, links already present, and which
record stores competences.

## Contact (person)

Person record. Standard CRM Contact + NonprofitEspocrm fields.

### Identity / status (existing)

| Field | Type | Notes |
|-------|------|--------|
| `contactType` | enum | Volunteer / Employee trigger create-user + competences UI |
| `personnelStatus` | enum Active/Inactive | Formula + User-delete hook |
| `linkedUser` | link → User | **Person identity** (Q1 A). `linkedUserId` indexed |
| `portalUser` | link → User | Unchanged; not this feature’s CRM-user checkbox |
| `isUser` | bool, readOnly | Derived from linked/portal (existing hook) |
| `assignedUser` | link → User | **Ownership** (ACL own). Not identity |

### Volunteer / employee profile (existing, stay on Contact)

`isOccasional`, `startDate`, `endDate`, `contractType`, `weeklyHours`,
`monthlyHours` (readOnly, Formula), `extra`, `taxCode`, `birthDate`,
`birthPlace`, `birthProvince`. Member-only: `joinDate`, `leaveDate`,
`positionsHeld`, `notes`.

### New / moved

| Field | Type | Storage | Validation / logic |
|-------|------|---------|--------------------|
| `activityCompetences` | multiEnum | **Storable** | Same option list as today’s User field (`MealPreparation`, `MealDistribution`, …). Visible only Volunteer/Employee. Empty = all shift categories (application rule, not a DB constraint) |
| `createCrmUser` | bool | **notStorable** | Default true. Visible only Volunteer/Employee on create. Never a schema column |

### Relationships

- `linkedUser` belongsTo User, foreign `linkedContacts` (existing).
- Shift planner does **not** become Contact-keyed; ActivityInvite stays
  User-keyed.

### State: personnelStatus

- Formula: date window Active/Inactive for Volunteer/Employee/MemberContact.
- User `afterRemove`: force Inactive for Contacts with this `linkedUserId`
  or `portalUserId`. Contact row remains.

## User (login)

Standard Espo User. Volunteer profile fields except name/email/phone are
**mirrors** of the linked Contact.

| Field | After this feature |
|-------|-------------------|
| `userName`, type, roles, teams, password / access info | Unchanged Espo |
| `firstName`, `lastName`, `emailAddress`, `phoneNumber` | May stay stored on User (copied at create) |
| hours, dates, extra, tax/birth, member fields | Already `notStorable` + `ContactProfileLoader` |
| `activityCompetences` | After migration: **`notStorable`** mirror, same options |
| `isOccasional` | **Unchanged this feature** (still stored on User + Contact; existing `SyncOccasionalToUser`) |
| `sourceContactId` (new) | notStorable, layout-disabled; create-from-Contact handshake |
| `hasVolunteerRole` / `hasEmployeeRole` / `hasMemberRole` | Existing notStorable flags from Roles |

### Relationships

- `linkedContacts` hasMany Contact (existing). Happy path: at most one
  Volunteer/Employee Contact per User for this flow.

### Validation

- Portal Users: skip profile sync (existing).
- Creating a User from Contact MUST NOT invent a second Contact.

## Shift planning (unchanged actors)

| Concept | Storage |
|---------|---------|
| Invitee / availability actor | User |
| Eligibility categories | Contact.`activityCompetences` for Contact where `linkedUserId` = that User |
| Empty competences | Treat as all categories (existing PHP) |

No new shift entities.

## Migration (data)

1. Rebuild after Contact field → Contact column exists.
2. For each non-deleted User with storable `activityCompetences`, find
   Contact(s) with `linkedUserId` = User id and `contactType` in
   Volunteer, Employee; write the list onto Contact (ORM attributes).
3. Mark User field notStorable; rebuild drops User column.
4. Local wipe + prod sample: **not** a schema object; owner-gated
   operational step (see `contracts/competence-migration.md`).

## Indexes

Existing `Contact.indexes.linkedUser` on `linkedUserId` is enough for
planner lookup. No new entity. Optional later: none required for this
feature.
