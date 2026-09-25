# Data model: On-demand admission PDF

No new entity type. No new columns.

## Lead / Contact (unchanged fields, new write rules)

Printed at view time from the open record:

| Field | Role on the form |
|-------|------------------|
| firstName, lastName | Applicant name |
| birthPlace, birthProvince, birthDate | Birth block |
| taxCode | C.F. |
| addressStreet, addressCity, addressState, addressPostalCode | Residence |
| emailAddress, phoneNumber | Contacts |
| contactType | Associato gate (`MemberContact`) |
| admissionBoardDate, admissionOutcome, memberBookNumber, admissionFeePaid, admissionReceiptNumber, newsletterConsent | Consiglio Direttivo |
| description | Fallback newsletter line from the website |

`admissionForm` (File) stays in entityDefs so leftover ids can be cleared.
It MUST NOT be written on save, GET, or convert leftover cleanup except
to **null**. Layouts already omit it.

Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md

## Template records (seeded, not a custom entity)

| Name | entityType | Body |
|------|------------|------|
| `Domanda di ammissione a socio` | Lead | Same HTML as today |
| `Domanda di ammissione a socio (Contact)` | Contact | Same HTML |

Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md

## Attachments

Admission-form Attachments created by 005 / 005.1 / 005.2 MUST be
removed when the correction rebuilds. Convert MUST NOT leave a new copy.

## Convert

Native convert still copies same-name same-type fields, including File
if a leftover id exists. After that, both `admissionFormId` values are
cleared. Board field copy is unchanged.

Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
