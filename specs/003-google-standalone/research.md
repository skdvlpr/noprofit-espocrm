# Research: Standalone Google calendar; retire WorkflowEngine

**Feature**: `003-google-standalone`  
**Date**: 2026-09-15

## Docs freshness

- **Decision**: Read constitution Read-root clone this turn; cite GitHub
  blob/master in artifacts.
- **Evidence**: Opened `docs/development/modules.md`, `view.md`,
  `metadata.md`, `metadata/scopes.md`, `metadata/app-metadata.md`,
  `extension-packages.md`, `administration/extensions.md`.
- **Alternatives**: `docs.espocrm.com` as cite — forbidden (constitution I).

## R1 — Remove WorkflowEngine from this product (not split)

- **Decision**: Uninstall from the local instance and **delete** the module
  from this git root (backend, client, tests, smokes, packaging AfterInstall,
  Nonprofit sibling installer entry). Do not implement F-002.
- **Rationale**: Owner: unused here; refactor lives in another project;
  uninstall means no decoupling. Rsync deploys the tree — leaving source
  would reinstall on next `main` push.
- **Citations**: https://github.com/espocrm/documentation/blob/master/docs/administration/extensions.md
  ; https://github.com/espocrm/documentation/blob/master/docs/development/extension-packages.md
- **Alternatives**: Keep source, only `class_exists` skip — rejected (rsync).
  Decouple WF email modal (old F-002) — rejected by owner.

## R2 — Google client owns the placeholder helper

- **Decision**: Stop AMD-requiring
  `nonprofit-espocrm:lib/template-variable-inserter` from
  `google-calendar-description-template.js` and
  `google-calendar-opportunity-event-settings.js`. Put an equivalent helper
  under `google-integration:` (copy then trim Google-specific bits). Nonprofit
  keeps its own inserter for template-text.
- **Rationale**: Constitution F-EXT-UNIVERSAL / modules.md: frontend lives in
  the owning module. Combined install: two copies OK; MUST NOT stack two
  helpers on one field.
- **Citations**: https://github.com/espocrm/documentation/blob/master/docs/development/modules.md
  ; https://github.com/espocrm/documentation/blob/master/docs/development/view.md
- **Alternatives**: Shared third module — extra ZIP, rejected. Keep require +
  document hard depend — violates US1.

## R3 — Entity catalog from Metadata, not a closed PHP list

- **Decision**: Capable types = live `scopes.{Type}.entity === true` that
  either (a) have an **active** CalendarDateSource row, or (b) are being
  seeded because the scope exists. `GoogleCalendarIntegrationTemplateFields`
  MUST NOT treat `CORE_ENTITY_TYPES` as mandatory. `CalendarDateSourceDefaults`
  / installer default templates: insert only when that scope exists.
  `CalendarSyncRunner::PULL_ENTITY_TYPES`: intersect with metadata (skip
  missing Meeting/Call/Task). Date-field pickers already should read
  `entityDefs` date/datetime fields for the chosen type.
- **Rationale**: Owner + gm-edu Native First (minus LMS):
  `$metadata->get(['scopes', …, 'entity'])` and entityDefs. Additional
  builders are the official hook for conditional metadata
  (`additionalBuilderClassNameList`, Espo ≥8.4).
- **Citations**: https://github.com/espocrm/documentation/blob/master/docs/development/metadata.md
  ; https://github.com/espocrm/documentation/blob/master/docs/development/metadata/scopes.md
  ; https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-metadata.md
- **Alternatives**: Keep four-name seed as the only attachable types —
  rejected (US3). Scan every entity on every HTTP request — rejected
  (builders run at rebuild/metadata compile).

## R4 — Layout owner without requiring Nonprofit

- **Decision**: Keep soft detection: if a NonprofitEspocrm `layouts/{Type}/detail.json`
  is readable, `app.layouts.{Type}.detail.module` may stay NonprofitEspocrm
  (suite). If not, GoogleIntegration. Google MUST still inject the Google
  Calendar panel via AdditionalBuilder when Nonprofit is absent (already the
  fallback). MUST NOT `require` Nonprofit classes.
- **Rationale**: Espo layout module override is how vertical CRM and GI share
  detail layouts on the suite; standalone GI must own the layout files it
  ships.
- **Citations**: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md
- **Alternatives**: Always force module=GoogleIntegration even on suite —
  can hide Nonprofit detail customizations; rejected.

## R5 — Checks and tests

- **Decision**: Rewrite `bin/smoke-google-integration.php` so a remaining
  `nonprofit-espocrm:` require in GI client is a **fail**. Remove WF smokes
  from this repo’s default story. `SafehouseBaseTestCase` already gates WF
  installer with `class_exists` — after delete, the branch is simply unused.
- **Rationale**: Spec US4 / FR-009.
- **Alternatives**: Leave smoke as documentation of the old coupling —
  rejected.

## R6 — Local uninstall vs prod

- **Decision**: Implement uninstalls WF on **DDEV** (Espo extension uninstall
  if registered, then delete files). Production: same steps only when the
  owner approves deploy/uninstall for that host.
- **Rationale**: Constitution VII / XVIII.
- **Alternatives**: Prod SSH in the same implement session — not unless asked.
