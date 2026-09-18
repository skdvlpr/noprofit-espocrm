# 016 — Specify 004: Contact-first volunteer/employee user

**Date:** 2026-09-15  
**Agent:** Cursor Auto (`/speckit-specify`)

## State

- `9f8864c` committed locally (003 Google standalone + WF removal). **Not pushed.**
- Local DDEV dropped leftover `workflow_definition` (110 rows) and
  `workflow_condition_state` (18 rows). No `%workflow%` tables remain.
- 003 owner UAT still open. Owner authorized **specify-ahead** for 004
  (emergency). Plan/tasks/implement for 004 **not** started.
- Active FEATURE_DIR: `specs/004-contact-first-crm-user`.

## Files

- `specs/004-contact-first-crm-user/spec.md`
- `specs/004-contact-first-crm-user/checklists/requirements.md`
- `.specify/feature.json`

## Verification

- Research: User afterSave creates/updates Contact; competences User-only;
  planner reads User; User afterRemove sets Contact Inactive.
- Espo docs opened: users-management, roles-management (own = assigned),
  hooks, customize-standard-fields, entity-defs, client-defs, logic-defs.
- Checklist: 15/16; blocked on Q1 identity link.

## Blockers

- Owner must answer Q1 (CRM-user link vs Assigned User) before
  `/speckit-plan`.
- 003 UAT still open.
- Local wipe of Users/Contacts and prod sample copy: not done (owner-gated).

## Next steps

1. Owner answers Q1 (and confirms the research write-up).
2. Then `/speckit-plan` only when the owner says so.
3. 003 UAT still requested in parallel.

## Update 2026-09-15

Owner answered Q1 = **A**. Authorized `/speckit-plan` for 004. 003 UAT
deferred until 004 closes. Continue in `017-plan-contact-first-crm-user.md`.
