# 011 — Prod tag Metro off-books rows

**Date:** 2026-09-06  
**Agent:** Cursor Auto

## State

- Pushed `fd3e53f` to `origin/main`. CI run `34026118682` **success** (test 8m35s, deploy 36s). Server log: `Rebuild has been done.`
- Metadata on prod includes `DonorPocket` and `excludeFromDigitalReports`.
- API user `cursor_ide` PUT both Metro PrimaNota ids to `DonorPocket`; Formula set `excludeFromDigitalReports=true`. Rows still GET-able (not deleted).

## Files

- `specs/002-prima-nota-off-books/tasks.md` (T016 checked; uncommitted unless user asks)

## Verification

- GET before: both `BankTransfer`, exclude false, Inviato, amounts 61.56 and 36.42.
- PUT 200 both; GET after: `DonorPocket`, exclude true.

## Blockers

- None for FR-010. User should rotate the API key (it was used in chat earlier). Uncommitted local: constitution/AGENTS/vendor noise — not part of this feature.

## Next steps

- Confirm Saldo digitale in the UI (hard refresh).
- Commit T016 checkbox if desired.
- Feature 001 SC-006 still open.
