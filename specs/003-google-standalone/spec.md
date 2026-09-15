# Feature Specification: Standalone Google calendar; retire WorkflowEngine

**Feature Branch**: `003-google-standalone`

**Created**: 2026-09-15

**Status**: Draft

**Input**: Owner changed course after `003-decouple-google-client`: do **not**
split WorkflowEngine (already being refactored in another project). Remove
WorkflowEngine from this nonprofit product (uninstall from the instance is
enough so it need not be decoupled). Keep Google calendar and make it work
**without** WorkflowEngine and **without** the nonprofit CRM, including on a
separate Espo. Every extension must discover entity types from Espo metadata
and stay autonomous.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Google calendar on a plain Espo (Priority: P1)

An administrator installs only the Google calendar extension on stock Espo
(no nonprofit CRM, no WorkflowEngine). They connect Google, open calendar
template / per-date event text, insert placeholders, and save a record that
exports to Google Calendar. Screens load. No missing-extension errors.

**Why this priority**: This is the missing standalone guarantee. Today Google
calendar screens can fail if the nonprofit CRM is absent.

**Independent Test**: Espo with Google calendar enabled and both nonprofit CRM
and WorkflowEngine absent; open the Google placeholder screens; insert one
token; save a record that is set to export.

**Acceptance Scenarios**:

1. **Given** Espo with Google calendar only, **When** a permitted user opens
   Google calendar description/template text in edit mode, **Then** the
   screen loads and they can insert a placeholder within two minutes.
2. **Given** the same instance, **When** they open per-date Google event
   settings that also offer placeholders, **Then** that screen loads and
   insert works.
3. **Given** those screens, **When** a reviewer looks for a hard requirement
   on the nonprofit CRM or WorkflowEngine, **Then** they find none.

---

### User Story 2 - Safehouse keeps Google after WorkflowEngine is gone (Priority: P1)

Staff on the Safehouse CRM (nonprofit CRM + Google calendar, **no**
WorkflowEngine) keep using Google calendar templates and export exactly as
today. WorkflowEngine menus, entities, and jobs are gone. Nothing in Google
calendar or the nonprofit CRM asks for WorkflowEngine.

**Why this priority**: Owner confirmed nothing in this product depends on
WorkflowEngine; keeping it would force a pointless split.

**Independent Test**: Local DDEV after WorkflowEngine is uninstalled/removed;
Google calendar screens and a known export path still work; WorkflowEngine
is not in Administration extensions / entity list.

**Acceptance Scenarios**:

1. **Given** WorkflowEngine removed from this product, **When** staff edit
   Google calendar templates on Safehouse, **Then** the placeholder helper
   still appears and inserts the same kind of tokens.
2. **Given** the same instance, **When** staff use nonprofit-only placeholder
   screens, **Then** those still work (this feature must not break them).
3. **Given** a user who previously opened WorkflowEngine, **When** they look
   for it after this change, **Then** they cannot open those screens (module
   not installed).

---

### User Story 3 - Calendar export follows any entity Espo knows (Priority: P1)

An administrator wants Google Calendar export on an entity type this
extension’s authors never listed (core or custom). They configure a date
source for that type (a real date/datetime field on that entity). After
rebuild, the Google calendar panel appears on that entity. Export uses that
record’s fields as placeholders. No developer edits a frozen list of
Meeting/Call/Task/Opportunity.

**Why this priority**: Owner law: extensions are universal; Espo already
exposes the catalog in metadata.

**Independent Test**: Add (or pick) an entity type that is not in the old
four-name seed; configure an active date source; rebuild; the Google panel
is on that entity’s detail; placeholders include that entity’s fields.

**Acceptance Scenarios**:

1. **Given** an entity type that exists in Espo metadata and has a date
   field, **When** an admin activates a date source for it and rebuilds,
   **Then** Google calendar controls appear on that entity without a code
   change to the Google extension.
2. **Given** a seed type (for example Meeting) is **missing** on an instance,
   **When** Google calendar starts, **Then** it skips that seed and does not
   crash.
3. **Given** a new custom entity appears later, **When** it has the needed
   date field and a date source, **Then** Google calendar can target it
   without waiting for a new Google-extension release that names it.

---

### User Story 4 - Checks tell the truth (Priority: P2)

Automated checks MUST fail if Google calendar UI still requires the
nonprofit CRM, if Google calendar requires WorkflowEngine, or if Google
calendar only supports a hardcoded closed list of entity types.

**Why this priority**: An existing smoke currently treats nonprofit coupling
as success.

**Independent Test**: Run Google calendar checks on DDEV; they pass only
when US1–US3 hold.

**Acceptance Scenarios**:

1. **Given** Google UI still required nonprofit CRM screens, **Then** the
   check fails.
2. **Given** Google calendar is self-contained and entity discovery is
   metadata-based, **Then** the check passes.

---

### Edge Cases

- Nonprofit CRM present, Google calendar present, WorkflowEngine absent:
  US2.
- Google calendar present, nonprofit CRM absent: US1.
- Both Google calendar and nonprofit CRM absent: nothing to test for this
  feature.
