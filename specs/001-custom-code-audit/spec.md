# Feature Specification: Custom Code Compliance Audit

**Feature Branch**: `001-custom-code-audit`

**Created**: 2026-08-31

**Status**: Draft

**Input**: User description: "Custom code compliance audit — full review of custom modules vs official EspoCRM documentation: security holes, performance, anti-patterns, extension boundaries, secrets not in App Secrets, coupling. Produce a prioritized change proposal (first major engineering spec after SDD constitution)."

## Clarifications

### Session 2026-08-31

- Q: Is local PHP runtime optional (host PHP) or must all local PHP/Espo work use DDEV, and what serves production? → A: Local PHP (and similar app stacks) MUST use DDEV only — not optional. Production runs on Caddy with automatic TLS/SSL (`crm.safehouse.community`). Prod probes still require explicit approval.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Maintainers get a prioritized compliance backlog (Priority: P1)

As a project owner / technical maintainer, I need a single ordered list of
compliance findings across all Safehouse custom extensions so I know what to
fix first (especially security and production-risk items) without rereading the
whole codebase.

**Why this priority**: Without a ranked backlog, remediation stays ad hoc and
production risk stays opaque after go-live.

**Independent Test**: Deliver one backlog document that a second reviewer can
use to pick the next remediation slice without opening the full tree.

**Acceptance Scenarios**:

1. **Given** the five custom modules are in scope, **When** the audit completes,
   **Then** every in-scope module appears in the report with a clear
   pass / findings / not-applicable summary.
2. **Given** findings exist, **When** the owner opens the backlog, **Then** each
   finding has severity, plain-language impact, cited official expectation, and
   a suggested remediation direction (not necessarily code yet).
3. **Given** the backlog is ordered, **When** the owner picks the top item,
   **Then** it is suitable to become the next Spec Kit feature without
   re-scoping the whole audit.

---

### User Story 2 - Security and secrets exposure is explicit (Priority: P1)

As a security-conscious operator of a live CRM with personal data, I need
explicit confirmation of where secrets and sensitive personal-data handling
diverge from official least-privilege and secrets-store guidance, including
leak paths (repo, logs, packaging, scripts).

**Why this priority**: Production already holds real donor/member data; secret
and PII mistakes are higher cost than UI debt.

**Independent Test**: A dedicated “Security & secrets” section can be reviewed
alone and lists every suspected secret location and PII/ACL concern found in
scope (or states none found after named checks).

**Acceptance Scenarios**:

1. **Given** credentials may live outside the admin secrets store, **When** the
   audit finishes, **Then** each such location is listed with risk and a
   migration recommendation toward the official secrets store.
2. **Given** Roles and field-level access exist, **When** sensitive person
   fields are reviewed, **Then** gaps vs least-privilege expectations are
   listed (or confirmed compliant for the sampled entities).
3. **Given** packaging and automation exist, **When** leak paths are reviewed,
   **Then** the report states whether extension packages, CI output patterns,
   or maintenance scripts are likely to expose secrets.

---

### User Story 3 - Extension boundaries and coupling are documented (Priority: P2)

As someone who may install Google / Workflow / themes on a stock Espo instance,
I need to know which extensions are safely standalone vs which silently depend
on the nonprofit vertical module.

**Why this priority**: Constitution requires installable Espo 10+ extensions
usable beyond this fork; unknown coupling blocks safe reuse and upgrades.

**Independent Test**: An “Extension map” section states for each module:
standalone-safe / Soft-depends / Hard-depends, with evidence examples.

**Acceptance Scenarios**:

1. **Given** multiple extensions share one product, **When** coupling is
   reviewed, **Then** each cross-module dependency is named and classified.
2. **Given** a module claims stock-Espo usefulness, **When** nonprofit-only
   entities or layouts are referenced, **Then** the report flags detection gaps
   or hard-coded nonprofit assumptions.

---

### User Story 4 - Performance and anti-pattern risks are ranked for later specs (Priority: P3)

