# Quickstart: 005 website admission leads

Local only: `https://nonprofit-espocrm.ddev.site`. PHP via `ddev exec`.
Do not change the website repo. Do not send mail. Prod apply Skip until named.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md

## Prerequisites

1. DDEV up, logged in as Espo **admin**.
2. After metadata: `ddev exec php command.php rebuild` (soft).
3. Role named **Member** exists (004 family).
4. Website integration user already exists. Do not create another. Do not copy secrets into git.

## Automated

```bash
ddev exec vendor/bin/phpunit tests/unit/Espo/Modules/NonprofitEspocrm/AdmissionBoardTest.php tests/unit/Espo/Modules/NonprofitEspocrm/NewsletterConsentTest.php
ddev exec vendor/bin/phpstan analyse -c phpstan.neon
```

## Owner flows (Italian labels)

1. **Socio card** — Lead type Associato, fill Codice Fiscale, Data di nascita, Luogo di nascita, Prov. di nascita. They show on detail and edit next to Tipo. Save does not create a Contact.
2. **Loose code** — Fiscal code `AAAAAA00A00A000A` saves on the Lead. Convert to Contact fails; Lead stays.
3. **Valid convert** — A real-looking code that passes the Contact check converts; Contact has the same type, address, and birth fields.
4. **Volunteer shape** — Type Volontario, name, email, phone, description only. Saves. No board panel. No admission PDF.
5. **PDF** — Associato Lead whose description contains `Newsletter: acconsente`. PDF matches the module: anagrafica, one consent box, blank signatures, president line Matteo Grossi. Save again: still one PDF.
6. **Board** — As admin, set seduta, Approvata, libro soci, Sì, ricevuta. PDF updates. Set Respinta: only Respinta. Set No: only No. As a user who is not admin and not Member, the same edit is refused.
7. **Move PDF** — Convert that Associato Lead. Contact has the PDF. Lead does not.

Pass / Fail / Skip + screenshot of the PDF. Prod Skip.
