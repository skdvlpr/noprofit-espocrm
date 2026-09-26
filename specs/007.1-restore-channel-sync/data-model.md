# Data model: Restore contact–user channel and name sync

**Feature**: `007.1-restore-channel-sync`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md

No new entity types. No new fields. No migration.

## Contact

| Field | Role |
|-------|------|
| `contactType` (multiEnum) | Copy runs when `ContactTypeSet::wantsCrmUser` (Volunteer, Employee, MemberContact, or legal pair). Help-seeker and others: no-op |
| `linkedUserId` | Counterpart. Empty: no-op |
| `emailAddress` + `emailAddressData` | Full set: address, primary, opted-out, invalid |
| `phoneNumber` + `phoneNumberData` | Full set: number, type, primary, opted-out, invalid |
| `salutationName`, `firstName`, `lastName` | Copied both ways |
| `assignedUserId` | Ownership. MUST NOT change |
| Board / PDF / hours / competences | Out of this copy |

## User

| Field | Role |
|-------|------|
| `type` | portal / system / api: never written |
| `emailAddress` + `emailAddressData` | Same set as Contact |
| `phoneNumber` + `phoneNumberData` | Same set as Contact |
| `salutationName`, `firstName`, `lastName` | Same as Contact |
| `userName` | MUST NOT copy |
| Volunteer/member profile fields | Stay `UserContactProfileSync` / loader. Not this Tool |

## Eligibility

```text
Contact has linkedUserId
AND contactType wants a CRM user (Vol / Emp / Associato / legal pair)
AND User is not portal/system/api
→ copy identity
else → no-op
```

## SaveOption

`nonprofitSkipContactUserChannelSync` — counterpart save MUST set this
so hooks do not bounce. MUST NOT use `SKIP_ALL` on that save (email and
phone savers must run).

## Empty email

Source email set empty → do not overwrite counterpart emails.
Phones and name still copy if they changed.

## Validation (unchanged)

- Another Contact owning an address: `UniqueAmongContacts`
- Another User owning an address: `UniqueAmongUsers`
- This Contact and its linked User may share an address
