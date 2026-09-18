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

Table lists are **identical** (166 names each). Differences are columns
and leftover unused tables, not missing product tables.

| Item | DDEV (after 004) | Prod (before 004) |
|------|------------------|-------------------|
| `contact.activity_competences` | present; 15 nonempty | **missing** |
| `user.activity_competences` leftover | present; 11 nonempty | present; **11 nonempty** |
| `user.is_occasional` leftover | present | present |
| Other User personnel fields | notStorable / dropped | same |
| Contact personnel fields | present | present |
| Contacts / Users / linked | 120 / 38 / 26 | 44 / 29 / 20 |
| `workflow_definition` | 1 row | 1 row (`Test WF`, `deleted=1`) |
| `workflow_condition_state` | 1 row | 1 row |
| `g_cal_smoke_*` | present | present, **0 rows**, no metadata in tree |

Copy on prod must use leftover User column; MUST NOT create Contacts/Users.

## Verification

- Local unit PHPUnit + PHPStan before push (see this file after CI).
- CI: `.github/workflows/ci.yml` test then rsync on `main`.
- Post-deploy already runs **soft** `php command.php rebuild` (must not
  `--hard` before `copyUserActivityCompetences --apply`).

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
