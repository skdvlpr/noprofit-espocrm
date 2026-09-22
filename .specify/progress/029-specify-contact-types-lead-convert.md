# 029 — Specify 004.3 contact types + Lead convert user

**Date:** 2026-09-18  
**Agent:** Cursor Auto (`/speckit-specify`)

## State

Owner confirmed production transfer of 004 family looks OK and authorized
the next spec. **Specify-ahead rationale:** 004.2 UAT V1–V12 Pass and
prod copy of existing rows are done; this amendment reshapes Contact
types and Lead convert before any 004.3 plan/tasks. Active
`FEATURE_DIR` is now `specs/004.3-contact-types-lead-convert`.

## Files

- `specs/004.3-contact-types-lead-convert/spec.md`
- `specs/004.3-contact-types-lead-convert/checklists/requirements.md`
- `.specify/feature.json` → that directory

## Verification

- Requirements checklist all `[x]` after removing a technical planning
  sentence from Assumptions.
- No `[NEEDS CLARIFICATION]`.
- Espo docs opened this turn (fields, dynamic logic, convert Lead, roles,
  modules, metadata, hooks, web-to-lead).

## Blockers

- Dual-close of 004 / 004.1 / 004.2 still owner-owned.
- Do not `/speckit-plan` until the owner asks.

## Next steps

1. Owner read `spec.md`; `/speckit-clarify` if needed, else `/speckit-plan`.
2. Do not implement until tasks exist. DDEV only until owner names prod.
