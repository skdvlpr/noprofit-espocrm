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
| `011-prod-tag-metro-off-books.md` | Prod PUT two Metro PrimaNota rows after CI deploy |
| `012-docs-cite-github-and-constitution-v1.3.md` | Constitution v1.3.0 + GitHub Espo doc cites |
| `013-close-001-specify-f001.md` | SC-006 close + specify `003-decouple-google-client` |
| `014-google-standalone-specify-plan.md` | Rewrite 003 Google standalone; retire WF; constitution v1.4.0 |
| `015-google-standalone-implement.md` | Implement 003: Google AMD standalone, delete WF from this product |
| `016-specify-contact-first-crm-user.md` | Specify-ahead 004 contact-first volunteer User |
| `017-plan-contact-first-crm-user.md` | Plan 004: linkedUser identity, Contact competences, User mirrors |
| `018-tasks-contact-first-crm-user.md` | Tasks T001–T029 for 004 (local DDEV; wipe/prod skipped) |
| `019-implement-contact-first-crm-user.md` | Implement 004: prod dump → DDEV, copy, notStorable; prod apply Skip |
| `020-specify-repair-create-crm-user.md` | Specify 004.1: repair create-User + side panel, access email, sync |
| `021-plan-repair-create-crm-user.md` | Plan 004.1: drawer, sendAccessInfo, channel sync |
| `022-tasks-repair-create-crm-user.md` | Tasks T001–T027 for 004.1 (local DDEV; prod/mail Skip) |
| `023-implement-repair-create-crm-user.md` | Implement 004.1: drawer, sendAccessInfo, channel sync; dual-close after Pass |
| `024-specify-create-user-review.md` | Specify 004.2: review modal, unique email, Contact mirror |
| `025-implement-create-user-review.md` | Implement 004.2 on DDEV; custom/ only; owner UAT V1–V10 |
| `026-uat-repair-create-user-review.md` | 004.2 UAT repair: layout, modal copy, cron, filters, CID logo |
| `027-access-info-logo-gmail-cid.md` | Access-info logo: Htmlizer-safe CID for Gmail |
| `028-prod-transfer-004-family.md` | Commit/push 004 family; compare models; prod copy + drop leftover tables |
| `029-specify-contact-types-lead-convert.md` | Specify 004.3: Contact multi-type + Lead convert with CRM user |
| `030-plan-contact-types-lead-convert.md` | Plan 004.3: multiEnum, native convert, hard Contact email unique |
| `031-tasks-contact-types-lead-convert.md` | Tasks T001–T039 for 004.3 (complexity 8/10) |
