# Contract: Lead types and native convert copy

**Feature**: `004.3-contact-types-lead-convert`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/layout-manager.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md

## Lead type

Lead `contactType` multiEnum, **maxCount 2**, options:

| Key | Staff label |
|-----|-------------|
| Volunteer | Volunteer / Volontario |
| Employee | Employee / Dipendente |
| MemberContact | Member / Associato |
| Other | Generic / Generico |

Legal sets match Contact: empty, one of those keys, `{Volunteer, MemberContact}`, or `{Employee, MemberContact}`. Web forms typically post one key; staff may add the second by hand.

## Extra fields

Volunteer and member extra fields MUST NOT appear on Lead layouts. Staff
fill them on convert Contact (`detailConvert`). Native Convert copies
`contactType` (same name + multiEnum type). Do not add a differently
typed `leadType` enum as the only type field — Convert would skip it.

Empty historical Lead type → treat as Generic (`Other`) when opening
convert.

## Convert outcomes

Stock checkboxes Account / Contact / Opportunity remain. This feature
requires Contact when creating a CRM User. Converted Lead is not deleted;
Converted To panel stays native.

Contact `detailConvert` layout: type, identity, extra fields for the
copied type(s), Crea utente CRM. Module layout via `app/layouts.json`.

Closing the CRM-user review without confirm MUST leave Lead unchanged
and MUST let Convert (or Contact create Save) open the review again.
