# 012 — Constitution v1.3.0 + GitHub Espo doc cites

**Date:** 2026-09-15  
**Agent:** Cursor Auto

## State

- Constitution **v1.3.0** is the law: Read
  `/home/skoksharov/espocrm-documentation`; cite
  `https://github.com/espocrm/documentation/blob/master` + the same relative
  path. MUST NOT cite `docs.espocrm.com` in git. MUST NOT use
  `~/safehouse/espocrm-documentation`.
- `AGENTS.md` docs table matches that map.
- Specs `001` / `002`, audit report `010`, bootstrap prompt, and our PHP/CI
  `@see` comments swept to GitHub blob cites.
- Production Prima Nota model (002) verified via SSH 2026-09-15: column
  `exclude_from_digital_reports` present; Metro rows DonorPocket + exclude;
  no extra SQL migration needed (Espo rebuild).
- Uncommitted vendor autoload noise is **not** part of this change.

## Files

- `.specify/memory/constitution.md`
- `AGENTS.md`
- `specs/001-custom-code-audit/*`, `specs/002-prima-nota-off-books/{spec,research,tasks}.md`
- `.specify/progress/010-compliance-audit-report.md`, `000-sdd-bootstrap.md`, this file
- `SDD-FIRST-CONSTITUTION-PROMPT.md`
- `bin/{test-build,run-tests}.sh`, `tests/integration/config-env.php`
- `.github/workflows/ci.yml` (comment URL only)
- `custom/Espo/Modules/NonprofitEspocrm/Tools/Installer.php` (comment URL only)

## Verification

- Ripgrep: no remaining `~/safehouse/espocrm-documentation` in agent-facing
  artifacts (except historical mention in `000`).
- Espo core `application/` / `client/lib/` left unchanged (Principle II).

## Blockers

- Feature `001` SC-006 still needs owner acceptance.
- Feature `002` is live on prod; T016 checked.

## Next steps

1. Owner: next Spec Kit feature — likely backlog rank 1 from `010`
   (decouple GoogleIntegration/WorkflowEngine client from NonprofitEspocrm)
   **or** close SC-006 first.
2. Do not `git push` unless the owner asks.
