# Data model: Website admission leads

No new entity. Lead and Contact gain the board section and one file. Existing type rules from 004.3 stay.

Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md

## Lead (existing + this feature)

| Field | Type | Rule |
|-------|------|------|
| `contactType` | multiEnum | Unchanged. Socio sends `["MemberContact"]`. Volunteer sends `["Volunteer"]`. |
| `source` / `status` | enum | Site sends `Web Site` / `New`. |
| `firstName`, `lastName`, `emailAddress`, `phoneNumber` | existing | Not made required by this feature. |
| `addressStreet`, `addressCity`, `addressState`, `addressPostalCode`, `addressCountry` | address | `addressState` is residence province. Not required. |
| `taxCode` | varchar | Not required. No `ItalianFiscalCode`. Uppercase, spaces removed. Long enough to store a bad paste (32). Shown on detail and edit. |
| `birthDate` | date | Not required. Shown. |
| `birthPlace` | varchar | Not required. Shown. Label Luogo di nascita. |
| `birthProvince` | varchar | Not required. Shown. Label Prov. di nascita. |
| `description` | text | Site writes the five lines, including `Newsletter: acconsente` or `non acconsente`. |
| `admissionBoardDate` | date | Not required. Board meeting. Associato panel only. |
| `admissionOutcome` | enum | `""`, `Approved`, `Rejected`. One value. Labels Approvata / Respinta. |
| `memberBookNumber` | varchar | Not required. Libro Soci number. |
| `admissionFeePaid` | enum | `""`, `Yes`, `No`. One value. Labels Sì / No. |
| `admissionReceiptNumber` | varchar | Not required. Ricevuta/Tessera. |
| `admissionForm` | file | The one current PDF. Replaced on change. Cleared when convert moves it. |

Volunteer-only extras (`startDate`, competences, …) stay defined and off the layouts.

## Contact (existing + file)

| Field | Type | Rule |
|-------|------|------|
| `taxCode` | varchar | Keeps `ItalianFiscalCode`. Empty allowed. Invalid blocks convert. |
| `birthDate`, `birthPlace`, `birthProvince`, address, `contactType` | existing | Filled by native convert when the Lead has them. |
| `admissionForm` | file | Same type as Lead so convert copies it. Not regenerated here. |

Board fields are **not** copied to Contact. They are already inside the PDF. The Contact does not grow a second board form.

## PDF template (record, not a new entity type)

One Template: entity type Lead, status Active, name “Domanda di ammissione a socio”. Body is the paper module. Placeholder data `newsletterConsent` comes from the loader, not a column.

## States

- Lead created, Associato, board empty → PDF with empty board boxes and blank signatures.
- Board saved → same PDF replaced, boxes filled, still one file.
- Converted to Associato Contact → file on Contact, none on Lead. Lead status Converted (native).
- Volontario only → no PDF, no board panel.

## Who writes the board fields

Logged-in admin, or a user with Role **name** `Member`. Everyone else, including the website user, gets Forbidden if those attributes change.
