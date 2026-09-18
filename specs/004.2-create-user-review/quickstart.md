# Quickstart: 004.2 create-user review

**Instance**: local DDEV `https://nonprofit-espocrm.ddev.site`  
**UI**: Italian primary.

Production copy/rebuild/live volunteer mail: **Skip** until named.

PHPUnit does not close owner UAT.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md

## Rebuild

```bash
ddev exec php command.php rebuild
```

## Happy path (admin)

1. Log in as admin. `#Contact/create`.
2. Type **Volontario**. Confirm **Crea utente CRM** is on and **no** panel
   opened. Confirm **Utente CRM** is hidden.
3. Fill name + email. **Salva**.
4. Side panel: identity filled **including prefix (Mr./Dr.)** and Occasionale
   preview, role Volunteer, no password fields, invite-link checkbox on.
   **Salva** on the panel.
5. Contact **Utente CRM** is the new User. Mailbox has set-password link,
   no password, **logo visible in Gmail** (CID). Shift/disponibilità mail
   already CID-ok; access-info must survive Htmlizer (not a file path).

Repeat with **Dipendente**.

Volontari list = regular volunteers only; use **Volontari occasionali** for
the occasional set. After rebuild, Dummy cron stays Active (do not gate all
jobs Inactive).

## Negative

- Uncheck Crea utente CRM → Contact only.
- Non-admin: Volunteer/Employee types absent.
- Existing Contact **Modifica**: no Crea utente CRM.
- Second User with an existing User email: refused.
- Change competences on Contact: User screen matches, not editable there.
