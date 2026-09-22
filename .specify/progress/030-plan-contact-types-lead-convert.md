# 030 — Plan 004.3 contact types + Lead convert

**Date:** 2026-09-18  
**Agent:** Cursor Auto (`/speckit-plan`)

## State

`/speckit-plan` complete for `specs/004.3-contact-types-lead-convert`.
Owner correction: Contact email uniqueness is hard, same family as User.
Native-first: multiEnum, Dynamic Logic `arrayAnyOf`, native Convert field
map, FieldValidators. Custom convert view only for two-phase persist.
No `application/` edits. No tasks.md yet.

## Files

- `specs/004.3-contact-types-lead-convert/plan.md`
- `research.md`, `data-model.md`, `quickstart.md`
- `contracts/contact-types.md`, `unique-contact-email.md`,
  `lead-types-and-convert.md`, `convert-create-user.md`, `role-sync.md`
- Spec FR-014 / SC-008 added

## Verification

- Constitution gates PASS (plan table).
- Espo docs opened this turn (fields, convert, duplicate-check, validators,
  formula exception/API before-save, arrayAnyOf, roles, modules).

## Blockers

- Existing duplicate Contact emails on DDEV must be listed before UAT of
  the hard rule. Prod inventory not started.
- `/speckit-tasks` not run.

## Next steps

1. Owner: `/speckit-tasks` when the plan is accepted.
2. Do not implement until `tasks.md` exists.
3. Do not prod-apply this amendment until named.
