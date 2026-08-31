# Implementation Plan: Custom Code Compliance Audit

**Branch**: `001-custom-code-audit` | **Date**: 2026-08-31 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-custom-code-audit/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Produce a **doc-backed compliance audit** of all Safehouse custom extensions
(five modules + matching client trees) against official EspoCRM documentation
and the project constitution. Deliverable is an English report + ranked
remediation backlog under `.specify/progress/` (and optional copies under this
`specs/` folder)—**not** mass code remediations. Methodology: full inventory +
risk-based deep dives (secrets, ACL/PII, extension boundaries, native-first
anti-patterns, obvious performance risks), with mandatory local+online doc
citations.

## Technical Context

**Language/Version**: Markdown / English prose artifacts; review via ripgrep,
filesystem inventory, and **mandatory DDEV** PHP/Espo probes
(`ddev exec php …` / rebuild). No host PHP. No new application language.

**Primary Dependencies**: DDEV (required local runtime); local Espo docs clone
(`~/safehouse/espocrm-documentation`, remote
https://github.com/espocrm/documentation/); online
https://docs.espocrm.com/; project constitution
`.specify/memory/constitution.md`; existing trees under
`custom/Espo/Modules/` and `client/custom/modules/`.

**Storage**: File-based report artifacts only (git-tracked markdown). No new
database entities.

**Testing**: Manual validation against [quickstart.md](./quickstart.md) and
[contracts/compliance-report.md](./contracts/compliance-report.md) checklist;
any PHPUnit/smoke probes for evidence MUST run under DDEV; no PHPUnit suite
required for the audit report itself (remediation features later inherit
Principle XIV).

**Target Platform**: Maintainer workstation with **DDEV** for all local PHP/Espo
work. Production is **Caddy + automatic TLS/SSL** (`crm.safehouse.community`).
Production probes **out of scope** without explicit user approval.

**Project Type**: Internal compliance audit / documentation deliverable (not a
product UI feature).

**Performance Goals**: Report usable for next-feature selection in under 15
minutes (SC-002); audit itself is offline static analysis first, then DDEV
probes as needed.

**Constraints**: No mass remediations; no `bin/` rewrite or CI redesign as
deliverables (FR-009/FR-010); no production SSH/API without approval; no
non-DDEV local PHP (FR-013); must refresh docs clone before deep review
(FR-012); secrets found MUST NOT be copied into the report (paths/kinds only).

**Scale/Scope**: 5 backend modules + matching client modules (where present) +
related packaging/`dist` leak-path skim; risk-based deep dive, not every layout
line.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I Official-docs / native-first | Audit criteria and citations from Espo docs | PASS — citation table in research + contracts |
| II Extensions only | Audit does not edit `application/`; remediations stay extension-scoped | PASS |
| III One active spec | This feature is the sole active track; sibling specs only if user authorizes specify-ahead | PASS |
| IV Doc-backed planning | This plan cites local + online docs | PASS |
| V Docs beat whim | Spec already bounds “report not fix” | PASS |
| VI Secrets / PII | Report lists locations/kinds; never pastes secret values | PASS |
| VII Safe deploy | No prod apply; prod = Caddy+auto SSL (context only) | PASS |
| VIII Git hygiene | Commit/push only on user ask | PASS |
| IX Builders vs ZIPs | Builders/`dist` reviewed as leak surface only | PASS |
| X Rebuild | After any metadata touch: `ddev exec` rebuild/clear cache | PASS |
| XI Migrations | N/A for audit-only | PASS |
| XII Legacy rethink | Findings may recommend rewrite; no rewrite here | PASS |
| XIII New tech | No new libraries | PASS |
| XIV Tests | Audit itself is checklist-validated; no fake greenwash | PASS |
| XV Agents/models | Implement phase may use stronger subagents for deep module reviews | PASS (deferred to tasks) |
| XVI Progress | Report + handoff in `.specify/progress/` | PASS |
| XVII Next Actions | End each milestone with options | PASS |

**Post-Phase 1 re-check:** Still PASS — design adds report schema contracts only;
no unjustified complexity. Clarification 2026-08-31: DDEV mandatory locally;
production Caddy+auto SSL recorded in Technical Context / FR-013.

## Project Structure

### Documentation (this feature)

```text
specs/001-custom-code-audit/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── compliance-report.md
│   └── finding-schema.md
├── checklists/requirements.md
└── tasks.md                 # later: /speckit-tasks
```

### Source Code (repository root) — audit *targets* (read-only)

```text
custom/Espo/Modules/
├── NonprofitEspocrm/
├── GoogleIntegration/
├── WorkflowEngine/
├── BugTracker/
└── SafehouseAuroraThemes/

client/custom/modules/
├── nonprofit-espocrm/
├── google-integration/
├── workflow-engine/
└── bug-tracker/
# SafehouseAuroraThemes: theme assets under client/ (verify during inventory)

dist/                        # packaged ZIPs — leak-path skim only
bin/build*.sh                # builders — policy/leak note only (no rewrite)
.github/workflows/           # CI leak-path note only (no redesign)
```

**Structure Decision**: Feature produces documentation under
`specs/001-custom-code-audit/` and a canonical report under
`.specify/progress/` (e.g. `010-compliance-audit-report.md` or split files).
No new runtime source tree. Audit **reads** the module paths above.

## Complexity Tracking

> No constitution violations requiring justification.
