# Implementation Plan: Repair Contact-first CRM user create

**Branch**: `004.1-repair-create-crm-user` | **Date**: 2026-09-17 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004.1-repair-create-crm-user/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Repair 004 create-User (UAT Fail: Contact saved, no User, no CRM-user
link) and replace the after-save modal with a **side drawer** opened from
the **Create CRM user** checkbox (Volunteer **and** Employee; full create
and small create; also edit when no User yet). Copied name/email/phone
are read-only in that drawer. Contact save then creates the User with
native **Send access info**, empty password (set-password link, never
plaintext). Brand Access info / Password Change Link templates (Safe
House mark). Two-way email+phone **set** sync on the linked pair.

Path = **Code** (JS drawer + User POST after Contact id exists; hooks for
channel sync; template helper + module templates). Formula cannot open a
drawer or copy `emailAddressData`. Rejected: after-save `views/modals/edit`
on `record/edit` (view dies on navigate); sending `sendPassword`.

Parent 004 competences/planner/delete-Inactive stay. Local DDEV only.
Owner closes **004 and 004.1 together** after 004.1 UAT Pass.

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10.0.3, AMD JS in
`client/custom/modules/nonprofit-espocrm/`. Local PHP **DDEV only**.

**Primary Dependencies**: Espo Metadata (`entityDefs`, `clientDefs`,
`app.layouts`, `app.templates`, `app.templateHelpers`), Dynamic Logic,
native User create + Send access info, Hooks `afterSave`, ORM
`emailAddressData` / `phoneNumberData`, Htmlizer helper for logo.
No new Composer libraries. Theme already treats `.modal.dialog-record`
as a right drawer (`client/custom/css/safehouse-aurora/`).

**Storage**: MariaDB via ORM. No new entity types. No competence schema
change in this amendment.

**Testing**: DDEV PHPUnit for channel-sync Tool (copy sets, skip
Help-seeker, skip loop). Owner UAT for drawer + access email (SMTP may
be unset locally — then User still exists; mail is Skip with reason).

**Target Platform**: Local DDEV `https://nonprofit-espocrm.ddev.site`.
Production mail/copy/rebuild Skip until the owner names that action.

**Project Type**: EspoCRM module NonprofitEspocrm.

**Performance Goals**: Channel sync is one linked counterpart save per
email/phone change. MUST NOT N+1 by table name.

**Constraints**: MUST NOT edit `application/`. MUST NOT POST User before
Contact has an id. MUST NOT put password in access email. MUST NOT sync
non-personnel Contacts. Unique hook names and SaveOption flag
(`nonprofitSkipContactUserChannelSync`). Italian UI primary.

**Scale/Scope**: Contact layouts (edit + detailSmall vs detail), Contact
record JS, User create drawer, access-info templates + helper, Contact
and User afterSave channel sync. 004 Tools (`UserContactProfileSync`,
competences) unchanged except handshake still uses `sourceContactId`.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I Official-docs / native-first | drawer = `dialog-record` + modal.md; Send access info native; templates + helper; ORM email/phone sets; hooks afterSave | PASS — research R1–R7 |
| II Extensions only | NonprofitEspocrm + client custom + theme CSS already in module/theme | PASS |
| III One active spec | FEATURE_DIR `004.1`; parent 004 UAT failed, not a second main feature | PASS |
| IV Doc-backed planning | GitHub blob cites in plan/research/contracts | PASS |
| V Docs beat whim | Empty password + access-info link per users-management / passwords | PASS |
| VI Secrets | No new secrets | PASS |
| VII Safe deploy | Local DDEV; prod mail/copy Skip | PASS |
| VIII Git | Ask before commit; never push unless asked | PASS |
| IX Builders vs ZIPs | No new ZIP required | PASS |
| X Rebuild | `ddev exec php command.php rebuild` after metadata/layouts | PASS |
| XI Migrations | No schema drop in 004.1 | PASS |
| XII Legacy | Repair 004 create path; keep 004 competences | PASS |
| XIII New tech | None | PASS |
| XIV Tests | PHPUnit for channel sync; UAT for UI/mail | PASS |
| XV Models | Score at `/speckit-tasks` | PASS |
| XVI Progress | `.specify/progress/021-…` | PASS |
| XVII Communication | Chat RU; artifacts EN; dual-close + 003 reminder | PASS |
| XVIII DDEV ↔ prod | Local DDEV; prod approval-gated | PASS |
| XIX Native fields | bool checkbox; email/phone field types | PASS |
| XX Live REST | Not required for design | PASS |

**Post-design re-check:** PASS. Formula rejected for UI, User create, and
multi-address sync. Assigned User still not identity. 004 competence
column / copy command not reopened.

## Project Structure

### Documentation (this feature)

```text
specs/004.1-repair-create-crm-user/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── create-user-side-panel.md
│   ├── access-info-email.md
│   └── email-phone-sync.md
└── tasks.md             # /speckit-tasks — not this command
```

### Source Code (repository root)

```text
custom/Espo/Modules/NonprofitEspocrm/
├── Resources/metadata/
│   ├── entityDefs/Contact.json          # createCrmUser: hide on detail
│   ├── clientDefs/Contact.json          # edit + editQuick views
│   ├── app/layouts.json                 # Contact edit layout map
│   ├── app/templates.json               # accessInfo module
│   └── app/templateHelpers.json
├── Resources/layouts/Contact/
│   ├── detail.json                      # no createCrmUser; linkedUser stays
│   ├── edit.json                        # new: checkbox on create/edit
│   └── detailSmall.json                 # checkbox on quick create
├── Resources/templates/accessInfo/{en_US,it_IT,ru_RU}/
├── Resources/templates/passwordChangeLink/{en_US,it_IT,ru_RU}/
├── TemplateHelpers/SafehouseLogo.php
├── Tools/ContactUserChannelSync.php
├── Hooks/Contact/SyncLinkedUserChannels.php
├── Hooks/User/SyncLinkedContactChannels.php
└── Tools/UserContactProfileSync.php     # sourceContactId handshake keep

client/custom/modules/nonprofit-espocrm/src/views/
├── contact/record/edit.js               # checkbox → drawer; save → User POST
├── contact/record/edit-small.js         # same for quick create
├── modals/create-crm-user.js            # dialog-record drawer
└── user/record/create-from-contact.js   # sendAccessInfo default; lock copies

tests/unit/Espo/Modules/NonprofitEspocrm/
└── ContactUserChannelSyncTest.php
```

**Structure Decision**: Same NonprofitEspocrm module as 004. Aurora already
styles `.modal.dialog-record` as the side drawer — no new theme fork.

## Complexity Tracking

No constitution violations. Custom drawer is Espo modal + existing theme
drawer CSS, not a new UI framework.
