# 017 — Plan 004: Contact-first volunteer/employee CRM user

**Date:** 2026-09-15  
**Agent:** Cursor Auto (`/speckit-plan`)

## State

- Active FEATURE_DIR: `specs/004-contact-first-crm-user`.
- Owner Q1 = **A**: identity = Contact `linkedUser`; Assigned User =
  ACL own only.
- User MAY keep notStorable mirrors (hours/dates today; competences after
  copy + `notStorable` on User). Planner MUST read Contact via
  `linkedUserId`.
- 003 implemented locally (`9f8864c`), **not** UAT-closed. Owner deferred
  003 U1–U7 until **after 004 closes**. Reminder required at 004
  implement-done / close.
- Local wipe / prod sample / prod competence copy: **not** run (owner-gated).
- `/speckit-tasks` **not** started.

## Files

- `specs/004-contact-first-crm-user/spec.md` (Q1 A, mirrors, 003 deferral)
- `specs/004-contact-first-crm-user/plan.md`
- `specs/004-contact-first-crm-user/research.md`
- `specs/004-contact-first-crm-user/data-model.md`
- `specs/004-contact-first-crm-user/quickstart.md`
- `specs/004-contact-first-crm-user/contracts/identity-link.md`
- `specs/004-contact-first-crm-user/contracts/create-user-from-contact.md`
- `specs/004-contact-first-crm-user/contracts/competences-and-planner.md`
- `specs/004-contact-first-crm-user/contracts/competence-migration.md`
- `specs/004-contact-first-crm-user/checklists/requirements.md` (16/16)
- `specs/003-google-standalone/checklists/owner-user-tests.md` (Deferred banner)
- `.specify/progress/015-google-standalone-implement.md` (UAT deferred note)
- `.specify/progress/016-specify-contact-first-crm-user.md` (Q1 answered)

## Verification

- `setup-plan.sh --json`: FEATURE_SPEC / IMPL_PLAN / SPECS_DIR /
  BRANCH `004-contact-first-crm-user`.
- Espo docs opened this turn (users-management, roles-management, acl,
  hooks, entity-defs, client-defs, record-defs, logic-defs, dynamic-logic,
  formula, fields, custom-views, view-setup-handlers, modal, modules,
  coding-practices, orm, tests, commands, app-layouts, app-console-commands,
  customize-standard-fields, translation).
- No NEEDS CLARIFICATION left in 004 spec.
- Constitution III exception recorded in `plan.md` (003 UAT deferred by
  owner, not skipped).

## Blockers

- `/speckit-tasks` / implement wait for owner.
- 003 closing UAT still required later.
- Wipe DDEV Users/Contacts and prod sample: wait for explicit yes.

## Next steps

1. Owner: `/speckit-tasks` when ready (do not implement until tasks exist).
2. After 004 implement + 004 owner UAT: **run 003**
   `checklists/owner-user-tests.md` U1–U6.
3. Ask before commit; never push unless asked.
4. Do not start F-003 App Secrets until 003 UAT is reported.
