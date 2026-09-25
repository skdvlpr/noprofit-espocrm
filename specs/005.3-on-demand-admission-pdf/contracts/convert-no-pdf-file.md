# Contract: Convert copies answers, not the PDF file

Staff convert. The CRM does not convert on Lead create.

Native convert copies onto Contact when the name and type match:

- `contactType`
- address attributes
- `taxCode`, `birthDate`, `birthPlace`, `birthProvince`
- board: `admissionBoardDate`, `admissionOutcome`, `memberBookNumber`,
  `admissionFeePaid`, `admissionReceiptNumber`, `newsletterConsent`

Native convert **also** duplicates a File field of the same name
(`admissionForm`) if the Lead still has an old id. After a successful
Associato convert, both Lead and Contact MUST have `admissionForm`
empty and those Attachments removed. No second PDF is generated and
stored.

If the Contact is not Associato, do not invent an admission form.
Volunteer convert is unchanged.

Rebuild still fills **empty board fields** from a Converted Associato
lead. Rebuild MUST NOT copy `admissionForm`. Rebuild deletes leftover
admission files on Lead and Contact.

Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-rebuild.md
