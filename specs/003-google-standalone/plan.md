# Implementation Plan: Standalone Google calendar; retire WorkflowEngine

**Branch**: `003-google-standalone` | **Date**: 2026-09-15 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-google-standalone/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Safehouse keeps Google Calendar as a **standalone** extension: it MUST run on
stock Espo without NonprofitEspocrm and without WorkflowEngine. WorkflowEngine
is unused here and is removed from this product (uninstall + stop shipping so
rsync cannot restore it). Google calendar MUST attach to **any** live Espo
entity type with a suitable date field via metadata + CalendarDateSource, not
a closed Meeting/Call/Task/Opportunity-only catalog. Placeholder UI MUST live
inside GoogleIntegration (today two field views AMD-require
`nonprofit-espocrm:lib/template-variable-inserter`).

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10+ extension JS (AMD). DDEV only for
local PHP.

**Primary Dependencies**: Espo Metadata (`scopes`, `entityDefs`, Additional
builders), ORM defs, GoogleIntegration CalendarDateSource / CalendarTemplate,
existing Google OAuth. No new Composer libraries. No WorkflowEngine.

**Storage**: Existing GoogleIntegration tables (CalendarDateSource,
CalendarTemplate, ExternalAccount). No new entity types. WorkflowEngine
tables retired on this instance after uninstall (Espo extension uninstall +
module tree gone).

**Testing**: DDEV PHPUnit (GoogleIntegration integration tests) + rewrite
`bin/smoke-google-integration.php` so coupling is a **failure**. Drop or skip
WorkflowEngine unit/smoke in this tree. No host PHP.

**Target Platform**: Local DDEV. Production `crm.safehouse.community` only
after explicit owner push/uninstall approval.

**Project Type**: EspoCRM custom modules under `custom/Espo/Modules/` +
`client/custom/modules/`.

**Performance Goals**: Metadata additional builders already iterate active
date-source entity types (small N). MUST NOT scan all scopes with per-type
file I/O on every request beyond current rebuild-time builders.

**Constraints**: MUST NOT edit `application/` or Espo core. MUST NOT fatal if
NonprofitEspocrm or a seed entity type is missing. MUST NOT hardcode SQL
table names in new runtime (DateSourceEntityTypesReader already uses a
rebuild-time PDO cache — keep as existing exception or move to Metadata/
ORM at implement if touched). Italian UI preserved.

**Scale/Scope**: GoogleIntegration client + metadata builders + installer
seeds + Nonprofit AfterInstall sibling list + remove WorkflowEngine module
tree and tests from this git root. BugTracker / Aurora unchanged.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I Official-docs / native-first | Metadata scopes + entityDefs; AdditionalBuilder | PASS — research citations |
| II Extensions only + F-EXT-UNIVERSAL | Google owns UI; WF removed; no hard peer ZIP | PASS |
| III One active spec | `003-google-standalone` replaces decouple-google-client | PASS |
| IV Doc-backed planning | GitHub blob cites in spec + research | PASS |
| V Docs beat whim | Owner cancelled F-002 split; uninstall WF | PASS |
| VI Secrets | No new secrets; OAuth unchanged | PASS |
| VII Safe deploy | Local DDEV now; prod rsync/uninstall on owner ask | PASS |
| VIII Git | Ask before commit/push | PASS |
| IX Builders vs ZIPs | Update GI smoke/packaging if they encode coupling | PASS |
| X Rebuild | `ddev exec php command.php rebuild` after metadata/layout | PASS |
| XI Migrations | Uninstall WF via Espo extension lifecycle, not ad-hoc DROP | PASS |
| XII Legacy | Closed entity catalog and nonprofit AMD require are the debt | PASS |
| XIII New tech | None | PASS |
| XIV Tests | PHPUnit + smoke rewrite; no stupid tests | PASS |
| XV Models | Complexity scored at `/speckit-tasks` | PASS |
| XVI Progress | `.specify/progress/014-…` | PASS |
| XVII Communication | Chat RU; artifacts EN | PASS |
| XVIII DDEV ↔ prod | Local DDEV; prod Caddy approval-gated | PASS |
| XIX Native fields | No new field types; reuse date/datetime | PASS |
| XX Live REST | Not required for this design | PASS |

**Post-design re-check:** PASS. Removing WF from git is the only durable way
rsync does not reinstall it. Google entity catalog = live `scopes.*.entity`
plus operator CalendarDateSource, not a PHP `CORE_ENTITY_TYPES` union that
fails closed.

## Project Structure

### Documentation (this feature)

```text
specs/003-google-standalone/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── google-ui-standalone.md
│   ├── entity-catalog.md
│   └── workflow-engine-removed.md
└── tasks.md                 # NOT created by /speckit-plan
```

### Source Code (repository root)

```text
custom/Espo/Modules/GoogleIntegration/
custom/Espo/Modules/NonprofitEspocrm/AfterInstall.php
client/custom/modules/google-integration/
client/custom/modules/nonprofit-espocrm/src/lib/template-variable-inserter.js
bin/smoke-google-integration.php
tests/integration/Espo/Support/SafehouseBaseTestCase.php
custom/Espo/Modules/WorkflowEngine/          # DELETE from this product
client/custom/modules/workflow-engine/      # DELETE
tests/unit/Espo/Modules/WorkflowEngine/     # DELETE
bin/smoke-workflow-engine.php               # DELETE
bin/packaging/WorkflowEngine-zip-AfterInstall.php  # DELETE
```

**Structure Decision**: Stay in the existing Espo module trees. Do not add
apps/ or a second package manager.

## Complexity Tracking

> No constitution violations requiring justification.
