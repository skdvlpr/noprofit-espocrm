# Contract: Socio Lead payload (site already sends this)

The website is not changed. A successful call is `POST Lead` with:

| Field | Value |
|-------|--------|
| `firstName`, `lastName`, `emailAddress`, `phoneNumber` | From the form |
| `addressStreet`, `addressCity`, `addressPostalCode` | Address, city, CAP |
| `addressState` | Residence province |
| `addressCountry` | `Italy` |
| `taxCode` | 16 chars `A–Z` / `0–9`, already uppercase |
| `birthDate` | `Y-m-d` |
| `birthPlace`, `birthProvince` | Birth place and province |
| `contactType` | `["MemberContact"]` |
| `source` | `Web Site` |
| `status` | `New` |
| `description` | Five lines below |
| `assignedUserId` | Only when the site integration has a user |

Description lines, in order:

```text
Domanda di ammissione socio dal sito.
Statuto accettato: sì.
Mission accettata: sì.
Quota: impegno al versamento.
Newsletter: acconsente
```

or `Newsletter: non acconsente`.

The CRM MUST accept this body from the existing integration user. It MUST NOT create a Contact. It MUST NOT require address or fiscal code on every Lead (volunteer payload has neither).

Volunteer payload the site does **not** send yet, which MUST also save:

`firstName`, `lastName`, `emailAddress`, `phoneNumber`, `description` (the message), `contactType: ["Volunteer"]`, `source: "Web Site"`, `status: "New"`.

Cite: https://github.com/espocrm/documentation/blob/master/docs/development/api.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
