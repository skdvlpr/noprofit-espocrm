# Contract: Contact ↔ User identity copy (channels + name)

**Feature**: `007.1-restore-channel-sync`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md

Amends:
[`../../004.1-repair-create-crm-user/contracts/email-phone-sync.md`](../../004.1-repair-create-crm-user/contracts/email-phone-sync.md)

## When

| Source save | Counterpart | Condition |
|-------------|-------------|-----------|
| Contact afterSave | User `linkedUserId` | `wantsCrmUser`; name or email/phone set changed |
| User afterSave | Contact with `linkedUserId` = this User | Same type filter on that Contact |

Help-seeker, unlinked, portal/system/api User: no-op.

## What

Copy the **full** email set and **full** phone set (004.1), plus:

- `salutationName` (prefix)
- `firstName`
- `lastName`

Primary scalar `emailAddress` / `phoneNumber` follows the primary row.

If the source email set is empty, **do not** clear the counterpart’s
emails. Still copy phones and name when those differ.

If incoming identity equals current (case-insensitive email; trimmed
phone; exact name strings), do not save.

## Loop

Save counterpart with SaveOption `nonprofitSkipContactUserChannelSync`.
MUST NOT create a User or Contact. MUST NOT change `assignedUserId` or
`userName`. MUST NOT copy competences, hours, board, or PDF here.

## Tests (PHPUnit)

- Associato Contact email set → User set matches.
- Associato Contact firstName → User firstName matches.
- User extra phone → Contact matches (Volunteer or Employee regression).
- Empty Contact email set → User emails unchanged.
- Help-seeker → User never written.
- Skip option → no second write.
- Empty counterpart link → no-op.

## Out of contract

- `middleName`
- Username
- LearnHouse / other CRMs
- Recreating retired `Member` table
- Sharing one email across two Users (native + UniqueAmongUsers)
