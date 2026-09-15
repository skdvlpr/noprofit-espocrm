# 013 — Close 001 (SC-006) and specify F-001

**Date:** 2026-09-15  
**Agent:** Cursor Auto

## State

- Owner accepted `.specify/progress/010-compliance-audit-report.md` (SC-006).
  Next remediations follow backlog order. First: F-001.
- F-016 (PrimaNota Role `fieldData`) **deferred by owner** — remember; not a
  001 blocker. Personal store: `f016-prima-nota-role-acl.md`.
- Feature `001-custom-code-audit` is **closed** as an audit (no code remediations
  in 001, per FR-009).
- Active Spec Kit feature is now `specs/003-decouple-google-client`
  (`/speckit-specify` for F-001). F-002 stays queued.

## Files

- `.specify/progress/010-compliance-audit-report.md` (SC-006 checked; F-016 note)
- `.specify/memory/constitution.md` (SC-006 TODO cleared)
- `.specify/feature.json` → `specs/003-decouple-google-client`
- `specs/003-decouple-google-client/spec.md`
- `specs/003-decouple-google-client/checklists/requirements.md`
- This handoff

## Verification

- Spec quality checklist 16/16 on first pass.
- Espo docs opened this turn: `modules.md`, `extension-packages.md`, `view.md`.

## Blockers

None for specify. Plan/implement still need DDEV; no prod apply in this feature
until the owner asks.

## Next steps

1. `/speckit-plan` then `/speckit-tasks` then `/speckit-implement` for 003.
2. After 003 owner UAT: specify F-002 (WorkflowEngine modal).
3. Later: F-016 Role field matrix when the owner un-pauses ACL/PII.
