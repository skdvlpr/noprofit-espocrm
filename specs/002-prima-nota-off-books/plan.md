# Implementation Plan: Prima Nota Off-Books Entries

**Branch**: `002-prima-nota-off-books` | **Date**: 2026-09-06 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-prima-nota-off-books/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Bookkeepers must record organisation movements paid from a person’s own pocket or account (never through the organisation digital/bank channel). Those rows stay in the Prima Nota **list** but must not change **Saldo digitale**, period banners, or ledger dashlets.

**Approach (native-first):** add enum key `DonorPocket` on existing `donationPaymentProvider` (IT label *Dalla tasca o c/c donatore*) plus bool `excludeFromDigitalReports` (read-only in UI; Formula sets it). Digital aggregations in `PrimaNotaStatsProvider` filter `excludeFromDigitalReports != true` (plus existing payment-status rules). Rebuild backfill sets the flag on existing `Cash` (and `DonorPocket`) rows so Contanti stays out of totals after the filter switch. Two production Metro expenses are retagged via API **after** auto-deploy + rebuild.

## Technical Context

**Language/Version**: PHP 8.4 (EspoCRM 10+ extension tree; production and CI already pin 8.4). Formula Script (Espo native). Client JS only if existing donation-platform view needs no change (metadata-driven enum).

**Primary Dependencies**: EspoCRM Entity Manager metadata (`entityDefs`, layouts, i18n), Formula before-save script, existing `NonprofitEspocrm` hooks and `PrimaNotaStatsProvider`. DDEV for all local PHP. No new Composer libraries.

**Storage**: MySQL/MariaDB via Espo ORM. New bool column on `prima_nota` (Espo rebuild schema). Default `false` applied to existing rows by the DB; Cash/DonorPocket then backfilled to `true`.

**Testing**: PHPUnit integration on isolated `db_test` (`tests/integration/Espo/Modules/NonprofitEspocrm/PrimaNotaTest.php`, `ReportingStatsTest.php`) via **DDEV**. Optional existing smokes remain probes, not the gate.

**Target Platform**: Local DDEV (Caddy inside DDEV). Production `crm.safehouse.community` (Caddy + automatic TLS). Deploy: GitHub Actions `CI` job `deploy` on **push to `main` only**, which rsyncs then runs `php command.php rebuild`.

**Project Type**: EspoCRM custom module (extension layout under `custom/Espo/Modules/NonprofitEspocrm` + `client/custom/modules/nonprofit-espocrm`).

**Performance Goals**: Same aggregation path as today (SUM of `amountIn`/`amountOut`); extra bool predicate is indexed optionally, not required at current ledger size.

**Constraints**: Changes only in NonprofitEspocrm. No host PHP. No secrets in git. Production API writes only after successful auto-deploy + rebuild/cache. `ProtectDonationPaymentProvider` currently blocks **all** platform changes after create — must allow non-Stripe changes so FR-010 and US1.3 work. Italian `PrimaNota.json` is ASCII-only (existing smoke).

**Scale/Scope**: One entity (`PrimaNota`), one reporting provider, two production row updates, one rebuild backfill action. No new entity types.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I Official-docs / native-first | Enum + bool + Formula; no custom total API | PASS — see research citations |
| II Extensions only | Edit NonprofitEspocrm only; never `application/` | PASS |
| III One active spec | `002-prima-nota-off-books` is the active track | PASS |
| IV Doc-backed planning | Local + online citations in research.md | PASS |
| V Docs beat whim | Hybrid already specified; no extra entities | PASS |
| VI Secrets / PII | API key never committed; prod PUT uses env/header only | PASS |
| VII Safe deploy | Ship via existing main CI; wait for deploy before prod data | PASS |
| VIII Git hygiene | Feature branch; push to `main` only on explicit user ask (this session: user asked to push) | PASS |
| IX Builders vs ZIPs | No new builder/ZIP | PASS |
| X Rebuild | Local `ddev exec php command.php rebuild`; prod via CI post-deploy + verify | PASS |
| XI Migrations | Schema via rebuild; data via RebuildAction + two API PUTs | PASS |
| XII Legacy rethink | Replace “not Cash” as the *only* digital filter | PASS |
| XIII New tech | None | PASS |
| XIV Tests | Integration tests for formula, hook relaxation, reporting filter | PASS |
| XV Agents & models | Task complexity scored in tasks.md; implement in this session as user requested | PASS |
| XVI Progress | Handoffs under `.specify/progress/` | PASS |
| XVII Communication | Chat RU; artifacts EN | PASS |
| XVIII DDEV ↔ prod | Local DDEV; prod Caddy host, no DDEV | PASS |

**Post-design re-check:** still PASS. RebuildAction is Espo-native (`RebuildAction` interface, `metadata/app/rebuild.json`), not a one-off SQL file on the server.

## Project Structure

### Documentation (this feature)

```text
specs/002-prima-nota-off-books/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── digital-totals.md
│   └── rest-prima-nota.md
└── tasks.md
```

### Source Code (repository root)

```text
custom/Espo/Modules/NonprofitEspocrm/
├── Resources/metadata/entityDefs/PrimaNota.json
├── Resources/metadata/formula/PrimaNota.json
├── Resources/metadata/clientDefs/PrimaNota.json
├── Resources/metadata/app/rebuild.json
├── Resources/layouts/PrimaNota/detail.json
├── Resources/layouts/PrimaNota/detailSmall.json
├── Resources/layouts/PrimaNota/filters.json
├── Resources/i18n/it_IT/PrimaNota.json
├── Resources/i18n/en_US/PrimaNota.json
├── Tools/Reporting/PrimaNotaStatsProvider.php
├── Hooks/PrimaNota/ProtectDonationPaymentProvider.php
└── Core/Rebuild/BackfillPrimaNotaDigitalExclude.php

tests/integration/Espo/Modules/NonprofitEspocrm/
├── PrimaNotaTest.php
└── ReportingStatsTest.php
```

**Structure Decision**: Existing NonprofitEspocrm extension tree (constitution II). No new module, no core edits.

## Complexity Tracking

> No constitution violations requiring justification.
