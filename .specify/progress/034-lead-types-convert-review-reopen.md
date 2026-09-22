# 034 — Lead types-only + convert fields + review-reopen

**Date:** 2026-09-21  
**Feature:** `004.3-contact-types-lead-convert`  
**Owner:** Convert works; extras not wanted on Lead; second type + Employee;
review freeze after Cancel.

## Product

- Lead is a type picker: Volunteer, Employee, Member, Generic. Same legal
  combos as Contact (`maxCount: 2`). Forms still typically post one type.
- Volunteer/member extra fields are **not** on Lead layouts
  (`layoutAvailabilityList: []` so Layout Manager cannot put them back).
  Staff fill them on convert Contact (`detailConvert`). Native Convert
  copies `contactType` (same name + multiEnum). Extra Lead field defs stay
  so a hard rebuild is not required.
- Convert of Volunteer+Member (or Employee+Member) copies **both** types
  so both Contact panels appear immediately.

## Bug (review freeze)

Two causes:

1. Nested view `createCrmUser` left on the parent after dialog
   `onDialogClose` → `remove()`. Next Convert / Save no-op’d if the parent
   treated that key as busy.
2. Contact create: `save()` **resolved** on Cancel, so
   `views/record/edit` `actionSave` called `exit('create')` and/or left
   the handler in a finished state. Second Save did nothing. Cancel now
   `Promise.reject('cancel')` (same as native duplicate-modal cancel).

Fix: `ContactTypeSet.presentCreateCrmUserReview` (seq + `clearView` /
`close` before reopen + `remove` listener) shared by Contact edit and
Lead convert.

Path = **Code** (custom convert + Contact edit + Lead record edit +
metadata). Rejected: forking ConvertService; Dynamic Logic options on the
same field.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/development/view.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md

## Spec

Updated US3/US4, FR-007–010, SC-003/004/007, contract, data-model, plan,
research R5/R6, tasks T041–T043, owner-user-tests 6–8 + 12.

## Still owner-owned

- Git commit / push — ask
- Prod apply of 004.3 — not named
- Live volunteer mail — not named
