# 014 — Rewrite 003: Google standalone, retire WF

**Date:** 2026-09-15  
**Agent:** Cursor Auto (`/speckit-specify` + `/speckit-plan`)

## State

- Owner cancelled F-002 split. WorkflowEngine leaves **this** product.
- Active spec renamed: `specs/003-google-standalone` (supersedes
  `003-decouple-google-client`).
- Constitution **v1.4.0**: Principle II + Locked Decision `F-EXT-UNIVERSAL`
  (autonomous extensions, metadata entity catalogs).
- Plan Phase 0–1 written. Tasks/implement **not** started. WF still on disk
  until `/speckit-implement`.

## Files

- `.specify/memory/constitution.md`
- `specs/003-google-standalone/*` (spec, checklist, plan, research, data-model,
  contracts, quickstart)
- `.specify/feature.json` (gitignored) → `specs/003-google-standalone`
- Deleted: `specs/003-decouple-google-client/`

## Verification

- Spec checklist 16/16.
- Espo docs opened: modules, view, metadata, scopes, app-metadata,
  extension-packages, extensions admin.
- Google has **no** WorkflowEngine references today; Nonprofit AfterInstall
  still lists WF installer (implement removes it).

## Blockers

- Prod uninstall/deploy not approved.
- F-016 still deferred.

## Next steps

1. `/speckit-tasks` then `/speckit-implement` when the owner asks.
2. Ask before commit (specify+plan not committed yet).
3. After 003 UAT: next audit rank is F-003 App Secrets (WF F-002 cancelled).
