# Quickstart: Contact-first volunteer/employee CRM user

**Feature**: `004-contact-first-crm-user`  
**Instance**: local DDEV only (`https://nonprofit-espocrm.ddev.site`).  
**PHP**: `ddev exec …` only.

Production copy/drop/wipe: **do not run** until the owner names that
action. 003 Google/WF owner tests: **deferred** — see
`specs/003-google-standalone/checklists/owner-user-tests.md` (run after
004 closes).

Contracts: [identity-link.md](./contracts/identity-link.md),
[create-user-from-contact.md](./contracts/create-user-from-contact.md),
[competences-and-planner.md](./contracts/competences-and-planner.md),
[competence-migration.md](./contracts/competence-migration.md).  
Model: [data-model.md](./data-model.md).

## Prerequisites

- DDEV up; NonprofitEspocrm module loaded.
- Staff user who can create Contact **and** User.
- After implement: rebuild
  `ddev exec php command.php rebuild`
  (https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md).

## V1 — Create Volunteer + CRM user

1. Contacts → Create. Type **Volunteer**. Confirm **Create CRM user** is
   checked.
2. Fill person + competences on the Contact. Save.
3. User create dialog opens with name, email, phone copied. Set username
   / roles / access info. Save.
4. **Expected**: one Contact, one User; Contact **CRM user** link points
   at that User; Assigned User on the Contact is **not** forcibly the new
   volunteer; competences stored on Contact.

## V2 — Volunteer without login

1. Create Volunteer, uncheck Create CRM user, save.
2. **Expected**: Contact only; no User; checkbox not offered for
   Help-seeker.

## V3 — Mirror + planner

1. Set Contact competences to meal distribution only.
2. Open the linked User (if volunteering panel still shown): mirror
   matches after refresh; User has no independent DB column after
   migration.
3. Open availability for that User.
4. **Expected**: other categories blocked; empty Contact list = all
   categories.

## V4 — Delete User

1. Delete the volunteer User.
2. **Expected**: Contact remains, `personnelStatus` Inactive.

## V5 — Copy command (DDEV)

1. Before User `notStorable`: seed a User with storable competences and a
   linked Volunteer Contact with empty competences (or use existing
   pairs).
2. `ddev exec php command.php copyUserActivityCompetences` (dry-run) then
   `--apply`. After `notStorable`, copy still reads a leftover User DB
   column when the Contact list is empty; it will not overwrite a
   non-empty Contact.
3. **Expected**: Contact list equals former User list; then User field
   notStorable + rebuild; planner still matches. Production: see
   [competence-migration.md](./contracts/competence-migration.md).

## Automated (supporting, not UAT close)

```text
ddev exec vendor/bin/phpunit tests/unit/Espo/Modules/NonprofitEspocrm
```

Exact test files are named in `/speckit-tasks`. PHPUnit does not close
owner UAT (constitution XVII).
