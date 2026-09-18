# 020 — Specify 004.1: Repair Contact-first CRM user create

**Date:** 2026-09-17  
**Agent:** Cursor Auto (`/speckit-specify`)

## State

- Owner UAT of `004-contact-first-crm-user` **Failed** on create-CRM-user
  (Employee Contact `Volontario User123` saved, `linked_user_id` empty).
- Owner asked for a specify that **includes the repair**, then close
  **004 and 004.1 together** after they confirm the new behaviour.
- Active FEATURE_DIR: `specs/004.1-repair-create-crm-user`.
- Parent 004 competence copy / planner / delete-Inactive stay; this
  amendment does not redo the prod dump.

## Files

- `specs/004.1-repair-create-crm-user/spec.md`
- `specs/004.1-repair-create-crm-user/checklists/requirements.md`
- `.specify/feature.json`
- `specs/004-contact-first-crm-user/spec.md` (status: UAT failed → 004.1)
- `specs/004-contact-first-crm-user/checklists/owner-user-tests.md` (U1 Fail note)
- `.specify/progress/README.md` (index 020)

## Verification

- Spec quality checklist 16/16; no `[NEEDS CLARIFICATION]`.
- Defaults recorded: two-way email/phone sync; copied identity fields
  read-only only in the create side panel; access email = set-password
  link, never plaintext password.
- Espo docs opened this turn: users-management, passwords, fields,
  dynamic-logic, modal, hooks, app-templates.

## Blockers

None for specify. Plan/tasks/implement wait for `/speckit-plan`.

## Next steps

1. `/speckit-plan` then `/speckit-tasks` then `/speckit-implement` when
   the owner says so (local only).
2. Owner UAT on 004.1; if Pass, close 004 and 004.1 with notes.
3. Then 003 Google/WF U1–U6.
4. Production competence copy still Skip until named.
