# 035 — Convert empty contractType + User isActive → Contact Inactive

**Date:** 2026-09-21  
**Feature:** `004.3-contact-types-lead-convert`

## Convert 400

Log: `Contact field: contractType, type: valid` on `POST /Lead/action/convert`.
The toast looks like `contactType`; the field is employee **Tipo contratto**,
hidden for Volunteer. Convert copies all same-name Lead fields, so
`contractType: null`/`""` is posted. Native enum `valid` rejects empty
unless `""` is an option.

Fix: optional enum `""` + `EmptyStringToNull`; convert payload drops empty
`contractType` unless Employee is selected. Formula uses `array\includes`
so Volunteer+Member still computes monthly hours / date-window status.

Path = **Metadata** + **Formula** + **Code** (convert JS). Rejected:
forking ConvertService.

## User Is Active

`InactivateLinkedContacts` now also `AfterSave`: `isActive` → false sets
linked Contact `personnelStatus` Inactive with `SKIP_ALL` (same as delete).
Does not auto-reactivate on Is Active = true.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/formula/array.md
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md

## Still owner-owned

- Git commit / push — ask
- Prod apply of 004.3 — not named
- Live volunteer mail — not named
