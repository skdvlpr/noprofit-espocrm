# 000 — SDD bootstrap (constitution ratification)

**Date:** 2026-08-31  
**Agent:** Cursor Auto (`/speckit-constitution` + `SDD-FIRST-CONSTITUTION-PROMPT.md`)

## State

- Spec Kit constitution ratified at `.specify/memory/constitution.md` **v1.0.0**.
- Slim `AGENTS.md` rewritten (preferences + SDD pointer only; old rulebook removed).
- Notion executor logging **retired**; handoffs go to `.specify/progress/`.
- Critical history extracted to `.specify/progress_old/` (not a full Notion dump).
- No product feature code, CI workflow edits, deploy, or push in this session.

## Docs pull

- Local clone: `~/safehouse/espocrm-documentation`
- Remote: `https://github.com/espocrm/documentation/`
- `git pull --ff-only`: already up to date
- HEAD: `ab2a5ee338be141ffe0d1b2c29ba742432bba089` (2026-08-31, Update index.md)

## Files changed

- `.specify/memory/constitution.md` (new content v1.0.0)
- `AGENTS.md` (full replace → slim)
- `.specify/progress/README.md`
- `.specify/progress/000-sdd-bootstrap.md` (this file)
- `.specify/progress_old/01-project-context.md` … `05-open-risks-and-debt.md`

## CI / deploy risks observed (no workflow edits)

- `.github/workflows/ci.yml`: test (PHPUnit + PHPStan + `db_test`) then deploy on `main` via SSH + rsync; secrets via GitHub `environment: production`; post-deploy rebuild only if `data/config.php` exists.
- `deploy/rsync-excludes.txt` excludes smokes/seeds/builders — good; still ships much of `bin/` and notes refuse-production policy on server.
- `.github/workflows/prod-provision-oneshot.yml`: manual `workflow_dispatch` runs Installer + quarantine on prod over SSH — high privilege; must stay operator-triggered and reviewed under a future harden-CI spec.
- Gaps for a future spec: consistent secret hygiene review, whether builders should be gitignored, whether auto-deploy on every `main` push is the desired long-term gate.

## Custom modules (names only)

| Module | Notes |
|--------|--------|
| NonprofitEspocrm | Vertical CRM suite (entities, shift planning, reporting, themes hooks, etc.) |
| GoogleIntegration | Google OAuth / Calendar (manifest display name GoogleCalendarDrive) |
| WorkflowEngine | Admin workflows extension (Espo ≥10) |
| BugTracker | Bug report entity/UI |
| SafehouseAuroraThemes | Aurora Light/Dark branding |

## Verification

- Constitution: no unresolved `[PLACEHOLDER]` tokens; version 1.0.0; dates ISO.
- Espo doc citations use local paths + docs.espocrm.com URLs.
- Scope Guard: no application/feature churn.

## Blockers

None for governance bootstrap. Notion SQL task query skipped after column mismatch; project page fetch was sufficient for archive.

## Next steps (ordered — user runs Spec Kit)

1. `/speckit-specify` — Custom code compliance audit (`custom/Espo/Modules/**` vs official docs).
2. `/speckit-specify` — Tests & `bin/` hygiene (PHPUnit vs smokes; builders policy).
3. `/speckit-specify` (if approved) — Harden CI/CD.
4. No prod deploy/migration/extension upload until an approved spec says so.
