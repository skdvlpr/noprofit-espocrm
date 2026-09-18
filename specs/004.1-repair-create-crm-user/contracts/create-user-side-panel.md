# Contract: Create CRM user side panel

**Feature**: `004.1-repair-create-crm-user`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/client-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md

Replaces the 004 after-save modal in
[`../../004-contact-first-crm-user/contracts/create-user-from-contact.md`](../../004-contact-first-crm-user/contracts/create-user-from-contact.md)
for UI. Server handshake (`sourceContactId`) stays.

## Layouts

| Layout | `createCrmUser` | `linkedUser` |
|--------|-----------------|--------------|
| Contact **edit** (new) | Yes | Optional |
| Contact **detailSmall** | Yes | No |
| Contact **detail** | **No** | Yes |

`app.layouts` MUST map Contact `edit` to NonprofitEspocrm.

## Checkbox

- Types: Volunteer **and** Employee only.
- New record: default **on**.
- Existing record with no `linkedUserId`: shown, default **off**.
- Already linked: hidden.
- Quick create uses the same rules (`recordViews.editQuick` / edit-small).

## Drawer

- Open when checkbox becomes true (including default-on after type is set
  on a new record).
- Class: Espo record dialog (`dialog dialog-record`) so Aurora docks it
  on the side.
- Prefill from Contact: `firstName`, `lastName`, `emailAddress`,
  `phoneNumber` (and data arrays if already on the model).
- Those four are **read-only in the drawer**. `userName`, teams, roles,
  send-access are editable.
- Confirm on the drawer: stash attributes on the Contact record view; do
  **not** POST User yet.
- Cancel / close without confirm: set `createCrmUser` false.

## Contact save (client)

1. Save Contact as usual.
2. If stashed draft and still no `linkedUserId`: `POST User` with
   `sourceContactId`, copied identity, login fields, `sendAccessInfo`,
   **no** `password` / `passwordConfirm` when send-access is on.
3. `fetch` Contact so **Utente CRM** is visible without opening
   Administration > Users.
4. MUST NOT open a second User create after that fetch.

## Server (unchanged handshake)

`UserContactProfileSync::linkFromSourceContact`: set `linkedUserId` only;
`SKIP_ALL`; never `assignedUserId`; never `getNewEntity('Contact')` for
Volunteer/Employee.

## Out of contract

- Portal users.
- Member checkbox.
- Posting User before Contact exists.
