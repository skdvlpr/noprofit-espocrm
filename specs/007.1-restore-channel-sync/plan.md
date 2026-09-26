# Implementation Plan: Restore contact–user channel and name sync

**Branch**: `007.1-restore-channel-sync` | **Date**: 2026-09-25 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/007.1-restore-channel-sync/spec.md`

## Summary

Restore two-way live copy of the full email set and full phone set
between a linked Contact and User, as 004.1 already did for Volunteer
and Employee. Extend the same copy to Associato (`MemberContact`). Also
copy prefix, first name, and last name both ways. Empty contact email
must not wipe the user’s login email.

Path = **Code**. Formula cannot copy email/phone *sets*, cannot set a
SaveOption skip flag, and cannot apply the empty-email guard. Rejected:
Advanced Pack Workflows/BPM (constitution II). Rejected: a second Tool
or new entity.

PDF / convert / 005.2 stay closed. `006` stays paused. Local DDEV only.

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10.0.3, module `NonprofitEspocrm`.
Local PHP **DDEV only**.

**Primary Dependencies**: Existing `ContactUserChannelSync`, Contact and
User `afterSave` hooks, `ContactTypeSet::wantsCrmUser`, native Email and
Phone field sets, personName attributes `salutationName` / `firstName` /
`lastName`. Existing UniqueAmongContacts / UniqueAmongUsers validators.
No new Composer libraries.

**Storage**: MariaDB via ORM. **No new entity types. No schema change.**
Email and phone rows stay on native `emailAddress` / `phoneNumber`
fields.

**Testing**: DDEV PHPUnit on `ContactUserChannelSync` (Associato copy,
name copy, empty-email guard, Volunteer regression, Help-seeker no-op,
skip-option loop). Owner UAT on local Rossella. Full
`ddev exec bash bin/run-tests.sh` before any later push discussion.

**Target Platform**: Local DDEV `https://nonprofit-espocrm.ddev.site`.
Production apply is out until the owner names it.

**Project Type**: EspoCRM module NonprofitEspocrm (`custom/` only).

**Performance Goals**: One counterpart save per changed identity (name
and/or channels). MUST NOT N+1 by table name.

**Constraints**: MUST NOT edit `application/`. MUST NOT create a User or
Contact. MUST NOT change `assignedUserId` or `userName`. MUST NOT copy
hours, competences, board, or PDF. Unique SaveOption
`nonprofitSkipContactUserChannelSync` stays. Italian UI primary.

**Scale/Scope**: One Tool and two existing afterSave hooks. Unit tests
for the Tool. No client JS unless a uniqueness message is already
missing (it is not).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I Official-docs / native-first | Hooks afterSave; Email/Phone sets; personName attributes; ORM save with skip option; Formula rejected | PASS — research |
| I Path stated | Path = Code | PASS |
| I No hardcoded SQL table names | Sync uses EntityManager only | PASS |
| II Extensions only | `custom/Espo/Modules/NonprofitEspocrm/` only | PASS |
| III One active spec | Amendment `007.1` of parent `007` | PASS |
| IV Doc-backed planning | GitHub blob cites in plan/research/contracts | PASS |
| VI Secrets / PII | No new secrets; emails stay on existing fields | PASS |
| VII / XVIII | Local DDEV; no prod apply | PASS |
| VIII Git | No commit/push in this command | PASS |
| XI Schema | No new columns | PASS |
| XIV Tests | DDEV PHPUnit for the Tool | PASS |

Post-design re-check: same gates. Design does not add Formula, BPM, core
edits, a new entity, or a second sync Tool.

## Project Structure

### Documentation (this feature)

```text
specs/007.1-restore-channel-sync/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── channel-name-sync.md
└── tasks.md             # not created here
```

### Source Code (repository root)

```text
custom/Espo/Modules/NonprofitEspocrm/
├── Tools/ContactUserChannelSync.php          # extend eligibility + name + empty-email
├── Tools/ContactTypeSet.php                  # wantsCrmUser already exists
├── Hooks/Contact/SyncLinkedUserChannels.php  # comments only if needed
└── Hooks/User/SyncLinkedContactChannels.php

tests/unit/Espo/Modules/NonprofitEspocrm/
└── ContactUserChannelSyncTest.php            # Associato, name, empty email
```

**Structure Decision**: Keep the 004.1 Tool and hooks. Do not add a
second module class for name.

## Phase 0

See [research.md](./research.md).

## Phase 1

- [data-model.md](./data-model.md)
- [contracts/channel-name-sync.md](./contracts/channel-name-sync.md)
- [quickstart.md](./quickstart.md)

## Complexity Tracking

No constitution exception.