- Date source points at a deleted/disabled entity type: skip; do not fatal.
- Pull from Google into CRM activity types: only types that exist on this
  instance; skip missing ones.
- Stale browser cache after rebuild: hard refresh is enough.
- Production uninstall of WorkflowEngine is approval-gated even if local
  DDEV uninstall is in this feature.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Google calendar placeholder screens MUST load and allow
  placeholder insertion when the nonprofit CRM extension is not installed.
- **FR-002**: Those screens MUST NOT require any screen or helper that only
  exists inside the nonprofit CRM extension or WorkflowEngine.
- **FR-003**: On Safehouse (nonprofit CRM + Google calendar), Google
  calendar placeholder insertion MUST remain available with equivalent
  tokens.
- **FR-004**: Nonprofit CRM placeholder screens MUST keep working with or
  without Google calendar.
- **FR-005**: WorkflowEngine MUST be removed from this product: not
  orchestrated on install, not offered in the UI, not required by Google
  calendar or nonprofit CRM. Local instance MUST uninstall/remove it so
  rsync cannot bring it back on the next deploy (source not shipped).
- **FR-006**: Google calendar MUST discover which entity types it can attach
  to from Espo metadata (live entity scopes and field definitions), not
  from a closed coded list as the only catalog.
- **FR-007**: Optional convenience seeds for well-known types MAY exist, but
  MUST apply only when that entity type exists on the instance.
- **FR-008**: An admin MUST be able to target an entity type the Google
  extension did not name in advance, when that type exists and has a
  suitable date field, by configuring a date source (no Google-extension
  code change).
- **FR-009**: Automated Google calendar checks MUST fail on remaining
  nonprofit-CRM UI coupling, WorkflowEngine coupling, or a closed-only
  entity catalog.
- **FR-010**: This feature MUST NOT change Google OAuth, Drive, or the
  meaning of existing calendar templates except as needed for FR-001–FR-008.
- **FR-011**: Local verification MUST use DDEV. Production uninstall of
  WorkflowEngine and production deploy remain approval-gated.

### Key Entities

- **Calendar date source**: Admin-configured mapping: which entity type and
  date field feed Google Calendar export. Already exists; becomes the
  operator path for unknown types.
- **Calendar template**: Reusable Google event text/settings per target
  entity type. Already exists.
- **Google-capable record**: Any Espo entity type that is a live entity in
  metadata and has an active date source.
- **WorkflowEngine definitions** (retired on this product): workflow
  records/jobs MUST stop existing on this instance after removal.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On Espo without nonprofit CRM and without WorkflowEngine, a
  permitted user can open each in-scope Google calendar text screen and
  insert one placeholder in under two minutes, with no missing-extension
  error.
- **SC-002**: On Safehouse local DDEV after WorkflowEngine is gone, the same
  Google screens still show the placeholder helper; staff need no new
  instructions.
- **SC-003**: A reviewer finds **zero** hard dependencies from Google
  calendar screens/jobs on nonprofit CRM or WorkflowEngine.
- **SC-004**: After configuring a date source for an entity type that was
  not in the old four-name seed (and rebuilding), that entity’s detail
  shows Google calendar controls and placeholders from **that** type’s
  fields.
- **SC-005**: WorkflowEngine is not installed on the local product instance
  (no workflow admin screens).
- **SC-006**: Google calendar automated checks pass on DDEV for US1–US4.

## Assumptions

- Owner 2026-09-15: WorkflowEngine is unused here and is being refactored
  elsewhere; do not decouple it — remove it from this product instead.
- Previous draft `003-decouple-google-client` is superseded by this spec
  (same sequential slot **003**, new name).
- F-002 (WorkflowEngine modal split) is **cancelled** for this repo.
- F-016 remains deferred.
- Placeholder UX stays insert-token (Segnaposti-style). No new designer.
- BugTracker and Aurora themes stay in this product.
- Production apply of uninstall/rsync still needs an explicit owner push
  and, if needed, a separate prod uninstall approval.

## Documentation Citations *(constitution IV)*

| Topic | Cite (GitHub) | Why this choice |
|-------|---------------|-----------------|
| Module layout / frontend prefix | https://github.com/espocrm/documentation/blob/master/docs/development/modules.md | UI lives in the owning module |
| Views | https://github.com/espocrm/documentation/blob/master/docs/development/view.md | Client views resolve from the module they belong to |
| Metadata / scopes | https://github.com/espocrm/documentation/blob/master/docs/development/metadata.md , https://github.com/espocrm/documentation/blob/master/docs/development/metadata/scopes.md | Entity catalog is `scopes.*.entity` |
| Additional builders | https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-metadata.md | Conditional metadata per live entity types |
| Extension packages | https://github.com/espocrm/documentation/blob/master/docs/development/extension-packages.md | Install/uninstall is the official lifecycle |
| Extensions admin | https://github.com/espocrm/documentation/blob/master/docs/administration/extensions.md | Uninstall WorkflowEngine from the instance |
