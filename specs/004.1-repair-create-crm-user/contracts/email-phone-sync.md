# Contract: Contact ↔ User email and phone sync

**Feature**: `004.1-repair-create-crm-user`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md

## When

| Source save | Counterpart | Condition |
|-------------|-------------|-----------|
| Contact afterSave | User `linkedUserId` | Type Volunteer or Employee; `emailAddressData` or `phoneNumberData` changed |
| User afterSave | Contact with `linkedUserId` = this User | Same type filter on that Contact |

Help-seeker, unlinked, portal/system User: no-op.

## What

Copy the **full set**:

- `emailAddressData` (address, primary, opted-out, invalid)
- `phoneNumberData` (number, type, primary, opted-out, invalid)

Primary scalar `emailAddress` / `phoneNumber` follows the primary row.

## Loop

Save counterpart with `SaveOption` `nonprofitSkipContactUserChannelSync`.
If incoming set equals current set (case-insensitive email; trimmed
phone), do not save.

## Identity

MUST NOT create a User or Contact. MUST NOT change `assignedUserId`.
MUST NOT copy competences or hours here (004 profile sync stays separate).

## Tests (PHPUnit)

- Volunteer Contact email set → User set matches.
- User extra phone → Contact matches.
- Help-seeker → User never written.
- Skip option → no second write.
- Empty counterpart link → no-op.

## Out of contract

- Name (`firstName` / `lastName`) live sync (not requested).
- LearnHouse / other CRMs.
- Sharing one email across two Users (native unique email: surface Espo
  duplicate error, do not invent a merge).
