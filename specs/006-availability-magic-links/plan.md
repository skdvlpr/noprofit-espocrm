# Implementation Plan: Shift planner review and availability links without login

**Branch**: `006-availability-magic-links` | **Date**: 2026-09-24 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/006-availability-magic-links/spec.md`

## Summary

Assess the shift planner against official Espo behaviour, fix the defects
that are wrong or unsafe, then send each volunteer their own time-limited
link in the availability email. The volunteer submits without a CRM
sign-in. Resend replaces links only for the people staff selected.

Path = **Code**. A no-login page, a secret that must be stored only as a
hash, and per-tick saving cannot be expressed as Formula. The public page
reuses the CRM availability dialog and shows nothing else. Ticks save
themselves. The link stays live for 7 days or until resend.
Advanced Pack Workflows are not available and are rejected. Metadata
entry-point defs are rejected because they are documented as of v10.1 and
this tree is Espo 10.0.3; the class entry point with `NoAuth` is the
native mechanism on this version.

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10.0.3, module `NonprofitEspocrm`

**Primary Dependencies**: Espo Entry Point + `NoAuth`, Entity Manager,
`Espo\Core\Utils\Hasher`, existing `ShiftEmailService` / `EmailSender`,
Email templates. No new framework.

**Storage**: New entity for one personal link per volunteer per send.
Existing `ActivityInvite` remains the availability answer. MySQL via DDEV
locally; production schema only after an explicit apply.

**Testing**: PHPUnit inside DDEV (`ddev exec bash bin/run-tests.sh`).
Full suite before any later push decision. Also send the volunteer
availability template through the outbound mail already configured on
local DDEV and read the received message. That message must contain
only the personal link that opens the shift dialog. It must not contain
the CRM planner URL. Do not print SMTP credentials. Do not send from
production.

**Target Platform**: Local DDEV `https://nonprofit-espocrm.ddev.site`,
including its already configured outbound mail. Production
`crm.safehouse.community` is out of this plan’s apply step.

**Project Type**: Espo extension (`custom/` + `client/custom/` only if a
staff control needs a label; the public page is server-rendered).

**Performance Goals**: One plan request of the current cohort size
creates one link per emailed volunteer and sends those emails. No new
batch job.

**Constraints**: Link lifetime 7 days or until submit. Resend invalidates
only selected people. Link does not create a CRM session. Do not log the
secret. Italian public page. No core edits.

**Scale/Scope**: One weekly plan, the volunteers already selected today.
Audit is the planner only.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Gate | Result |
|------|--------|
| I. Docs opened this turn and cited | Pass. Entry points, emails, ACL, ORM, app entry points (rejected for version). |
| I. No hardcoded SQL table names | **Fail today** in `ShiftPlanningInstaller::migrateLegacyPlaceVarchar`. In scope to remove or replace before magic links. No new SQL. |
| I. Path stated; no Advanced Pack | Pass. Path = Code. Workflows rejected. |
| II. Extensions only | Pass. New class under `custom/Espo/Modules/NonprofitEspocrm/`. |
| VI. Secrets / least privilege | Pass. Store hash only. Public page shows one volunteer’s shifts. No CRM login. |
| VII / XVIII. No prod apply | Pass. Local DDEV only until the owner names apply. |
| VIII. No commit / push in this command | Pass. Specify + plan only. |
| XI. Schema change | Pass. New entity via metadata; rebuild creates columns. Written in data-model. No hard rebuild. |
| XIV. Tests | Pass. DDEV PHPUnit for expiry, replace, wrong token, submit-once. |

Post-design re-check: same gates. The SQL violation is a planned fix, not
a new violation. Design does not add table-name SQL, core edits, or a
CRM session on the public page.

## Project Structure

### Documentation (this feature)

```text
specs/006-availability-magic-links/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── availability-link.md
└── tasks.md             # not created here
```

### Source Code (repository root)

```text
custom/Espo/Modules/NonprofitEspocrm/
├── EntryPoints/ShiftAvailability.php          # new, NoAuth
├── Tools/ShiftPlanning/AvailabilityWorkflow.php
├── Tools/ShiftEmailService.php
├── Tools/ShiftPlanningInstaller.php           # remove hardcoded SQL
├── Controllers/ActivityOffer.php              # logged-in save stays
└── Resources/metadata/entityDefs/             # AvailabilityAccessLink

client/custom/modules/nonprofit-espocrm/       # only if staff UI copy changes
```

## Phase 0

See [research.md](./research.md). Defects are listed there. In-scope fixes
block the public link.

## Phase 1

- [data-model.md](./data-model.md) — link record and answer rules
- [contracts/availability-link.md](./contracts/availability-link.md) — public page and resend
- [quickstart.md](./quickstart.md) — local checks without live mail

## Complexity Tracking

No constitution exception. The installer SQL is removed, not waived.
