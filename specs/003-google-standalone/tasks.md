---
description: "Task list for standalone Google calendar and WorkflowEngine retirement"
---

# Tasks: Standalone Google calendar; retire WorkflowEngine

**Input**: Design documents from `/specs/003-google-standalone/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/)

**Tests**: Spec US4 / FR-009 require smoke + PHPUnit to fail on remaining coupling. Include those check tasks.

**Organization**: User stories from spec.md (US1–US4). Cite Espo:
https://github.com/espocrm/documentation/blob/master/docs/administration/extensions.md
https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/scopes.md
https://github.com/espocrm/documentation/blob/master/docs/development/modules.md
https://github.com/espocrm/documentation/blob/master/docs/development/view.md

**Complexity**: Each task lists `[C#]` (1–10) and a proposed model. Proposals are not a launch order. Implement on Auto unless the owner names Launch/Replace.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: User story label (US1–US4) on story-phase tasks only

## Path Conventions

Espo module trees at repository root: `custom/Espo/Modules/`, `client/custom/modules/`, `bin/`, `tests/`. Local PHP via DDEV only.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm active feature artifacts and ignore files before destructive WF work.

- [x] T001 Confirm `specs/003-google-standalone/{spec,plan,research,data-model,quickstart}.md` and `contracts/` exist; FEATURE_DIR is this folder
- [x] T002 Verify `.gitignore` covers PHP/Espo runtime (`*.log`, `.env*`, `data/cache/`, `vendor/` if untracked); do not add ignore rules that untrack committed Espo `vendor/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Uninstall WorkflowEngine on local DDEV and remove it from this product so later stories cannot depend on it. **⚠️ CRITICAL**: Complete before US1–US4 implementation that assumes WF is gone.

**Independent Test**: `test ! -d custom/Espo/Modules/WorkflowEngine`; Administration extensions list has no WorkflowEngine after rebuild.

- [x] T003 List then uninstall WorkflowEngine on local DDEV if registered (`ddev exec php command.php extension -l`, then `ddev exec php command.php extension -u --name="WorkflowEngine"` per Espo CLI). Do **not** uninstall on production. [C4] Auto
- [x] T004 Delete `custom/Espo/Modules/WorkflowEngine/` and `client/custom/modules/workflow-engine/` from this git root [C3] Auto
- [x] T005 [P] Delete `tests/unit/Espo/Modules/WorkflowEngine/` and `tests/integration/Espo/Modules/WorkflowEngine/` [C3] Auto
- [x] T006 [P] Delete `bin/smoke-workflow-engine.php`, `bin/packaging/WorkflowEngine-zip-AfterInstall.php`, `bin/build-workflow-engine.sh` [C3] Auto
- [x] T007 Remove WorkflowEngine from `custom/Espo/Modules/NonprofitEspocrm/AfterInstall.php` sibling list and `scripts/AfterInstall.php` (comment + class list). Keep `class_exists` soft-detect in `tests/integration/Espo/Support/SafehouseBaseTestCase.php` [C3] Auto
- [x] T008 Strip WorkflowEngine from `bin/build.sh` copy/echo lines, `tests/integration/Espo/Core/CoreCompatibilityTest.php` (EXPECTED_MODULES + WorkflowDefinition scopes/ORM), and WorkflowEngine rows in `phpstan-baseline.neon` [C4] Auto
- [x] T009 [P] Stop documenting this product as shipping WorkflowEngine in `deploy/DEPLOY.md` and `docs/template-variables-ui-extension.md` [C2] Auto

**Checkpoint**: Repo and local instance have no WorkflowEngine module tree; installers do not call it.

---

## Phase 3: User Story 1 - Google calendar on a plain Espo (Priority: P1) 🎯 MVP

**Goal**: Google placeholder UI loads using only `google-integration:` / Espo core AMD. No `nonprofit-espocrm:` require.

**Independent Test**: `rg "nonprofit-espocrm:" client/custom/modules/google-integration` is empty; Google description/per-date screens use `google-integration:lib/template-variable-inserter`.

### Implementation for User Story 1

- [x] T010 [US1] Copy `client/custom/modules/nonprofit-espocrm/src/lib/template-variable-inserter.js` to `client/custom/modules/google-integration/src/lib/template-variable-inserter.js`; AMD id `google-integration:lib/template-variable-inserter`; CSS class `google-template-variable-inserter`; empty-hint fallback `CalendarDateSource` / `googleCalendarSelectTargetEntityFirst`; drop WorkflowDefinition i18n [C5] Auto — AMD prefix must match module kebab name
- [x] T011 [US1] Point `client/custom/modules/google-integration/src/views/fields/google-calendar-description-template.js` at the Google inserter; remove leftover `.nonprofit-template-variable-inserter` selectors [C4] Auto
- [x] T012 [US1] Point `client/custom/modules/google-integration/src/views/fields/google-calendar-opportunity-event-settings.js` at the Google inserter [C4] Auto
- [x] T013 [P] [US1] Update deprecation comment in `client/custom/modules/google-integration/src/lib/google-calendar-variable-panel.js` so it no longer tells Google UI to require nonprofit AMD [C2] Auto

**Checkpoint**: Google client tree has zero `nonprofit-espocrm:` AMD requires.

---

## Phase 4: User Story 2 - Safehouse keeps Google after WorkflowEngine is gone (Priority: P1)

**Goal**: Nonprofit placeholder screens still work without WorkflowDefinition i18n; Google screens still work on the suite.

**Independent Test**: Nonprofit `template-text` inserter renders; empty hint is a Nonprofit Global message, not WorkflowDefinition.

### Implementation for User Story 2

- [x] T014 [US2] Add `messages.selectTargetEntityTypeFirst` to Nonprofit `Resources/i18n/{en_US,it_IT,ru_RU}/Global.json` [C3] Auto
- [x] T015 [US2] Point `client/custom/modules/nonprofit-espocrm/src/views/fields/template-text.js` emptyHint at Global; update header comment on `client/custom/modules/nonprofit-espocrm/src/lib/template-variable-inserter.js` (Nonprofit-owned, not shared with Google/WF); fallback translate must not use WorkflowDefinition [C4] Auto

**Checkpoint**: Nonprofit inserter does not mention WorkflowEngine/WorkflowDefinition.

---

## Phase 5: User Story 3 - Calendar export follows any entity Espo knows (Priority: P1)

**Goal**: Google attach catalog is live metadata `scopes.{Type}.entity === true` plus CalendarDateSource; seeds skip missing types; pull preference list is intersected with metadata.

**Independent Test**: AdditionalBuilder has no closed CORE union that ignores scopes; installer seeds already skip missing scopes; PULL skips missing types.

### Implementation for User Story 3

- [x] T016 [US3] In `custom/Espo/Modules/GoogleIntegration/Core/Utils/Metadata/AdditionalBuilder/GoogleCalendarIntegrationTemplateFields.php` drop `CORE_ENTITY_TYPES` as a closed catalog; collect types from `DateSourceEntityTypesReader` filtered by `$data->scopes->{Type}->entity === true` (same pattern as `GoogleCalendarCapableFields.php`) [C6] Auto — AdditionalBuilder only sees `$data`
- [x] T017 [US3] In `custom/Espo/Modules/GoogleIntegration/Tools/Calendar/CalendarSyncRunner.php` inject `Espo\Core\Utils\Metadata` and intersect `PULL_ENTITY_TYPES` with `scopes.{Type}.entity === true`; skip missing types without fatal [C6] Auto
- [x] T018 [P] [US3] Confirm `custom/Espo/Modules/GoogleIntegration/Tools/Installer.php` `isSupportedDateSource` already skips missing scopes; add a one-line comment that seeds are optional and metadata-gated (FR-007). Do not add new hardcoded entity names [C3] Auto
- [x] T019 [P] [US3] Add `tests/unit/Espo/Modules/GoogleIntegration/GoogleCalendarIntegrationTemplateFieldsTest.php` proving missing Opportunity scope is skipped and a live date-source type is kept [C5] Auto

**Checkpoint**: Catalog law F-EXT-UNIVERSAL holds in GI PHP.

---

## Phase 6: User Story 4 - Checks tell the truth (Priority: P2)

**Goal**: Smoke fails if Google UI still requires nonprofit AMD, if WF trees remain, or if CORE_ENTITY_TYPES is a closed-only catalog.

**Independent Test**: `ddev exec php bin/smoke-google-integration.php` PASS after the rewrite; a reviewer can see coupling assertions as failures.

### Tests for User Story 4

- [x] T020 [US4] Rewrite coupling assertions in `bin/smoke-google-integration.php` (~variable picker / inserter block): **FAIL** on `nonprofit-espocrm:` inside `client/custom/modules/google-integration/`; **PASS** on `google-integration:lib/template-variable-inserter`; **FAIL** if `custom/Espo/Modules/WorkflowEngine` exists; **FAIL** if TemplateFields still defines `CORE_ENTITY_TYPES`; assert PULL uses Metadata scopes. Keep `recordUrl` check against the **Google** inserter path [C5] Auto

**Checkpoint**: Smoke encodes US1–US3.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Rebuild, test, owner UAT artifact, progress log.

- [x] T021 `ddev exec php command.php rebuild` after metadata/client changes [C3] Auto
- [x] T022 Run DDEV PHPUnit unit suite for GoogleIntegration (and full unit if fast) plus Google smoke; keep SafehouseBaseTestCase WF `class_exists` (no-op after delete) [C5] Auto
- [x] T023 [P] Write English `specs/003-google-standalone/checklists/owner-user-tests.md`; append `.specify/progress/015-google-standalone-implement.md` [C3] Auto
- [x] T024 Confirm production WF uninstall and git push were **not** done [C1] Auto

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Immediate
- **Foundational (Phase 2)**: Depends on Setup; BLOCKS story work that assumes WF is gone
- **US1 (Phase 3)**: After Phase 2 (AMD copy does not need WF deleted, but product goal does)
- **US2 (Phase 4)**: After Phase 2 (Nonprofit i18n replaces WF strings)
- **US3 (Phase 5)**: Independent of US1 files; can parallel US1 after Phase 2
- **US4 (Phase 6)**: After US1 inserter path and US3 catalog constants exist
- **Polish**: After US1–US4

### User Story Dependencies

- **User Story 1 (P1)**: After Foundational — Google AMD only
- **User Story 2 (P1)**: After Foundational — Nonprofit i18n; does not need US1 complete to keep nonprofit screens, but suite UX needs both
- **User Story 3 (P1)**: After Foundational — PHP catalog; parallel with US1
- **User Story 4 (P2)**: After US1 + US3 smoke targets exist

### Parallel Opportunities

- T005 / T006 / T009 after T004 starts (deletes in different trees)
- T011 / T012 after T010
- T014 parallel with US1
- T016 / T017 / T018 after Phase 2
- T023 parallel with T021/T022 notes

### Parallel Example: User Story 1

```bash
# After T010 exists:
Task: "Point google-calendar-description-template.js at Google inserter"
Task: "Point google-calendar-opportunity-event-settings.js at Google inserter"
Task: "Update google-calendar-variable-panel.js deprecation comment"
```

---

## Parallel Example: User Story 3

```bash
Task: "Filter TemplateFields by scopes.entity"
Task: "Intersect CalendarSyncRunner PULL with Metadata"
Task: "Comment installer seed gating"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1–2 (WF uninstall + delete)
2. Phase 3 Google-owned inserter
3. STOP: `rg nonprofit-espocrm: client/custom/modules/google-integration` empty

### Incremental Delivery

1. Setup + Foundational → WF gone locally
2. US1 → Google ZIP would load on stock Espo UI
3. US2 → Safehouse nonprofit screens keep placeholders
4. US3 → metadata catalog
5. US4 → smoke tells the truth
6. Polish → rebuild, PHPUnit, owner UAT handshake (no prod)

### Parallel Team Strategy

One agent: sequential T003→T008, then T010–T013 with T016–T019 in parallel, then T020–T024.

---

## Notes

- [P] = different files, no incomplete-task dependency
- Do not modify `specs/003-google-standalone/checklists/requirements.md` markers
- Do not `git commit` / `git push` unless the owner asks
- Do not uninstall WorkflowEngine on production
- When the owner later brings a refactored WorkflowEngine from another instance, treat it as a new specify — not a restore of this tree
- Suggested models in this file are proposals, never a launch order

## Complexity owner table (constitution XV)

| ID | Work | C | Proposed model | Why |
|----|------|---|----------------|-----|
| T001 | Confirm artifacts | 1 | Auto | Mechanical |
| T002 | Ignore files | 2 | Auto | `.gitignore` already complete |
| T003 | DDEV uninstall WF | 4 | Auto | Documented Espo CLI |
| T004 | Delete WF module trees | 3 | Auto | `rm` |
| T005 | Delete WF tests | 3 | Auto | `rm` |
| T006 | Delete WF smoke/packaging | 3 | Auto | `rm` |
| T007 | AfterInstall sibling list | 3 | Auto | Small PHP |
| T008 | build.sh / CoreCompatibility / phpstan | 4 | Auto | Grep-driven |
| T009 | Deploy/docs copy | 2 | Auto | Docs |
| T010 | Google inserter copy | 5 | Auto | AMD prefix rules known |
| T011 | Description template view | 4 | Auto | One require line |
| T012 | Per-date settings view | 4 | Auto | One require line |
| T013 | Variable panel comment | 2 | Auto | Comment |
| T014 | Nonprofit i18n | 3 | Auto | Three JSON files |
| T015 | Nonprofit inserter/template-text | 4 | Auto | Drop WF i18n |
| T016 | TemplateFields catalog | 6 | Auto | Mirror CapableFields |
| T017 | PULL ∩ metadata | 6 | Auto | Constructor DI |
| T018 | Installer seed comment | 3 | Auto | Already gated |
| T019 | TemplateFields unit test | 5 | Auto | stdClass metadata stub |
| T020 | Smoke rewrite | 5 | Auto | Assert invert |
| T021 | DDEV rebuild | 3 | Auto | Constitution X |
| T022 | PHPUnit + smoke | 5 | Auto | DDEV |
| T023 | Owner UAT + progress 015 | 3 | Auto | Constitution XVII |
| T024 | No prod / no push | 1 | Auto | Gate |
