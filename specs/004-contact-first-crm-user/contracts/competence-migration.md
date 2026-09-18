# Contract: Competence copy then User column drop

**Feature**: `004-contact-first-crm-user`

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-console-commands.md
https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md

## Order (MUST)

1. Add Contact `activityCompetences` (storable) + layouts + i18n.
2. `ddev exec php command.php rebuild` — Contact column exists; User
   column still exists.
3. Run copy command (dry-run then apply) on **DDEV**.
4. Switch planner to Contact read path (can ship in the same implement
   as long as copy ran first on that instance).
5. Set User `activityCompetences` `notStorable: true`.
6. Rebuild — User DB column gone.
7. Production: same order, only after owner names copy/rebuild on
   `crm.safehouse.community`.

## Copy command

- Metadata: `app.consoleCommands` → class in
  `Espo\Modules\NonprofitEspocrm\Classes\ConsoleCommands\`.
- Implement `Espo\Core\Console\Command`.
- `listed: true`.
- Flags: dry-run (default or explicit) vs apply.
- Logic: for each User the ORM still has `activityCompetences` stored,
  find Contact(s) with `linkedUserId`, type Volunteer or Employee, set
  Contact field from User list. Skip portal/system as appropriate.
- MUST NOT hardcode SQL table names in runtime PHP.
- Idempotent: re-run overwrites Contact with User snapshot until step 5.

## Production apply (same order as this DDEV rehearsal)

Do **not** run copy/rebuild on production until the owner names that
exact action. When they do, this tree already has User `notStorable`, so
use the leftover-column path (do **not** `rebuild --hard` first):

1. Backup MariaDB on `crm.safehouse.community`.
2. Deploy this tree (Contact `activityCompetences` + copy command + User
   `notStorable`).
3. `php command.php rebuild` — **soft** only. Adds Contact column. Soft
   rebuild may leave `user.activity_competences` in MariaDB even after
   the field is `notStorable`
   (https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md).
4. Dry-run then apply:
   `php command.php copyUserActivityCompetences`
   then `--apply`.
   Copy reads leftover User column when ORM is empty **and** the Contact
   list is still empty. It MUST NOT overwrite a non-empty Contact
   (`skippedEmpty`).
5. Confirm Volunteer/Employee Contact lists match former User lists.
6. Optional later: `rebuild --hard` to drop the leftover User column —
   backup first. MUST NOT `--hard` before step 4.

Local DDEV 2026-09-16: prod dump imported, copy applied, User field
`notStorable`. After any rebuild, re-inactivate scheduled jobs and
truncate `job` so local cron cannot hit production Google/email/push.

## ShiftPlanningInstaller

Today it patches **User** detail to include `activityCompetences`. After
this feature it MUST also ensure **Contact** volunteer panel contains the
field. It MUST NOT re-add a storable User column after step 5.
