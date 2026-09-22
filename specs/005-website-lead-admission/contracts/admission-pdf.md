# Contract: Admission PDF

Produced only when the Lead’s `contactType` includes `MemberContact`. One current file in `admissionForm`. A later save replaces that file and does not delete other attachments.

Printed page, matching the paper module:

1. Title `DOMANDA DI AMMISSIONE A SOCIO`.
2. Fixed letterhead: SAFE HOUSE ETS, Codice Fiscale 96629270586, RUNTS Rep. n. 156768, sede legale Via Delleani 26, 00042 Anzio (RM), sede operativa Torino (Piemonte).
3. Section 1 from the Lead: name, `birthPlace` + `birthProvince`, `birthDate`, `taxCode`, `addressCity` + `addressState`, `addressStreet`, `addressPostalCode`, phone, email. No country row.
4. Section 2: the three fixed declarations (mission and values; statute and regulations; annual fee).
5. Section 3: GDPR notice. Mark `ACCONSENTO` when the loader sees `Newsletter: acconsente`. Mark `NON ACCONSENTO` when it sees `Newsletter: non acconsente`. Otherwise both empty. Applicant date and Firma del Richiedente are blank lines.
6. Reserved section: board date, outcome (Approvata / Respinta / empty), Libro Soci number, quota (Sì / No / empty), blank line `Firma del Presidente (Matteo Grossi)`, receipt number.

No email is sent. No signature image is stored.

Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/pdf-defs.md
