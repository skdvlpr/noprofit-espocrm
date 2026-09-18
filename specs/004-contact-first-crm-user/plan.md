# Implementation Plan: Contact-first volunteer/employee CRM user

**Branch**: `004-contact-first-crm-user` | **Date**: 2026-09-15 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004-contact-first-crm-user/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Staff start from **Contact** (Volunteer/Employee): fill the person
profile there, optionally create a CRM **User** (checkbox default on)
with name/email/phone copied. Person identity stays `linkedUser` (owner
Q1 = **A**). Assigned User stays ACL **own**, not identity. Shift
**competences** become a storable Contact multi-enum; the planner reads
the Contact linked to the User; User MAY keep **notStorable** mirrors
(same pattern as hours/dates). User delete still inactivates the
Contact. Copy User→Contact competences before dropping the User column.
Local wipe / prod sample is owner-gated (P2).

003 Google/WF owner UAT is **deferred until 004 closes** (still
required).

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10+ (this tree 10.0.3), AMD JS in
`client/custom/modules/nonprofit-espocrm/`. Local PHP **DDEV only**.

**Primary Dependencies**: Espo Metadata (`entityDefs`, `clientDefs`,
`recordDefs`, `app.layouts`, `app.consoleCommands`), ORM EntityManager,
Hooks (`AfterSave` / `AfterRemove` / `BeforeSave`), FieldProcessing
Loader, Dynamic Logic, Formula (existing Contact before-save), native
User create (`views/modals/edit`). No new Composer libraries. No
WorkflowEngine / Advanced Pack BPM.

**Storage**: MariaDB via Espo ORM. New/changed attributes on Contact and
User only. No new entity types. Rebuild adds Contact competence column
then, after copy, drops User competence column when `notStorable`.

**Testing**: DDEV PHPUnit unit tests for Tools (link-vs-create,
competences-from-Contact). Owner UAT checklist at implement-done.
003 UAT is a **later** handshake, not this feature’s close.

**Target Platform**: Local DDEV `https://nonprofit-espocrm.ddev.site`.
Production `crm.safehouse.community` (Caddy TLS) only after explicit
owner approve for copy/rebuild/deploy.

**Project Type**: EspoCRM module NonprofitEspocrm
(`custom/Espo/Modules/NonprofitEspocrm/` +
`client/custom/modules/nonprofit-espocrm/`).

**Performance Goals**: Planner competence lookup is one Contact find by
indexed `linkedUserId` per User in a week view — acceptable. MUST NOT
N+1 SQL by table name; use ORM.

**Constraints**: MUST NOT edit `application/`. MUST NOT use Assigned User
as identity. MUST NOT drop User competences before copy. MUST NOT run
local wipe or production migrator without that exact owner yes. Italian
UI primary. Formula vs Code: see research R6.

**Scale/Scope**: Contact/User metadata + layouts + i18n; User/Contact
hooks and `UserContactProfileSync`; `ContactProfileLoader`;
`ShiftPlanningSupport::getUserCompetences`; Contact record JS for
create-user; console copy command; ShiftPlanningInstaller layout
provision. Google, Prima Nota, food-parcel identity unchanged (FR-012).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I Official-docs / native-first | entityDefs, Dynamic Logic, Formula keep, hooks, ORM, modal | PASS — research R1–R8 |
| II Extensions only | NonprofitEspocrm module only | PASS |
| III One active spec | 004 is FEATURE_DIR; 003 implemented, UAT deferred by owner | PASS with recorded exception (table below) |
| IV Doc-backed planning | GitHub blob cites in spec/research/contracts | PASS |
| V Docs beat whim | Q1 A matches ACL own ≠ identity | PASS |
| VI Secrets | No new secrets | PASS |
| VII Safe deploy | Local DDEV; prod copy/rebuild owner-gated | PASS |
| VIII Git | Ask before commit; never push unless asked | PASS |
| IX Builders vs ZIPs | No new ZIP required | PASS |
| X Rebuild | `ddev exec php command.php rebuild` after metadata | PASS |
| XI Migrations | Copy command then notStorable; no silent prod DROP | PASS |
| XII Legacy | Reverse User→Contact create; keep mirrors | PASS |
| XIII New tech | None | PASS |
| XIV Tests | Propose real-logic PHPUnit at tasks; no coverage % | PASS |
| XV Models | Complexity scored at `/speckit-tasks` | PASS |
| XVI Progress | `.specify/progress/017-…` | PASS |
| XVII Communication | Chat RU; artifacts EN; 003 UAT reminder | PASS |
| XVIII DDEV ↔ prod | Local DDEV; prod Caddy approval-gated | PASS |
| XIX Native fields | bool checkbox; multiEnum competences; link identity | PASS |
| XX Live REST | Not required for design; live wipe/import owner-gated | PASS |

**Post-design re-check:** PASS. Identity = `linkedUser`. Competences
stored on Contact; User mirrors `notStorable` after copy. Planner reads
Contact by `linkedUserId`. Create-user is UI + hook, not Formula.
Duplicate Contact create from User is stopped for Volunteer/Employee.

### Justified exception

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| III: owner UAT before next **main** feature | Owner 2026-09-15: defer 003 U1–U7 until 004 is closed; 004 is now more important | Wait for 003 UAT first — owner overrode for this queue |

003 is **not** accepted. Closing tests remain on
`specs/003-google-standalone/checklists/owner-user-tests.md`.

## Project Structure

### Documentation (this feature)

```text
specs/004-contact-first-crm-user/
├── plan.md              # This file
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── identity-link.md
│   ├── create-user-from-contact.md
│   ├── competences-and-planner.md
│   └── competence-migration.md
└── tasks.md             # NOT created by /speckit-plan
```

### Source Code (repository root)

```text
custom/Espo/Modules/NonprofitEspocrm/
├── Resources/metadata/entityDefs/{Contact,User}.json
├── Resources/metadata/clientDefs/{Contact,User}.json
├── Resources/metadata/recordDefs/User.json
├── Resources/metadata/app/layouts.json          # already maps Contact/User
├── Resources/metadata/app/consoleCommands.json  # copy command
├── Resources/metadata/formula/Contact.json      # keep monthlyHours/status
├── Resources/layouts/Contact/detail.json
├── Resources/layouts/User/detail.json
├── Resources/i18n/{en_US,it_IT,ru_RU}/{Contact,User}.json
├── Tools/UserContactProfileSync.php
├── Tools/ShiftPlanning/ShiftPlanningSupport.php
├── Tools/ShiftPlanningInstaller.php
├── Classes/FieldProcessing/User/ContactProfileLoader.php
├── Classes/ConsoleCommands/                     # copy User→Contact competences
├── Hooks/User/SyncContactProfile.php
├── Hooks/User/InactivateLinkedContacts.php      # keep
└── Hooks/Contact/SyncIsUser.php                 # keep

client/custom/modules/nonprofit-espocrm/src/views/contact/record/
├── detail.js    # extend: after:save create-user (or shared mixin)
└── edit.js      # new or handler — checkbox + after:save
```

**Structure Decision**: Single Espo module NonprofitEspocrm (existing).
No new module, no core edits.

## Complexity Tracking

See Constitution Check exception table (003 UAT deferred). No extra
architecture violations.

## Phase 0 / Phase 1 outputs

- [research.md](./research.md) — Q1 A, mirrors, Formula vs Code, migration
  order, 003 UAT deferral.
- [data-model.md](./data-model.md)
- [contracts/](./contracts/)
- [quickstart.md](./quickstart.md)

`/speckit-tasks` is **not** this command.
