# Contract: convert board answers and the PDF

Native convert copies onto Contact when the name and type match. After this correction that includes the six board fields and `admissionForm`.

When the lead and the contact both include Associato:

- The contact shows Consiglio Direttivo with the lead’s answers.
- The contact PDF preview opens the same file as the lead.
- The lead PDF preview still opens that file.
- No second PDF is stored.

When the lead is not Associato, the contact has no board panel and no admission PDF.

Italian interface:

| Card | Section | Title |
|------|---------|--------|
| Contact | volunteer / employee | Volontario / Dipendente |
| Contact | member | Associato |
| User | volunteering | Volontariato |
| User | member | Associato |

English interface keeps Volunteer / Employee, Member, and Volunteering.

Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/development/api/i18n.md
