# 006 — Specify Prima Nota off-books / donor-pocket

**Date:** 2026-09-06  
**Agent:** Cursor Auto (`/speckit-specify`)

## State

- New feature `specs/002-prima-nota-off-books` (user-authorised specify-ahead).
- Hybrid: IT platform **Dalla tasca o c/c donatore** + bool **exclude from digital reports**.
- Digital totals MUST filter exclude flag only (plus existing payment status).
- Two Metro rows in scope (tag at implement with explicit prod approval).
- `.specify/feature.json` → `specs/002-prima-nota-off-books`.
- API key used earlier this session for **read-only** prod research; do not reuse for writes until user says so. User will rotate the key.

## Files

- `specs/002-prima-nota-off-books/spec.md`
- `specs/002-prima-nota-off-books/checklists/requirements.md`
- `.specify/feature.json`
- this handoff

## Verification

- Checklist 16/16; no `[NEEDS CLARIFICATION]`.
- No product code this turn.

## Next steps

1. `/speckit-plan` then `/speckit-tasks` then `/speckit-implement` for 002.
2. Do not PATCH prod until user explicitly approves.
3. `001-custom-code-audit` SC-006 still open.