As a maintainer planning later hygiene work, I need performance and design
anti-pattern findings separated from security so they can feed tests/`bin`
hygiene and CI specs without blocking P0 fixes.

**Why this priority**: Important for long-term health, but secondary to secrets
and ACL on a live system.

**Independent Test**: Non-security findings appear in their own ranked group
and explicitly point to likely follow-on specs when relevant.

**Acceptance Scenarios**:

1. **Given** unbounded queries, N+1 list risks, or duplicated logic vs native
   features exist, **When** the audit completes, **Then** they appear with
   severity below security unless they enable data leakage.
2. **Given** findings would reshape tests/`bin` or CI work, **When** the report
   is delivered, **Then** it recommends whether to draft those sibling specs
   early (user still decides under constitution III).

---

### Edge Cases

- What if a finding needs live production inspection? Record it as blocked
  pending explicit operator approval; do not probe production (Caddy/TLS host)
  without that approval. Local PHP evidence MUST come from DDEV, not host PHP.
- What if DDEV is down or not started? Start/repair DDEV before PHP probes;
  do not fall back to non-DDEV local PHP for Espo commands.
- What if documentation and current Espo version disagree? Prefer current
  official docs for the installed major line; note version skew in the finding.
- What if a module is partially obsolete (merged/renamed historically)? Treat
  remaining tree as in-scope; note historical names only as context.
- What if volume is huge? Use full inventory + risk-based deep dives; do not
  claim line-by-line perfection—state coverage method in the report.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The audit MUST cover all custom product modules currently in the
  repository inventory: NonprofitEspocrm, GoogleIntegration, WorkflowEngine,
  BugTracker, SafehouseAuroraThemes (backend trees and matching client module
  trees where present).
- **FR-002**: The audit MUST produce a single English compliance report (or
  equivalent structured progress artifact set) with: executive summary,
  per-module summary, ranked backlog, and security/secrets section.
- **FR-003**: Each finding MUST be testable later: description, severity
  (Critical / High / Medium / Low), evidence location (path or area), official
  expectation cited, and recommended next action type (fix / migrate secret /
  rewrite / accept risk / open follow-on spec).
- **FR-004**: The audit MUST evaluate secrets handling against the official
  application secrets store guidance and flag values that appear in git,
  packages, or shared automation.
- **FR-005**: The audit MUST evaluate access-control and personal-data exposure
  risks against official Roles / ACL expectations (least privilege).
- **FR-006**: The audit MUST evaluate extension packaging and module layout
  against official modules / extension-package expectations (no core tree
  edits; correct backend/frontend placement).
- **FR-007**: The audit MUST evaluate “native-first” violations: custom
  reinvention where Entity Manager, Formula, Dynamic Logic, Roles, or hooks
  already suffice—recorded as anti-patterns with doc citations.
- **FR-008**: The audit MUST note performance risks that threaten production
  scale (unbounded loads, obvious N+1 list patterns) without requiring a full
  profiler run for v1 of this feature.
- **FR-009**: The audit MUST NOT implement mass remediations as part of this
  feature; remediations become separate Spec Kit features from the backlog
  (except user-approved emergency containment called out in the report).
- **FR-010**: The audit MUST explicitly exclude deep rewrite of `bin/` smoke
  inventory and CI workflow redesign as deliverables (those remain separate
  queued specs), while MAY list cross-references when findings depend on them.
- **FR-011**: The audit MUST record methodology and coverage limits so another
  agent can continue after context loss.
- **FR-012**: Before deep Espo API/metadata review, the auditor MUST refresh or
  confirm freshness of the local Espo documentation clone per constitution
  duty, and cite local + online sources for non-obvious judgments.
- **FR-013**: Any local PHP / Espo CLI / rebuild / metadata probe for this
  feature MUST run via **DDEV** (`ddev exec …`). Host PHP outside DDEV is
  forbidden for project work. The report MUST note that production is served
  by **Caddy with automatic SSL**; production inspection remains
  approval-gated and is not a substitute for DDEV-local verification.

### Key Entities

