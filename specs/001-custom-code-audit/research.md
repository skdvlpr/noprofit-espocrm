# Research: Custom Code Compliance Audit

**Feature**: `001-custom-code-audit`  
**Date**: 2026-08-31

## Docs freshness (FR-012)

- **Decision**: Confirm local docs at start of implement; pull if >7 days stale.
- **Evidence (plan time)**: `~/safehouse/espocrm-documentation` HEAD
  `ab2a5ee338be141ffe0d1b2c29ba742432bba089` (2026-08-31), remote
  `https://github.com/espocrm/documentation/`, already up to date after fetch.
- **Rationale**: Constitution freshness duty + FR-012.
- **Alternatives**: Online-only — rejected as primary; used as fallback if pull fails.

## R1 — Deliverable medium

- **Decision**: English markdown report in `.specify/progress/` (primary) with
  optional mirror/summary under `specs/001-custom-code-audit/`.
- **Rationale**: Constitution XVI retires Notion logs; progress is the handoff
  surface; specs folder keeps Spec Kit linkage.
- **Alternatives considered**: Notion page — rejected (retired); JSON-only
  findings DB — overkill for v1; PDF — harder to diff/review in git.

## R2 — Coverage method

- **Decision**: Full module inventory + risk-based deep dive.
  1. Inventory: scopes, manifests, backend/frontend trees, package ZIPs presence.
  2. Deep dive order: secrets/leak paths → ACL/PII → extension boundaries →
     native-first anti-patterns → performance smells.
- **Rationale**: Spec edge case forbids claiming line-by-line perfection;
  SC-001 requires 100% module summaries.
- **Alternatives**: Exhaustive every-file review — too slow, low marginal
  value; random sample only — fails SC-001.

## R3 — Severity rubric

- **Decision**:
  - **Critical**: Secret in git/ZIP/logs; unauthenticated sensitive access;
    production-breaking ACL hole for PII.
  - **High**: Hard nonprofit coupling in “universal” extension; secrets outside
    App Secrets with realistic leak path; clear IDOR-class risk in custom code.
  - **Medium**: Native-first violations with upgrade/maintenance cost;
    packaging/layout doc drift; Soft coupling.
  - **Low**: Style/doc inconsistency; minor performance smell without leak.
- **Rationale**: Aligns User Story 2 (security first) and Story 4 (separate
  non-security ranks).
- **Alternatives**: CVSS full scoring — unnecessary for internal backlog.

## R4 — Official expectations (citation anchors)

Judgments MUST cite local + online (constitution IV). Primary anchors:

| Topic | Local | Online | Why |
|-------|-------|--------|-----|
| Modules | `~/safehouse/espocrm-documentation/docs/development/modules.md` | https://docs.espocrm.com/development/modules/ | Backend/frontend paths, order |
| Extension packages | `.../development/extension-packages.md` | https://docs.espocrm.com/development/extension-packages/ | ZIP layout, scripts |
| Extensions admin | `.../administration/extensions.md` | https://docs.espocrm.com/administration/extensions/ | Install/upgrade behaviour |
| Coding practices | `.../development/coding-practices.md` | https://docs.espocrm.com/development/coding-practices/ | Namespace / Tools vs Controllers |
| Hooks | `.../development/hooks.md` | https://docs.espocrm.com/development/hooks/ | When hooks are appropriate |
| ACL | `.../development/acl.md` | https://docs.espocrm.com/development/acl/ | Server-side checks |
| Roles | `.../administration/roles-management.md` | https://docs.espocrm.com/administration/roles-management/ | Least privilege |
| App Secrets | `.../administration/app-secrets.md` | https://docs.espocrm.com/administration/app-secrets/ | Secret store |
| Entity Manager | `.../administration/entity-manager.md` | https://docs.espocrm.com/administration/entity-manager/ | Fields/layouts native path |
| Metadata | `.../development/metadata.md` | https://docs.espocrm.com/development/metadata/ | Metadata expectations |
| Tests | `.../development/tests.md` | https://docs.espocrm.com/development/tests/ | Cross-ref only (hygiene spec later) |

## R5 — Secrets reporting hygiene

- **Decision**: Report **path + kind** (e.g. “OAuth client secret field in
  Integration entity / config key name”) never raw values; redaction mandatory
  if a value is accidentally viewed.
- **Rationale**: Constitution VI; report itself must not become a leak vector.
- **Alternatives**: Full secret dump for “completeness” — forbidden.

## R6 — Production and DDEV

- **Decision**: **DDEV is mandatory** for all local PHP/Espo CLI, rebuild, and
  metadata probes (`ddev exec …`). Host PHP is forbidden. Production is served
  by **Caddy with automatic SSL**; production inspection = blocked finding +
  explicit user approval (never the default evidence path).
- **Rationale**: User clarification 2026-08-31 (FR-013 / SC-007); aligns with
  Safehouse local/prod split.
- **Alternatives**: Optional DDEV / host PHP fallback — rejected; silent prod
  SSH — rejected.

## R7 — Coupling classification

- **Decision**: Per module label:
  - **Standalone-safe**: usable on stock Espo without NonprofitEspocrm.
  - **Soft-depends**: detects Nonprofit and enhances; degrades cleanly.
  - **Hard-depends**: breaks or is meaningless without Nonprofit entities.
- **Rationale**: User Story 3; constitution II.
- **Alternatives**: Binary “coupled/not” — too coarse for remediation planning.

## R8 — Relationship to queued specs

- **Decision**: Cross-reference findings that belong in tests/`bin` hygiene or
  CI harden specs; do not implement those specs here. Recommend specify-ahead
  only if audit mid-flight shows those specs’ scope would be wrong without
  early drafts; user decides (constitution III).
- **Rationale**: FR-010 + one-in-progress rule.
- **Alternatives**: Fold bin/CI into this feature — rejected by spec.

## R9 — Agent execution model (for tasks/implement)

- **Decision**: Inventory can run on Auto; deep per-module security/coupling
  slices SHOULD use stronger subagents in parallel **read-only** (constitution
  XV), then consolidate into one report.
- **Rationale**: Large tree; parallel read does not violate one-feature
  implement rule.
- **Alternatives**: Single-threaded entire tree — slower, more context loss.

## Unresolved clarifications

None remaining for plan gates. Spec had zero `[NEEDS CLARIFICATION]` markers.
