# Owner user tests: Create-User review modal (004.2)

**Feature**: `004.2-create-user-review`  
**Instance**: local DDEV `https://nonprofit-espocrm.ddev.site`  
**UI language**: Italian primary. Chat answers: Pass / Fail / Skip + note or screenshot.

Production copy/rebuild/live volunteer mail is **Skip** until the owner names that exact action.

PHPUnit does not close owner UAT (constitution XVII). Close **004 + 004.1 + 004.2** together after this list Pass. Then 003 Google/WF U1–U6.

Cite:
[https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md](https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md)
[https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md](https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md)
[https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md](https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md)
[https://github.com/espocrm/documentation/blob/master/docs/development/modal.md](https://github.com/espocrm/documentation/blob/master/docs/development/modal.md)

Quickstart: [../quickstart.md](../quickstart.md)

## Checklist

- [x] V1 Admin, `#Contact/create`, type **Volontario**. **Crea utente CRM** is a real checkbox and **on**. Checking it does **not** open a panel. **Utente CRM** picker is hidden. Fill name **with prefix (Mr./Dr.)** + unique email + Occasionale if testing that flag. Create form has **one** personName row (no duplicate Nominativo/Cognome). **Salva**. Expected: side panel (`dialog-record`) opens with identity filled **including salutation prefix**, Occasionale/volunteer preview matching the Contact draft, role Volunteer by **name**, **no** Password / Genera / confirm, invite checkbox labelled for the **set-password link** and on. Cancel the panel. Expected: **no** Contact, **no** User.
- [x] V2 Repeat V1 and **Salva** on the panel. Expected: Contact **Utente CRM** is the new User; Assigned User is still staff; no plaintext password; mailbox (if SMTP) has set-password **link**, **no** password, and **visible Safe House logo** (CID, not a broken remote URL). If SMTP is down: User still exists; mark **Skip** for mail.
- [x] V3 Repeat V2 with type **Dipendente**. Expected: role Employee; same Contact+User+link; no password fields.
- [x] V4 Volunteer, **uncheck** Crea utente CRM (Utente CRM picker visible again). **Salva**. Expected: Contact only; no User; no panel.
- [x] V5 Open an existing Volunteer/Employee → **Modifica**. Expected: **no** Crea utente CRM checkbox (create-only). Do not create a User from this screen.
- [x] V6 Second User (or this panel) with email `kokshse196@proton.me` (kept `semen.koksharov`). Expected: **hard** refuse, not a skippable duplicate dialog. Fresh unique email succeeds.
- [x] V7 Non-admin Contact create. Expected: Volunteer/Employee types **absent**. Other types can still be created. Server refuses Volunteer/Employee if forced.
- [x] V8 Linked pair: edit **competences / occasional / hours** on Contact; open User. Expected: same values, **read-only** on User (not a second stored copy). Change on User form must not overwrite Contact.
- [x] V9 Linked pair: extra User email / Contact phone still two-way (004.1 channel sync).
- [x] V10 Help-seeker / Persona in difficoltà create. Expected: no Crea utente CRM checkbox.
- [x] V11 Contact list primary filters: **Volontari** = regular (`isOccasional` false or null); **Volontari occasionali** = occasional true; Dipendenti / Associati by type. Counts must match SQL, not an empty 2-of-many drop from NULL `isOccasional`.
- [x] V12 Admin notification: cron/lavori pianificati banner **gone** after Dummy job heartbeat. Cache-disabled on DDEV is expected, not a Fail.

## Residual after V1–V12

Access-info / password-change-link logo in **Gmail** (broken img, alt Safe House) while disponibilità/shift mail already shows CID. Cause: Htmlizer `postProcessHtml` on system templates. Retest that mail in Gmail after T033. Do **not** resend to live volunteers unless named.

## Out of this screen

Checkbox-opens-panel (004.1), Modifica-on-existing create-user, plaintext password, **any core** `application/` **edit**.

## What to send back

For each V#: **Pass**, **Fail** (what you saw), or **Skip** (why).