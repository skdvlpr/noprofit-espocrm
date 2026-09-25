# 054 — Specify + plan + tasks: on-demand admission PDF

**Date:** 2026-09-25  
**Feature:** `specs/005.3-on-demand-admission-pdf`  
**Agent:** Cursor Grok 4.6

## State

Active spec switched from `007.1-restore-channel-sync` to amendment
`005.3` of parent `005`. Owner: do not store the admission PDF; rebuild
on every card open or download; convert copies board/person fields only;
delete any file Espo still duplicates; leftover files stripped on
rebuild. Specify, plan, and tasks written this turn. **Not implemented.**

Path = Code (GET + leftover wipe). Formula `ext\pdf\generate` rejected
(stores an attachment). Local DDEV only.

## Files

- `.specify/feature.json` → `specs/005.3-on-demand-admission-pdf`
- `specs/005.3-on-demand-admission-pdf/spec.md`
- `checklists/requirements.md` (16/16)
- `plan.md`, `research.md`, `data-model.md`, `quickstart.md`
- `contracts/live-admission-pdf.md`, `contracts/convert-no-pdf-file.md`
- `tasks.md` T001–T015, feature complexity 5, max task C6
- this handoff

## Verification

- `setup-plan.sh --json`: FEATURE_SPEC / IMPL_PLAN / SPECS_DIR /
  BRANCH `005.3-on-demand-admission-pdf`
- `setup-tasks.sh --json`: FEATURE_DIR same; docs research, data-model,
  contracts/, quickstart
- No `[NEEDS CLARIFICATION]` in spec
- Espo docs opened this turn: printing-to-pdf, pdf-defs, formula/ext,
  fields, sales-management, hooks, api, acl, modules, tests, commands,
  app-rebuild, custom-views, metadata, entity-defs; in-tree
  `Pdf\Service::generate` and `ConvertService` file copy (read-only)

## Blockers

- `/speckit-implement` not started
- Owner UAT after implement
- Production apply not approved

## Next steps

1. Owner: `/speckit-implement` on inherit/Auto (or name Launch/Replace)
2. Do not commit/push unless asked
3. Do not prod-apply until named
