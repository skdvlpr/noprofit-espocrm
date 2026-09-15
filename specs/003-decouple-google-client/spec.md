# Feature Specification: Decouple Google calendar client from nonprofit CRM

**Feature Branch**: `003-decouple-google-client`

**Created**: 2026-09-15

**Status**: Draft

**Input**: Owner accepted audit `001` (SC-006) and ordered remediations by
backlog rank. Rank 1 / F-001: Google calendar extension screens must not
require the nonprofit CRM extension. WorkflowEngine (F-002) is the next
item after this feature, not this one.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Google calendar templates work without the nonprofit CRM (Priority: P1)

An administrator installs only the Google calendar extension on a stock Espo
instance (no Safehouse nonprofit CRM). They open calendar template / event
text screens and insert the usual placeholders (the same “Segnaposti-style”
helper staff already use on Safehouse). The screens load. Placeholder
insertion works. Nothing asks them to install the nonprofit CRM.

**Why this priority**: This is the High finding. Today those screens fail
when the nonprofit extension is absent, so the Google package is not a true
standalone extension.

**Independent Test**: On an Espo with Google calendar enabled and nonprofit
CRM **disabled or absent**, open the two Google calendar text screens that
offer placeholders; both render in edit mode and accept a placeholder insert
within two minutes.

**Acceptance Scenarios**:

1. **Given** Espo with the Google calendar extension and without the
   nonprofit CRM extension, **When** a permitted user opens a Google calendar
   description/template text field in edit mode, **Then** the screen loads
   fully (no missing-module failure) and they can insert a placeholder into
   the text.
2. **Given** the same instance, **When** they open the Google calendar
   opportunity-event settings text that also offers placeholders, **Then**
   that screen behaves the same way (loads; insert works).
3. **Given** those screens, **When** a reviewer looks for a hard requirement
   on the nonprofit CRM extension for those screens, **Then** they find none.

---

### User Story 2 - Safehouse combined install does not regress (Priority: P1)

Staff on the live Safehouse CRM (nonprofit CRM **and** Google calendar both
present) keep editing calendar templates exactly as today: Italian UI,
placeholder helper, no extra training.

**Why this priority**: Production already uses both extensions together.
Standalone purity must not break the combined product.

**Independent Test**: On the project’s local DDEV instance with both
extensions enabled, repeat the same two screens; behaviour matches today’s
placeholder helper.

**Acceptance Scenarios**:

1. **Given** both extensions enabled, **When** staff edit a Google calendar
   template text field, **Then** the placeholder helper still appears and
   inserts the same kind of tokens they use now.
2. **Given** both extensions enabled, **When** staff use nonprofit-only
   screens that also have a placeholder helper (for example donation/email
   template text), **Then** those screens still work (this feature must not
   steal or break the nonprofit helper).

---

### User Story 3 - Package checks tell the truth (Priority: P2)

Release/smoke checks for the Google calendar extension must not treat
“depends on nonprofit CRM screens” as success. After this feature, a check
that the Google UI still points at nonprofit CRM screens MUST fail.

**Why this priority**: A previous smoke currently encodes the coupling as
expected; leaving that would hide a regression.

**Independent Test**: Run the Google-integration smoke (DDEV); it passes
only if Google calendar UI does not require nonprofit CRM screens.

**Acceptance Scenarios**:

1. **Given** the updated checks, **When** Google calendar UI still required
   nonprofit CRM screens, **Then** the check fails.
2. **Given** the updated checks, **When** Google calendar UI is
   self-contained, **Then** the check passes.

---

### Edge Cases

- Nonprofit CRM installed, Google calendar not: nonprofit placeholder
  screens stay available; no Google screens to open.
- Google calendar installed, nonprofit CRM not: User Story 1.
- Both installed: User Story 2; no double helpers stacked on one field.
- After rebuild/cache clear, the same screens still load (stale client
  cache is an operator refresh, not a product defect if a hard refresh
  works).
- WorkflowEngine email modal still coupled to nonprofit CRM (F-002) —
  **unchanged** and out of scope.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Google calendar placeholder screens MUST load and allow
  placeholder insertion when the nonprofit CRM extension is not installed.
- **FR-002**: Those Google screens MUST NOT require any screen or helper
  that only exists inside the nonprofit CRM extension.
- **FR-003**: On an instance with both extensions, Google calendar
  placeholder insertion MUST remain available with equivalent tokens and
  no extra staff training.
- **FR-004**: Nonprofit CRM placeholder screens that already exist MUST
  keep working when Google calendar is absent or present.
- **FR-005**: Automated Google calendar extension checks MUST fail if
  Google screens still require the nonprofit CRM extension, and MUST pass
  when they do not.
- **FR-006**: This feature MUST NOT change WorkflowEngine email composition
  (that is backlog F-002).
- **FR-007**: This feature MUST NOT change Google calendar backend sync,
  OAuth, or entity data shapes except as required to keep the same
  placeholder tokens on the Google screens.
- **FR-008**: Local verification MUST use DDEV; production apply remains
  approval-gated.

### Key Entities

- **Calendar template text**: Existing Google calendar template body/title
  fields where staff insert placeholders.
- **Opportunity event settings text**: Existing Google calendar field for
  opportunity-related event text with the same helper.
- **Nonprofit template text**: Existing nonprofit CRM fields that use a
  placeholder helper (must keep working; not redesigned here).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On Espo without the nonprofit CRM extension, a permitted user
  can open each in-scope Google calendar text screen and insert one
  placeholder in under two minutes, with no missing-extension error.
- **SC-002**: On the combined Safehouse local instance, the same two Google
  screens still show the placeholder helper; a reviewer who used them
  before this change needs no new instructions.
- **SC-003**: A reviewer finds **zero** remaining hard dependencies from
  those Google screens on the nonprofit CRM extension.
- **SC-004**: Google calendar automated checks pass on DDEV; a deliberately
  reintroduced nonprofit-CRM dependency on those screens would fail the
  check.

## Assumptions

- Owner accepted audit `001` on 2026-09-15 and chose backlog order; this
  spec is **only** F-001.
- Safehouse production currently runs both extensions; stock-Espo
  standalone is the missing guarantee, not a new product.
- Placeholder UX stays “insert `{{field}}`-style tokens” (current
  Segnaposti helper). No new visual designer.
- Google calendar ZIP/rsync packaging stays the existing channel; this
  spec does not change how production is deployed.
- F-016 (PrimaNota Role field matrix) remains deferred by the owner.

## Documentation Citations *(constitution IV)*

| Topic | Cite (GitHub) | Why this choice |
|-------|---------------|-----------------|
| Module layout / frontend prefix | https://github.com/espocrm/documentation/blob/master/docs/development/modules.md | Custom UI lives in the owning module; `module/{name}` / `{name}:` paths |
| Views / AMD prefix | https://github.com/espocrm/documentation/blob/master/docs/development/view.md | Client views resolve from the module they belong to |
| Extension packages | https://github.com/espocrm/documentation/blob/master/docs/development/extension-packages.md | Google calendar remains an installable extension, not a core patch |
