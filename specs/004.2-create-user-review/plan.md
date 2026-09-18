# Implementation Plan: Create-User review modal, unique email, Contact mirror

**Branch**: `004.2-create-user-review` | **Date**: 2026-09-18 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004.2-create-user-review/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Replace 004.1 “checkbox opens User drawer immediately” with: **Crea utente
CRM** only on Contact **create**; Save opens a **review** `dialog-record`
panel; admin confirms; then Contact+User persist. No password fields.
Invite checkbox = native **set-password link** (`sendAccessInfo` + omit
password), renamed on this panel only. Volunteer/Employee create =
**admin**. Unique User email. Personnel profile on User = **read-only
reflection** of Contact (no second store, no User→Contact profile copy).

Path = **Code**. Formula cannot intercept Save, hide password, unique-check
User emails, or ACL-filter enum types. Rejected: any edit of `application/`
or core JS.

Parent 004 competences/planner stay. 004.1 UAT not accepted. Local DDEV.
Owner closes **004 + 004.1 + 004.2** after this UAT Pass.

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10.0.3, AMD JS in
`client/custom/modules/nonprofit-espocrm/`. Local PHP **DDEV only**.

**Primary Dependencies**: Espo Metadata, Dynamic Logic, native User create
+ Send access info, Hooks, ORM, Acl `user->isAdmin()`, duplicate
WhereBuilder + hard User email validator. Theme already docks
`.modal.dialog-record`. gm-edu Promote (`promote-edit.js`,
`TeacherPromoteService::createRegularCrmUser`) is the **mail** pattern
only — do not copy Promote/LH.

**Storage**: MariaDB via ORM. No new entity types. `User.isOccasional`
becomes `notStorable` (leftover column unused). No production User
deletes.

**Testing**: DDEV PHPUnit: unique User email; admin-only personnel type;
profile write-back disabled; User create payload omits password when
sendAccessInfo. Owner UAT for modal + SMTP mail.

**Target Platform**: Local DDEV `https://nonprofit-espocrm.ddev.site`.

**Project Type**: EspoCRM module NonprofitEspocrm.

**Performance Goals**: One Contact save + one User create per confirmed
review. Unique-email check is one ORM query on User emails.

**Constraints**: MUST NOT edit `application/`, `client/src`, vendored
Espo. Only `custom/` and `client/custom/`. MUST NOT open modal on
checkbox. MUST NOT persist until review Save. MUST NOT email plaintext
password. MUST NOT show Crea utente CRM on existing-record edit.

**Scale/Scope**: Contact create/edit JS, create-crm-user modal,
create-from-contact User record view, Contact beforeSave ACL, User email
uniqueness, UserContactProfileSync load-only for profile, i18n, layouts.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I Official-docs / native-first | modal.md; passwords/users-management set-password link; fields foreign/notStorable; duplicate-check; acl isAdmin; hooks | PASS — R1–R8 |
| II Extensions only | NonprofitEspocrm + `client/custom` only. Zero core files | PASS |
| III One active spec | FEATURE_DIR `004.2`; amendment of 004.1 | PASS |
| IV Doc-backed planning | GitHub blob cites | PASS |
| V Docs beat whim | Empty password + sendAccessInfo = link, not sendPassword | PASS |
| VI Secrets | No new secrets | PASS |
| VII Safe deploy | Local DDEV; prod Skip | PASS |
| VIII Git | Ask before commit; never push unless asked | PASS |
| IX Builders vs ZIPs | No new ZIP required | PASS |
| X Rebuild | `ddev exec php command.php rebuild` after metadata | PASS |
| XI Migrations | isOccasional notStorable; do not DROP column in this spec | PASS |
| XII Legacy | Repair 004.1 UX; keep 004 competences on Contact | PASS |
| XIII New tech | None | PASS |
| XIV Tests | PHPUnit + owner UAT | PASS |
| XV Models | Score at `/speckit-tasks` | PASS |
| XVI Progress | `.specify/progress/025-…` | PASS |
| XVII Communication | Chat RU; artifacts EN; triple-close | PASS |
| XVIII DDEV ↔ prod | Local DDEV | PASS |
| XIX Native fields | bool checkbox; email unique via User email field; profile reflection | PASS |
| XX Live REST | Not required for design | PASS |

**Post-design re-check:** PASS. Formula rejected. Core Administration →
Users label unchanged. Email/phone channel sync from 004.1 stays.

## Project Structure

### Documentation (this feature)

```text
specs/004.2-create-user-review/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── review-modal.md
│   ├── access-info-link.md
│   ├── unique-user-email.md
│   ├── admin-personnel.md
│   └── contact-profile-mirror.md
└── tasks.md
```

### Source Code (repository root)

```text
custom/Espo/Modules/NonprofitEspocrm/
├── Classes/DuplicateWhereBuilders/User.php
├── Classes/FieldValidators/User/EmailAddress/UniqueAmongUsers.php
├── Hooks/Contact/RestrictPersonnelTypeToAdmin.php
├── Tools/UserContactProfileSync.php          # load-only for profile
└── Resources/
    ├── metadata/entityDefs/{Contact,User}.json
    ├── metadata/recordDefs/User.json
    ├── metadata/clientDefs/Contact.json
    ├── i18n/{en_US,it_IT,ru_RU}/{Contact,User}.json
    └── layouts/Contact/{edit.json,detailSmall.json}

client/custom/modules/nonprofit-espocrm/src/views/
├── contact/record/edit.js
├── contact/record/edit-small.js
├── contact/modals/create-crm-user.js
└── user/record/create-from-contact.js

tests/unit/Espo/Modules/NonprofitEspocrm/
```

**Structure Decision**: Extend NonprofitEspocrm only. Do not add files under
`application/` or `client/lib`. Prefer module layouts over
`custom/Espo/Custom/Resources/layouts` when both exist.
