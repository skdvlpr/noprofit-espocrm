# Owner user tests: Repair Contact-first CRM user create

**Feature**: `004.1-repair-create-crm-user`  
**Instance**: local DDEV `https://nonprofit-espocrm.ddev.site`  
**UI language**: Italian primary. Chat answers: Pass / Fail / Skip + note or screenshot.

Production copy/rebuild/live volunteer mail is **Skip** until the owner names that exact action.

PHPUnit supports V6; it does not close owner UAT (constitution XVII).

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md

Parent 004 UAT U1 Failed 2026-09-17. Close **004 and 004.1 together** after this list Pass. Then 003 Google/WF U1–U6.

Quickstart: [../quickstart.md](../quickstart.md)

## Checklist

- [ ] V1 Contacts → Create. Type **Volontario**. Confirm **Crea utente CRM** is a real checkbox (not a dash) and **on**. Fill name + email. Side panel opens; name/email/phone read-only there; set username + roles; leave password empty; Send access info on. Save Contact. Expected: Contact **Utente CRM** points at the User; Assigned User is still staff; no second User create dialog.
- [ ] V2 Repeat V1 with type **Dipendente** / Employee. Expected: same — Contact + User + link.
- [ ] V3 Volunteer, **uncheck** Crea utente CRM (panel closes). Save. Expected: Contact only; no User.
- [ ] V4 Open `#Contact/view/6aac25d95f3708d0d` (or any Volunteer/Employee with empty CRM user) → Edit. Checkbox is **off**. Check it, complete panel, Save. Expected: User linked. Detail shows Utente CRM, not a create-user dash.
- [ ] V5 Access email. If SMTP is configured: mailbox has branded Italian (or locale) access mail with set-password link and **no password**. If SMTP is not configured: User still exists; mark **Skip** for mail with that reason.
- [ ] V6 On a linked pair: change User extra email; Contact matches after refresh. Change Contact phone; User matches.
- [ ] V7 Create Persona in difficoltà / Help-seeker. Expected: no Crea utente CRM checkbox.

## What to send back

For each V#: **Pass**, **Fail** (what you saw), or **Skip** (why).
