# Quickstart: Repair Contact-first CRM user create

**Feature**: `004.1-repair-create-crm-user`  
**Instance**: local DDEV `https://nonprofit-espocrm.ddev.site`  
**PHP**: `ddev exec …` only.

Production copy/rebuild/live volunteer mail: **Skip** until named.
003 Google UAT: after **004 and 004.1** close.

Contracts: [create-user-side-panel.md](./contracts/create-user-side-panel.md),
[access-info-email.md](./contracts/access-info-email.md),
[email-phone-sync.md](./contracts/email-phone-sync.md).

Rebuild after metadata:

```text
ddev exec php command.php rebuild
```

https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md

## V1 — Volunteer create + side panel

1. Contacts → Create. Type **Volontario**. Confirm **Crea utente CRM** is
   a checkbox (not a dash) and **on**.
2. Fill name + email. Side panel opens; name/email locked; set username
   + roles. Leave password empty; **Send access info** on.
3. Save Contact.
4. **Expected**: Contact **Utente CRM** points at the User; Assigned User
   is still staff.

## V2 — Employee same path

Repeat V1 with type **Dipendente** / Employee.

## V3 — Checkbox off

Volunteer, uncheck Crea utente CRM (panel closes). Save. **Expected**:
Contact only.

## V4 — Existing Contact without User

Open `#Contact/view/6aac25d95f3708d0d` (or any Volunteer/Employee with
empty CRM user) → Edit → check Crea utente CRM → complete panel → Save.
**Expected**: User linked.

## V5 — Access email

If SMTP is configured: mailbox has branded Italian (or locale) access
mail with set-password link and **no password**. If SMTP is not
configured: User still exists; record **Skip** for mail.

## V6 — Channel sync

On a linked pair: change User extra email; Contact matches after
refresh. Change Contact phone; User matches.

## V7 — Help-seeker

Create Persona in difficoltà. **Expected**: no checkbox.

## Automated (supporting)

```text
ddev exec vendor/bin/phpunit tests/unit/Espo/Modules/NonprofitEspocrm
```

PHPUnit does not close owner UAT (constitution XVII).