- **Compliance Finding**: A single ranked issue with severity, evidence, citation,
  and recommended action type.
- **Module Summary**: Pass/findings overview for one extension.
- **Remediation Backlog Item**: Ordered unit suitable to become a future Spec
  Kit feature.
- **Secret Location Candidate**: Suspected sensitive value store/path and
  recommended official store migration.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of the five named custom modules have a written module
  summary in the delivered report.
- **SC-002**: A reviewer can select the next remediation feature from the top
  of the backlog in under 15 minutes without re-auditing the tree.
- **SC-003**: Every Critical and High finding includes a cited official
  expectation (doc reference) and a plain-language business/security impact.
- **SC-004**: The Security & secrets section either lists concrete exposure
  candidates or explicitly states “none found” after the named check categories
  in FR-004/FR-005.
- **SC-005**: At least the top 10 backlog items (or all items if fewer than 10)
  are written so each can map 1:1 to a future `/speckit-specify` title.
- **SC-006**: Owner acceptance: user confirms the report is sufficient to drive
  the next remediation priority (recorded in progress handoff).
- **SC-007**: Methodology records that every local application-runtime command
  used for evidence ran in the project’s mandated local development
  environment, and that production is fronted by an automatic-TLS reverse
  proxy (not used as the local runtime).

## Assumptions

- “Compliance” means alignment with official EspoCRM documentation and this
  project’s constitution—not a legal certification audit.
- Local PHP runtime is **always DDEV** for this repo (and the user’s PHP/Laravel
  projects generally). Production is **Caddy + automatic SSL**; live prod
  checks require separate explicit approval and are not the default evidence
  path.
- Matching frontend trees under `client/custom/modules/` are in scope whenever
  the module ships UI.
- Historical Notion/`progress_old` notes are hints only; the audit re-verifies
  against the tree and current docs.
- Fixing findings is out of scope for this feature’s Done criteria; producing
  the prioritized proposal is the Done bar.
- Queued follow-ons (tests/`bin` hygiene, CI harden) stay deferred unless the
  user authorizes specify-ahead under constitution III.

## Documentation Citations *(constitution IV)*

Non-obvious audit judgments MUST cite both local and online sources, including
at minimum:

| Topic | Local | Online |
|-------|-------|--------|
| Modules layout | `~/safehouse/espocrm-documentation/docs/development/modules.md` | https://docs.espocrm.com/development/modules/ |
| Extension packages | `~/safehouse/espocrm-documentation/docs/development/extension-packages.md` | https://docs.espocrm.com/development/extension-packages/ |
| Extensions admin | `~/safehouse/espocrm-documentation/docs/administration/extensions.md` | https://docs.espocrm.com/administration/extensions/ |
| Coding practices | `~/safehouse/espocrm-documentation/docs/development/coding-practices.md` | https://docs.espocrm.com/development/coding-practices/ |
| Hooks | `~/safehouse/espocrm-documentation/docs/development/hooks.md` | https://docs.espocrm.com/development/hooks/ |
| ACL | `~/safehouse/espocrm-documentation/docs/development/acl.md` | https://docs.espocrm.com/development/acl/ |
| Roles | `~/safehouse/espocrm-documentation/docs/administration/roles-management.md` | https://docs.espocrm.com/administration/roles-management/ |
| App Secrets | `~/safehouse/espocrm-documentation/docs/administration/app-secrets.md` | https://docs.espocrm.com/administration/app-secrets/ |
| Entity Manager | `~/safehouse/espocrm-documentation/docs/administration/entity-manager.md` | https://docs.espocrm.com/administration/entity-manager/ |
| Metadata | `~/safehouse/espocrm-documentation/docs/development/metadata.md` | https://docs.espocrm.com/development/metadata/ |
| Tests | `~/safehouse/espocrm-documentation/docs/development/tests.md` | https://docs.espocrm.com/development/tests/ |

**Why this shape:** The feature is an audit/proposal, not a product UI change;
doc-backed severity and native-first judgments are the acceptance surface.
