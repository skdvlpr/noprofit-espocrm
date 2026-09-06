# Progress handoffs

Append-only English logs for agent handoff. Replaces Notion executor logs.

## How to append

1. Create the next numbered file when starting a **new session milestone**  
   (`001-….md`, `002-….md`, …) **or** append a dated section to the latest file
   if it is still the same short slice.
2. Each entry MUST include:
   - **Date** (ISO `YYYY-MM-DD`) and agent identity
   - **State** — what is true now
   - **Files changed** (paths)
   - **Verification** — tests/smokes/rebuild done
   - **Blockers**
   - **Next steps** — exact enough for another agent after context loss
3. Do **not** overwrite history. Do **not** write Notion executor logs.
4. Legacy critical extract only: `../progress_old/`.

## Index

| File | Topic |
|------|--------|
| `000-sdd-bootstrap.md` | Constitution ratification + SDD bootstrap |
| `001-constitution-and-specify-audit.md` | Constitution v1.1.0 + `001-custom-code-audit` specify |
| `002-plan-custom-code-audit.md` | Plan Phase 0–1 for compliance audit |
| `003-clarify-replan-tasks.md` | DDEV/Caddy clarify + re-plan + tasks.md |
| `004-constitution-v1.2.0-ddev-caddy.md` | Constitution XVIII local↔prod (DDEV/Caddy) |
| `005-implement-compliance-audit.md` | Implement audit + report |
| `010-compliance-audit-report.md` | **Canonical compliance audit report** |
| `006-specify-prima-nota-off-books.md` | Specify `002-prima-nota-off-books` |
| `007-plan-prima-nota-off-books.md` | Plan Phase 0–1 for off-books Prima Nota |
| `008-tasks-prima-nota-off-books.md` | Tasks T001–T019 for off-books Prima Nota |
| `009-implement-prima-nota-off-books.md` | Implement off-books Prima Nota (T016 prod pending deploy) |
