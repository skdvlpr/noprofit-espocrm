---
description: "Task list for Prima Nota off-books / donor-pocket"
---

# Tasks: Prima Nota Off-Books Entries

**Input**: Design documents from `/specs/002-prima-nota-off-books/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

**Tests**: Included (constitution XIV + spec independent tests). Write/adjust tests with implementation in this repo’s PHPUnit style.

**Complexity scores** (constitution XV, 1–10): noted per task. Session model implements all (user requested full circle now).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 / US2 / US3 from spec.md

## Phase 1: Setup

**Purpose**: Confirm extension paths and ignore hygiene (no new project).

- [x] T001 Verify `.gitignore` already covers PHP/`vendor`/`data/config.php`/`.env*` for this Espo tree (repo root `.gitignore`) — complexity 1
- [x] T002 Confirm DDEV is the local PHP runtime; no host `php` for rebuild/PHPUnit (`.ddev/config.yaml`, `bin/dev-rebuild.sh`) — complexity 2

---

## Phase 2: Foundational

**Purpose**: Metadata + Formula + layouts + i18n so DonorPocket and exclude exist before reporting/hook/tests.

**⚠️ CRITICAL**: No user-story reporting work until this phase is complete

- [x] T003 Add `DonorPocket` to `donationPaymentProvider.options` and add `excludeFromDigitalReports` bool in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/PrimaNota.json` — complexity 4
- [x] T004 [P] Append Formula auto-exclude script in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/formula/PrimaNota.json` — complexity 5
- [x] T005 [P] IT+EN field labels, enum option, tooltips (IT ASCII-only) in `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/it_IT/PrimaNota.json` and `custom/Espo/Modules/NonprofitEspocrm/Resources/i18n/en_US/PrimaNota.json` — complexity 3
- [x] T006 [P] Place `excludeFromDigitalReports` on detail/detailSmall/filters in `custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/PrimaNota/detail.json`, `detailSmall.json`, `filters.json` — complexity 3
- [x] T007 Relax `donationPaymentProvider` dynamicLogic readOnly from “has id” to “equals Stripe” in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/clientDefs/PrimaNota.json` — complexity 4

**Checkpoint**: Metadata shape complete

---

## Phase 3: User Story 1 - Record privately paid purchase without changing digital totals (P1) 🎯 MVP

**Goal**: Saving DonorPocket (or Cash) auto-excludes; list still shows the row.

**Independent Test**: Create DonorPocket expense of amount X; list +1; Saldo digitale / dashlet realised totals unchanged.

### Tests for User Story 1

- [x] T008 [US1] Add PHPUnit coverage for Formula exclude on DonorPocket/Cash and counted BankTransfer in `tests/integration/Espo/Modules/NonprofitEspocrm/PrimaNotaTest.php` — complexity 6
- [x] T009 [US1] Assert `getTotals` ignores DonorPocket id and still counts a digital row in `tests/integration/Espo/Modules/NonprofitEspocrm/ReportingStatsTest.php` — complexity 7

### Implementation for User Story 1

- [x] T010 [US1] Switch `PrimaNotaStatsProvider::bankChannelWhere()` to exclude-flag contract in `custom/Espo/Modules/NonprofitEspocrm/Tools/Reporting/PrimaNotaStatsProvider.php` — complexity 6
- [x] T011 [US1] Allow non-Stripe platform changes after create in `custom/Espo/Modules/NonprofitEspocrm/Hooks/PrimaNota/ProtectDonationPaymentProvider.php` (keep Stripe locked) — complexity 6

**Checkpoint**: US1 testable locally after rebuild + PHPUnit

---

## Phase 4: User Story 2 - Future reporting uses only the exclude flag (P1)

**Goal**: Digital totals documented and implemented without a magic platform list; Cash stays excluded via flag + backfill.

**Independent Test**: Reviewer states the rule using only exclude + status; Cash row still out of totals.

- [x] T012 [US2] Add idempotent RebuildAction backfill for Cash/DonorPocket in `custom/Espo/Modules/NonprofitEspocrm/Core/Rebuild/BackfillPrimaNotaDigitalExclude.php` and register it in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/rebuild.json` — complexity 5
- [x] T013 [US2] Keep `contracts/digital-totals.md` aligned with the PHP where clause (comment in `PrimaNotaStatsProvider.php`) — complexity 2
- [x] T014 [US2] Update Cash-exclusion comments and optional smoke option check in `bin/smoke-prima-nota-stripe-commission.php` if it asserts provider options — complexity 3

**Checkpoint**: FR-008/FR-009 satisfied after rebuild

---

## Phase 5: User Story 3 - Tag two Metro production rows (P2)

**Goal**: After deploy, two known expenses become DonorPocket + exclude.

**Independent Test**: GET both ids; platform DonorPocket; exclude true; still in list.

- [x] T015 [US3] Adjust hook/PHPUnit so Other→DonorPocket (and DonorPocket→BankTransfer) is allowed; Stripe change still blocked in `tests/integration/Espo/Modules/NonprofitEspocrm/PrimaNotaTest.php` — complexity 5
- [ ] T016 [US3] After CI deploy to `crm.safehouse.community`, verify Metadata then PUT the two ids per `specs/002-prima-nota-off-books/contracts/rest-prima-nota.md` (API user; no secrets in git) — complexity 6

**Checkpoint**: SC-002 measurable on prod

---

## Phase 6: Polish & Cross-Cutting

- [x] T017 Run `ddev exec php command.php rebuild` then DDEV PHPUnit for PrimaNota + ReportingStats per `specs/002-prima-nota-off-books/quickstart.md` — complexity 4
- [x] T018 [P] Append `.specify/progress/009-implement-prima-nota-off-books.md` and update `.specify/progress/README.md` index — complexity 2
- [x] T019 Mark completed tasks `[x]` in `specs/002-prima-nota-off-books/tasks.md` — complexity 1

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: immediate
- **Foundational (Phase 2)**: depends on Setup; BLOCKS stories
- **US1 (Phase 3)**: depends on Foundational (T010 needs T003)
- **US2 (Phase 4)**: depends on T003 + T010 (backfill assumes column exists)
- **US3 (Phase 5)**: depends on US1 hook change; prod PUT depends on push/deploy
- **Polish**: after local tests green

### User Story Dependencies

- **US1 (P1)**: after Phase 2
- **US2 (P1)**: can proceed once T003+T010 exist; RebuildAction parallel to tests
- **US3 (P2)**: needs US1 hook; prod after deploy

### Parallel Opportunities

- T004, T005, T006 after T003 (or with T003 if coordinated)
- T008 and T009 after T003–T011 in same implement pass
- T018 docs while tests run

---

## Parallel Example: Foundational

```bash
Task: "Add DonorPocket + exclude bool in entityDefs/PrimaNota.json"
Task: "i18n it_IT + en_US PrimaNota.json"
Task: "layouts detail/detailSmall/filters"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1–2 metadata/Formula/layouts
2. Reporting filter + hook
3. PHPUnit US1
4. Validate locally

### Incremental Delivery

1. US1 local → US2 backfill → push `main` (user asked) → wait deploy/rebuild → US3 PUT two ids

### Suggested MVP scope

User Story 1 (record + totals ignore). US2 is required before switching the filter in production. US3 is the production data fix.

---

## Notes

- Deploy is **main-only** (`.github/workflows/ci.yml`). Feature work may sit on `002-prima-nota-off-books` then merge to `main` for auto-deploy.
- Do not PUT prod until Metadata shows `DonorPocket`.
- Ask before commit unless this session already includes explicit push (user: push then wait for auto-deploy).
