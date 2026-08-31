# Tasks: Custom Code Compliance Audit

**Input**: Design documents from `/specs/001-custom-code-audit/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Not requested as automated TDD; validation via quickstart + contract checklists.

**Organization**: Phases by user story (US1–US4). Local PHP MUST use DDEV. Prod = Caddy+auto SSL (approval-gated).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no incomplete dependencies)
- **[Story]**: US1–US4 map to spec user stories
- Include exact file paths

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Docs freshness, DDEV readiness, report skeleton

- [x] T001 Confirm Espo docs clone freshness and record SHA in `.specify/progress/010-compliance-audit-report.md` metadata (`cd ~/safehouse/espocrm-documentation && git pull --ff-only`)
- [x] T002 Ensure DDEV is running for this repo (`ddev describe` / `ddev start`) — block PHP work if DDEV unavailable
- [x] T003 Create report skeleton at `.specify/progress/010-compliance-audit-report.md` matching `specs/001-custom-code-audit/contracts/compliance-report.md` section order
- [x] T004 [P] Add optional index link file `specs/001-custom-code-audit/REPORT.md` pointing to the canonical progress report
- [x] T005 [P] Append start handoff to `.specify/progress/003-implement-compliance-audit.md` (methodology, DDEV required, Caddy prod note)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Full inventory before deep dives; shared finding ID conventions

**⚠️ CRITICAL**: No user-story deep-dive sections until inventory exists

- [x] T006 Inventory all five backends under `custom/Espo/Modules/{NonprofitEspocrm,GoogleIntegration,WorkflowEngine,BugTracker,SafehouseAuroraThemes}/` into report Module Summaries draft
- [x] T007 [P] Inventory matching frontends under `client/custom/modules/{nonprofit-espocrm,google-integration,workflow-engine,bug-tracker}/` and locate SafehouseAuroraThemes client/theme asset paths
- [x] T008 [P] Skim `dist/*.zip` and `bin/build*.sh` for packaging/leak surface notes (no rewrite) in report out-of-scope / packaging notes
- [x] T009 Establish finding ID scheme and empty Findings + Backlog tables in `.specify/progress/010-compliance-audit-report.md` per `specs/001-custom-code-audit/contracts/finding-schema.md`
- [x] T010 Document coverage method (inventory + risk-based deep dive) and limits in the report Methodology section (FR-011)

**Checkpoint**: Five module placeholders + IDs ready; deep dives can proceed

---

## Phase 3: User Story 2 - Security & secrets (Priority: P1)

**Goal**: Explicit Security & secrets section with SecretLocationCandidates and ACL/PII notes

**Independent Test**: Security section alone is reviewable; lists candidates or states none-found after named checks (SC-004)

### Implementation for User Story 2

- [x] T011 [P] [US2] Scan modules for secret-like config/keys/OAuth/Stripe/VAPID patterns (paths/kinds only) under `custom/Espo/Modules/` and `client/custom/modules/`
- [x] T012 [P] [US2] Review Integration/ExternalAccount/App Secret usage vs `~/safehouse/espocrm-documentation/docs/administration/app-secrets.md` and https://docs.espocrm.com/administration/app-secrets/
- [x] T013 [P] [US2] Sample PII entity field ACL / Roles expectations using docs `roles-management.md` + `acl.md` (local + online citations)
- [x] T014 [US2] Optional DDEV metadata probe only if static review insufficient: `ddev exec php …` (never host PHP; never prod)
- [x] T015 [US2] Write Security & secrets section + SecretLocationCandidate rows into `.specify/progress/010-compliance-audit-report.md`
- [x] T016 [US2] Create Critical/High `ComplianceFinding` entries (F-*) with dual citations per finding-schema

**Checkpoint**: US2 complete — security section meets SC-003/SC-004 without raw secrets

---

## Phase 4: User Story 3 - Extension boundaries & coupling (Priority: P2)

**Goal**: Extension map with standalone-safe / soft-depends / hard-depends per module

**Independent Test**: Extension map section classifies all five modules with evidence paths

### Implementation for User Story 3

- [x] T017 [P] [US3] Analyze NonprofitEspocrm coupling surface in `custom/Espo/Modules/NonprofitEspocrm/` (always vertical)
- [x] T018 [P] [US3] Analyze GoogleIntegration for stock-Espo vs Nonprofit hard refs under `custom/Espo/Modules/GoogleIntegration/` and `client/custom/modules/google-integration/`
- [x] T019 [P] [US3] Analyze WorkflowEngine, BugTracker, SafehouseAuroraThemes coupling similarly under their module paths
- [x] T020 [US3] Write Extension map section into `.specify/progress/010-compliance-audit-report.md` with coupling enum + evidence
- [x] T021 [US3] Add extension-boundary findings (F-*) citing `modules.md` / `extension-packages.md` (local + online)

**Checkpoint**: US3 complete — coupling classifications present for all five modules

---

## Phase 5: User Story 4 - Performance & native-first anti-patterns (Priority: P3)

**Goal**: Ranked non-security findings feeding later hygiene specs

**Independent Test**: Non-security findings grouped separately; cross-refs to bin/CI specs where relevant

### Implementation for User Story 4

- [x] T022 [P] [US4] Scan for unbounded load / obvious N+1 / list notStorable risks in custom PHP under `custom/Espo/Modules/`
- [x] T023 [P] [US4] Scan for native-first violations (custom reinvention vs Entity Manager / Formula / Dynamic Logic / hooks) with doc citations
- [x] T024 [US4] Write performance + native-first findings into `.specify/progress/010-compliance-audit-report.md`
- [x] T025 [US4] Add cross-reference notes for deferred tests/`bin` and CI specs (FR-010) without implementing them

**Checkpoint**: US4 complete — non-security backlog material ready

---

## Phase 6: User Story 1 - Prioritized remediation backlog (Priority: P1) 🎯 MVP deliverable close

**Goal**: Single ordered backlog + complete per-module summaries so owner picks next specify in &lt;15 minutes

**Independent Test**: Second agent picks rank-1 specify title from report alone (SC-002)

### Implementation for User Story 1

- [x] T026 [US1] Complete all five ModuleSummary sections in `.specify/progress/010-compliance-audit-report.md` (SC-001)
- [x] T027 [US1] Merge all findings into catalog ordered by severity then rank
- [x] T028 [US1] Build ranked RemediationBacklogItem table (top ≥10 or all) with proposed `/speckit-specify` titles (SC-005)
- [x] T029 [US1] Write Executive summary naming top risk and recommended first remediation
- [x] T030 [US1] Fill Owner acceptance stub (SC-006) awaiting user confirmation
- [x] T031 [US1] Self-validate report against `specs/001-custom-code-audit/quickstart.md` checklist including SC-007 (DDEV + Caddy notes)

**Checkpoint**: Full report contract satisfied; feature Done pending user SC-006

---

## Phase 7: Polish & Cross-Cutting

**Purpose**: Handoff, no secret leakage, Spec Kit hygiene

- [x] T032 Redact pass: ensure `.specify/progress/010-compliance-audit-report.md` contains no raw secrets (research R5)
- [x] T033 Update `.specify/progress/003-implement-compliance-audit.md` with final state, files, verification, blockers
- [x] T034 [P] Update `.specify/progress/README.md` index with report + implement handoff entries
- [x] T035 Ask user for SC-006 acceptance and whether to commit (no commit/push without ask)

---

## Dependencies & Execution Order

### Phase dependencies

- Phase 1 → Phase 2 → (US2 ∥ US3 ∥ US4 deep dives after T010) → Phase 6 consolidates → Phase 7
- US2/US3/US4 may run in parallel after foundational inventory (different report sections / finding ID ranges agreed in T009)
- US1 (Phase 6) depends on US2–US4 findings existing

### User story dependency graph

```text
Setup → Foundation → US2 (secrets)
                   → US3 (coupling)  ─┬→ US1 backlog + summaries → Polish
                   → US4 (perf/native)┘
```

### Parallel opportunities

- T004 ∥ T005 after T003
- T007 ∥ T008 after T006 starts
- T011 ∥ T012 ∥ T013 (US2 scans)
- T017 ∥ T018 ∥ T019 (US3 modules) — prefer stronger subagents (constitution XV)
- T022 ∥ T023 (US4)

### Complexity scoring (constitution XV — for `/speckit-implement`)

| Task band | Score 1–10 | Model hint |
|-----------|------------|------------|
| T001–T010 setup/inventory | 2–4 | Auto OK |
| T011–T016 secrets/ACL | 7–9 | Stronger subagent |
| T017–T021 coupling | 6–8 | Stronger subagent |
| T022–T025 anti-patterns | 5–7 | Auto or stronger |
| T026–T035 consolidate/polish | 3–5 | Auto OK |

---

## Parallel example: after Foundation

```bash
# Agent A: US2 secrets (T011–T016) → report Security section
# Agent B: US3 coupling (T017–T021) → Extension map
# Agent C: US4 perf/native (T022–T025) → findings
# Then single agent: US1 consolidate T026–T031
```

---

## Implementation strategy

### MVP

Complete Phase 1–2 + US2 + US1 minimum path (security-ranked backlog + five module stubs filled) if time-boxed—prefer full US2–US4 before declaring SC-006 ready.

### Incremental

1. Skeleton + inventory  
2. Security section (highest value)  
3. Coupling map  
4. Perf/native  
5. Backlog + executive summary  
6. Owner acceptance  

### Suggested next command

`/speckit-implement` (execute tasks T001→…; DDEV mandatory for PHP)
