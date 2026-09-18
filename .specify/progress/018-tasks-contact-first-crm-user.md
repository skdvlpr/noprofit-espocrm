# 018 — Tasks 004: Contact-first volunteer/employee CRM user

**Date:** 2026-09-15  
**Agent:** Cursor Auto (`/speckit-tasks`)

## State

- `specs/004-contact-first-crm-user/tasks.md` written (T001–T029).
- Owner: **local DDEV only**. Wipe Users/Contacts, prod sample, production
  copy/rebuild = Skip (T025/T029) until named.
- `/speckit-implement` **not** started. Waiting on constitution XV table
  (keep / change / drop; Launch vs Replace for C7–C8 rows).
- 003 owner UAT still required after 004 closes.

## Files

- `specs/004-contact-first-crm-user/tasks.md`
- `.specify/progress/README.md` (index 018)

## Verification

- `setup-tasks.sh --json`: FEATURE_DIR
  `specs/004-contact-first-crm-user`; docs research, data-model,
  contracts/, quickstart.
- Checklist format: `- [ ] T0xx` + paths; story labels on US phases only.
- Tests proposed: T010, T017, T020 (plus T026 run). Owner may drop.

## Blockers

- Owner keep/change/drop + Launch/Replace for T011 and T013 before
  spawning those models.
- Implement waits for `/speckit-implement`.

## Next steps

1. Owner replies on the complexity table.
2. Then `/speckit-implement` when asked (local DDEV).
3. After 004 UAT: 003 `owner-user-tests.md` U1–U6.
