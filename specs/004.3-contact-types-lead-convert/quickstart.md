# Quickstart: 004.3 DDEV validation

Local only: `https://nonprofit-espocrm.ddev.site`. PHP via `ddev exec`.
Do not convert live Leads or send volunteer mail unless the owner names it.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md

## Prerequisites

1. DDEV up, logged in as Espo **admin**.
2. After metadata: `ddev exec php command.php rebuild` (soft).
3. List Contact email collisions; clean or Skip those rows before hard unique UAT.
4. Copy enum `contactType` → multiEnum (console dry-run then `--apply`).
5. Confirm Roles named Volunteer, Employee, Member exist.

## Automated

```bash
ddev exec vendor/bin/phpstan analyse -c phpstan.neon
ddev exec vendor/bin/phpunit tests/unit
```

Expect type-combination, filter, and Contact email uniqueness tests green.

## Owner flows (hash + Italian labels)

1. **Volunteer + Member** — Contact create: both types, both panels, shared
   tax/birth once. Save + Crea utente CRM → review shows both Roles →
   confirm. Filters Volontari and Associati both list the person.
2. **Illegal mix** — Volunteer + Employee (and Member + Help-seeker) refused.
3. **Member-only create user** — same review as 004.2, Role Member.
4. **Non-admin** — cannot set Volunteer/Employee.
5. **Add Member on existing Volunteer+User** — no create-user checkbox;
   User gains Role Member.
6. **Lead Volunteer** — type only on Lead; Convert shows volunteer fields on
   Contact; Crea utente CRM on → review → Contact Volunteer + User; Lead Converted.
7. **Cancel review on convert** — Lead not Converted; no new Contact/User;
   Convert again reopens the review.
8. **Lead Generic** — convert checkbox off → Contact Other.
9. **Duplicate Contact email** — second Contact with the same email fails;
   linked Contact+User may share.
10. **Create Volunteer/Employee sticks** — `#Contact/create` Volontario-only
    and Dipendente-only keep the type; Associato+Volontario both stay.
11. **Lead Volontario+Associato** — both types copy to Contact on convert.
12. **Convert empty Tipo contratto** — Volunteer+Member convert + user
    review Confirm succeeds.
13. **User Is Active off** — linked Contact Stato becomes Inactive.

Pass / Fail / Skip + screenshot per step. Prod apply Skip until named.

## Next

`/speckit-tasks` then `/speckit-implement`.
