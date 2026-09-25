# Contract: Live admission PDF (no stored file)

Staff with read access to an Associato Lead or Associato Contact open
Anteprima PDF or the open-PDF action.

- The CRM builds the current *Domanda di ammissione a socio* from that
  record’s fields and streams `application/pdf`.
- The request MUST NOT create or keep an Attachment on the record.
- Non-Associato: no preview (existing Dynamic Logic). Forbidden if no
  read access. Not found if the record is missing.
- Empty printed fields stay blank. Newsletter ticks use
  `newsletterConsent` or the description line, same as today.
- Download filename stays `domanda ammissione {first} {last} {ddmmyyyy}.pdf`.

Lead GET already renders live. Contact GET MUST do the same with a
Contact Template (Pdf Service requires matching entity type).

Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/pdf-defs.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/development/api.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
