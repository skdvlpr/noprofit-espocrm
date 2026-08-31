# Contract: Compliance Report Document

**Feature**: `001-custom-code-audit`  
**Consumers**: Project owner, follow-on Spec Kit sessions, future agents.

## Location

- **Canonical**: `.specify/progress/010-compliance-audit-report.md` (or the next
  free `NNN-compliance-audit-*.md` if `010` is taken at implement time).
- **Optional index**: `specs/001-custom-code-audit/REPORT.md` linking to canonical.

## Required sections (order)

1. **Title & metadata** — date, docs clone SHA, auditor identity, scope list.
2. **Executive summary** — top risks and recommended first remediation.
3. **Methodology & coverage limits** — FR-011.
4. **Security & secrets** — User Story 2; may state “none found” after named checks.
5. **Extension map (coupling)** — User Story 3.
6. **Per-module summaries** — all five modules (SC-001).
7. **Findings catalog** — all `ComplianceFinding` records (see finding-schema).
8. **Ranked remediation backlog** — User Story 1; top items specify-ready (SC-005).
9. **Cross-references** — notes for deferred tests/`bin` and CI specs (FR-010).
10. **Owner acceptance** — checkbox / progress note for SC-006.

## Non-negotiable rules

- Never include raw secret values (research R5).
- Every Critical/High finding MUST include local + online citation (SC-003).
- No remediation code patches presented as “done” for this feature (FR-009).

## Acceptance probe

A second agent reading only this document can name the next Spec Kit feature
title from backlog rank 1 without opening module source trees (SC-002).
