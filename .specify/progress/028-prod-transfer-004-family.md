# 028 — Prod transfer: 003 leftover + 004 family

**Date:** 2026-09-18  
**Agent:** Cursor Auto

## State

Owner authorized commit + push of the current tree so CI can deploy
`crm.safehouse.community` after tests, then the same DDEV competence/schema
migrations on production **without creating new records**. Extra leftover
tables (WorkflowEngine) must be dropped manually; `rebuild --hard` does not
drop unused tables
([commands.md](https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md)).

004.3 (Contact multi-role / Associato volunteer-like flow) is **not** started
until this transfer finishes.

## Files

- Specs `004`, `004.1`, `004.2` and progress `016`–`027` (committed with code).
- Product code: `custom/` + `client/custom/` only (no `application/` / core).

## Data-model compare (before deploy)

Table lists were **identical** (166 names each). Differences were columns
and leftover unused tables, not missing product tables.

| Item | DDEV (after 004) | Prod (before 004) |
|------|------------------|-------------------|
| `contact.activity_competences` | present; 15 nonempty | **missing** |
| `user.activity_competences` leftover | present; 11 nonempty | present; **11 nonempty** (9 live `deleted=0`) |
| `user.is_occasional` leftover | present | present |
| Other User personnel fields | notStorable / dropped | same |
| Contact personnel fields | present | present |
| Contacts / Users / linked | 120 / 38 / 26 | 44 / 29 / 20 |
| `workflow_definition` | 1 row | 1 row (`Test WF`, `deleted=1`) |
| `workflow_condition_state` | 1 row | 1 row |
| `g_cal_smoke_*` | present | present, **0 rows**, no metadata in tree |

Copy on prod uses leftover User column; MUST NOT create Contacts/Users.

## Applied 2026-09-18

- Commit `f2c81de` pushed to `main` (also shipped unpushed 003 Google/WF-removal commits).
- CI `35375408359`: test 8m39s + deploy 43s **success** (soft rebuild in post-deploy).
- Backup: `/home/deploy/backups/espocrm-pre-004-20260918T173638Z.sql.gz` (32M).
- After deploy: `contact.activity_competences` existed; User leftover column kept; counts still 44 / 29 / 20.
- Dry-run then apply `php command.php copyUserActivityCompetences --apply`: **copied=9**, skippedNoContact=2, skippedNotPersonnel=3, skippedEmpty=5. Nine Volunteer lists **MATCH** User leftovers. No new Contact/User rows.
- Rsync **without delete** had left `custom/Espo/Modules/WorkflowEngine` + `client/custom/modules/workflow-engine` on the server. Removed those dirs; inactivated `WorkflowEngineRunScheduledWorkflows`. Google/push jobs left **Active**.
- Dropped unused tables: `workflow_definition`, `workflow_condition_state`, `g_cal_smoke_all_day`, `g_cal_smoke_date_time`, `g_cal_smoke_twin_date`. Soft rebuild after module removal. **No `--hard`.** Leftover `user.activity_competences` column still present (optional later hard rebuild).

## Verification

- Local: PHPStan OK; unit PHPUnit 164 tests / 349 assertions.
- CI: PHPStan + unit + integration + rsync deploy green.
- Prod: contact=44, user=29, 9 nonempty Contact competence lists, WF tables gone.

## Blockers

- None for this transfer. 004.3 (Contact multi-role / Associato volunteer-like flow) not started.
- Optional later: owner-named `rebuild --hard` to drop leftover User columns; enable `DEPLOY_RSYNC_DELETE` if leftover files must prune automatically (ask first).

## Next steps

1. Owner: confirm volunteers on `crm.safehouse.community` still have competences on Contact.
2. `/speckit-specify` 004.3 when owner is ready (multi Contact types, Associato = volunteer flow, merge duplicate fields, mirror to User).
3. Do not dual-close 004–004.2 beyond existing DDEV UAT unless owner wants a prod Pass.

## Blockers

- Copy on prod uses leftover `user.activity_competences` when Contact lists
  are empty. MUST NOT `rebuild --hard` before copy.
- Do not create Users/Contacts/Roles on prod; migrate existing rows only.
- Do not inactivate prod Google/email jobs the way DDEV was gated.

## Next steps

1. Compare DDEV vs prod `information_schema` (tables + contact/user columns).
2. Backup prod MariaDB.
3. Wait CI deploy + soft rebuild.
4. Dry-run then `--apply` copy; confirm Contact lists.
5. `DROP` leftover WorkflowEngine tables after confirm empty/unused.
6. Optional later `--hard` to drop leftover User columns (owner-named).
