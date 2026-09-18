# Owner user tests: Contact-first volunteer/employee CRM user

**Feature**: `004-contact-first-crm-user`  
**Instance**: local DDEV `https://nonprofit-espocrm.ddev.site` (database is a **production dump** from 2026-09-16).  
**UI language**: Italian primary. Chat answers: Pass / Fail / Skip + note or screenshot.

Production copy/rebuild/deploy is **Skip** until the owner names that exact action.

PHPUnit supports this list; it does not close it.

Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md

## Deferred elsewhere

003 Google/WF closing tests (`specs/003-google-standalone/checklists/owner-user-tests.md` U1–U6) remain **required after 004 and 004.1 are closed**.

U1 (create User from Contact) **Failed** 2026-09-17 (Employee saved, no User, no CRM-user link). Repair and replacement UAT: `specs/004.1-repair-create-crm-user/`. Close **004 and 004.1 together** after 004.1 owner Pass.

## Checklist

- [ ] U1 Contacts → Create. Type **Volontario**. Confirm checkbox **Crea utente CRM** / Create CRM user is **on**. Fill name + email. Save. Expected: User create dialog opens with name/email copied; after User save, Contact **Utente CRM** points at that User; Assigned User on the Contact is still the staff owner (not the new volunteer).
- [ ] U2 Create a Volunteer, **uncheck** Create CRM user, save. Expected: Contact only; no User dialog.
- [ ] U3 Create Help-seeker / Persona in difficoltà. Expected: no Create CRM user checkbox.
- [ ] U4 Open a Volunteer Contact that has competences. Change the competence list, save. Open the linked User (if volunteering panel still shows). Expected: Contact stores the list; User mirror matches after refresh; shift availability follows Contact (empty = all categories).
- [ ] U5 Delete a volunteer User (not the last admin). Expected: Contact remains, status **Inattivo** / Inactive.
- [ ] U6 Production: `copyUserActivityCompetences --apply` / notStorable rebuild. **Skip** until the owner names that action.

## What to send back

For each U#: **Pass**, **Fail** (what you saw), or **Skip** (why).
