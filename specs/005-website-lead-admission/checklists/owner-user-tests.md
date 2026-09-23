# Owner user tests: website admission Lead and socio PDF

**Feature**: `005-website-lead-admission`  
**Environment**: DDEV `https://nonprofit-espocrm.ddev.site`  
**Language**: Italian UI labels  
**Website repo / prod / live mail**: Skip until the owner names them

Cite:
https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md

Mark each step Pass / Fail / Skip. Attach the PDF on Fail.

## Setup

- [ ] Logged in as Espo **admin**
- [ ] Hard refresh (Ctrl+Shift+R)
- [ ] Role **Member** exists

## Flows

1. **Socio card**  
   Create Lead, type **Associato** only. Fill Codice Fiscale, Data di nascita, Luogo di nascita, Prov. di nascita, address. Save. Those fields stay on detail and edit next to **Tipo**. No Contact is created.  
   Result: Pass / Fail / Skip

2. **Loose fiscal code**  
   Code `AAAAAA00A00A000A` saves on the Lead. **Converti** to Contact fails. Lead stays.  
   Result: Pass / Fail / Skip

3. **Volunteer shape**  
   Type **Volontario** only, name, email, phone, description. No address, no fiscal code. Save succeeds. Panel **Consiglio Direttivo** is hidden. No admission PDF.  
   Result: Pass / Fail / Skip

4. **PDF**  
   Associato Lead, description contains the line `Newsletter: acconsente`. Open **Domanda di ammissione**. Letterhead, anagrafica, one ACCONSENTO box, blank signature lines, president line **Matteo Grossi**. Save again: still one PDF.  
   Result: Pass / Fail / Skip

5. **Board exclusivity**  
   Set seduta, **Approvata**, libro soci, **Sì**, ricevuta. PDF shows them. Switch to **Respinta**: only Respinta. Switch quota to **No**: only No.  
   Result: Pass / Fail / Skip

6. **Non-member cannot edit the board**  
   User who is not admin and does not have role Member cannot save a board change.  
   Result: Pass / Fail / Skip

7. **Convert moves the PDF**  
   Convert the Associato Lead to Contact. Contact (Associato) has the PDF. Lead no longer does. Contact has the same type, address, and birth fields.  
   Result: Pass / Fail / Skip

## Skip

- Production apply
- Editing the website
- Sending the PDF by email (a person downloads it and may send it for signature)
