# 001 — Constitution v1.1.0 + specify custom-code-audit

**Date:** 2026-08-31  
**Agent:** Cursor Auto

## State

- Constitution amended **1.0.0 → 1.1.0**: Principle III (one-in-progress +
  specify-ahead exception); Principle XVII (mandatory Next Actions footer);
  Development Workflow aligned.
- `AGENTS.md` synced with one-liner prefs for III/XVII.
- Active feature: `specs/001-custom-code-audit` (Custom Code Compliance Audit)
  — `spec.md` Draft, quality checklist pass.
- `.specify/feature.json` → `specs/001-custom-code-audit`.

## Files

- `.specify/memory/constitution.md`
- `AGENTS.md`
- `specs/001-custom-code-audit/spec.md`
- `specs/001-custom-code-audit/checklists/requirements.md`
- `.specify/feature.json`
- `.specify/progress/001-constitution-and-specify-audit.md` (this file)

## Verification

- No `[NEEDS CLARIFICATION]` in spec; checklist all checked.
- No product code / CI changes.

## Next steps

1. User: `/speckit-plan` for `001-custom-code-audit` (preferred).
2. Or `/speckit-clarify` if scope should change before planning.
3. Do not start tests/`bin` or CI specs until this feature’s backlog exists,
   unless user authorizes specify-ahead.
