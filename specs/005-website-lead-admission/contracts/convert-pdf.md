# Contract: Convert and the PDF

Staff convert. The CRM does not convert on Lead create.

Native convert copies onto Contact when the name and type match:

- `contactType`
- address attributes
- `taxCode`, `birthDate`, `birthPlace`, `birthProvince`
- `admissionForm` (file)

Contact `taxCode` still uses `ItalianFiscalCode`. An empty code is allowed. A non-matching code fails the Contact create, the Lead stays unconverted, and the PDF stays on the Lead.

After a successful convert, if both Lead and Contact include `MemberContact`:

- Contact has the admission file
- Lead `admissionForm` is empty and that Lead attachment is gone
- No second PDF is generated for the Converted Lead

If the Contact is not Associato, the Lead file stays.

Board columns are not separate Contact fields; they are already on the PDF.

Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
