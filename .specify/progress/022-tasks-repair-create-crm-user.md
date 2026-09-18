# 022 — Tasks 004.1: Repair Contact-first CRM user create

**Date:** 2026-09-17  
**Agent:** Cursor Auto (`/speckit-tasks`)

## State

- `specs/004.1-repair-create-crm-user/tasks.md` written (T001–T027).
- Owner: **local DDEV only**. Prod copy/rebuild and live volunteer mail = Skip.
- `/speckit-implement` **not** started. Waiting on constitution XV table
  (keep / change / drop; Launch vs Replace for C7–C8 rows T011, T012).
- 004 and 004.1 close together after 004.1 owner UAT Pass.
- 003 owner UAT still required after that.

## Files

- `specs/004.1-repair-create-crm-user/tasks.md`
- `.specify/progress/README.md` (index 022)

## Verification

- `setup-tasks.sh --json`: FEATURE_DIR
  `specs/004.1-repair-create-crm-user`; docs research, data-model,
  contracts/, quickstart.
- Checklist format: `- [ ] T0xx` + paths; story labels on US phases only.
- Tests proposed: T020 (plus T015 existing, T024 run).

## Blockers

- Owner keep/change/drop + Launch/Replace for T011 and T012 before
  spawning those models.

## Next steps

1. Owner replies on the complexity table.
2. Then `/speckit-implement` when asked (local DDEV).
3. After 004.1 UAT Pass: close 004 and 004.1; then 003 U1–U6.
