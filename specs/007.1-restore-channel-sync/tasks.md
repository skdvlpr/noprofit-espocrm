# Tasks: Restore contact–user channel and name sync

**Input**: Design documents from `/specs/007.1-restore-channel-sync/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/channel-name-sync.md, quickstart.md

**Tests**: PHPUnit inside DDEV for Associato channel copy, name copy, empty-email guard, Volunteer regression, Help-seeker no-op, skip-option (constitution XIV). Owner UAT on local Rossella. Full `ddev exec bash bin/run-tests.sh` only before a later push. No production apply.

**Models**: Proposals only. Do not launch a stronger model until the owner names Launch or Replace per row. Feature complexity 4.

**Docs to cite in code comments** (opened at plan):

- https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
- https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
- https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
- https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [ ] T001 Confirm feature dir `specs/007.1-restore-channel-sync`. Do not edit `application/`. Do not implement `006`. PDF/convert stay closed. [C1] Auto. test: no — inventory.

## Phase 2: Foundational

**Purpose**: Confirm eligibility helper exists. No schema.

**Checkpoint**: `wantsCrmUser` is the type filter. No user-story code yet.

- [ ] T002 Confirm `ContactTypeSet::wantsCrmUser` in `custom/Espo/Modules/NonprofitEspocrm/Tools/ContactTypeSet.php` includes Volunteer, Employee, and MemberContact. Do not add a second helper. [C2] Auto. test: no — already covered by existing type-set tests; this is a read.

## Phase 3: User Story 1 — Emails and phones both ways (P1) 🎯 MVP

**Goal**: Linked Associato, Volunteer, and Employee pairs copy the full email set and full phone set both ways. Empty contact email does not wipe the user login email.

**Independent Test**: Associato contact email/phone change updates the user. User extra phone updates the contact. Help-seeker does not. Volunteer still copies.

### Tests for User Story 1

> Write tests FIRST. They MUST fail before T004.

- [ ] T003 [US1] Extend `tests/unit/Espo/Modules/NonprofitEspocrm/ContactUserChannelSyncTest.php`: Associato (`MemberContact`) contact email set copies to User; empty contact email set does not call `saveEntity` to wipe User emails; Volunteer still copies (existing); Help-seeker still never writes; skip option still no-op. Cite tests.md. [C4] Auto. test: yes — this file. Bug it would catch: Associato still skipped, or empty set clears login email.

### Implementation for User Story 1

- [ ] T004 [US1] In `custom/Espo/Modules/NonprofitEspocrm/Tools/ContactUserChannelSync.php` use `ContactTypeSet::wantsCrmUser` instead of `hasPersonnel`. If source email set is empty, do not overwrite counterpart emails; still copy phones when phones differ. Keep `nonprofitSkipContactUserChannelSync`. Cite fields.md and hooks.md. [C5] Auto. test: yes — T003. Why Auto: one existing Tool, eligibility + empty-email guard.

**Checkpoint**: Channel copy works for Associato. Names still drift until US2.

## Phase 4: User Story 2 — Name stays aligned (P1)

**Goal**: Prefix, first name, and last name copy both ways on the same linked pairs.

**Independent Test**: Change first name on the contact, user matches. Change last name on the user, contact matches. Unlinked contact does not create a user.

### Tests for User Story 2

> Write tests FIRST. They MUST fail before T006. Same file as T003 — do not run in parallel with T003/T004.

- [ ] T005 [US2] Extend `tests/unit/Espo/Modules/NonprofitEspocrm/ContactUserChannelSyncTest.php`: Associato contact `firstName` change copies to User; User `lastName` change copies to Contact; `salutation` copies; unlinked contact does not save a User. [C4] Auto. test: yes — this file. Bug it would catch: name still omitted from identity copy.

### Implementation for User Story 2

- [ ] T006 [US2] In `custom/Espo/Modules/NonprofitEspocrm/Tools/ContactUserChannelSync.php` copy `salutation`, `firstName`, `lastName` when they differ. Treat them as changed attributes alongside channels. Do not copy `middleName`, `userName`, or `assignedUserId`. Cite entity personName / fields.md. [C5] Auto. test: yes — T005. Why Auto: same Tool as T004.

- [ ] T007 [P] [US2] Update class/hook comments in `custom/Espo/Modules/NonprofitEspocrm/Tools/ContactUserChannelSync.php`, `custom/Espo/Modules/NonprofitEspocrm/Hooks/Contact/SyncLinkedUserChannels.php`, and `custom/Espo/Modules/NonprofitEspocrm/Hooks/User/SyncLinkedContactChannels.php` so they say Volunteer/Employee/Associato and name+channels. [C1] Auto. test: no — comments.

**Checkpoint**: Identity (name + all emails + all phones) matches both ways.

## Phase 5: Polish

- [ ] T008 [P] Write `specs/007.1-restore-channel-sync/checklists/owner-user-tests.md` from `quickstart.md` (Rossella: second email/phone, name both ways, empty contact email leaves user email, shared pair email succeeds). [C2] Auto. test: no — owner UAT script.
- [ ] T009 `ddev exec vendor/bin/phpunit tests/unit/Espo/Modules/NonprofitEspocrm/ContactUserChannelSyncTest.php`, PHPStan on the Tool, `ddev exec php command.php rebuild`. Inactivate Google Calendar Sync, Overlay, and Send Push Reminders if rebuild reactivated them (ORM, no table-name SQL). [C3] Auto. test: no — the tests are T003/T005.
- [ ] T010 Append `.specify/progress/` implement handoff. Do not commit, push, or apply production unless the owner asks in that turn. [C1] Auto. test: no.

## Dependencies

- T002 after T001
- T003 after T002; T004 after T003 (failing tests first)
- T005 after T004 (same test file and same Tool)
- T006 after T005
- T007 after T006 (comments match the final behaviour)
- T008 after T001 (can draft in parallel with US2)
- T009 after T006
- T010 after T009

US1 and US2 are **not** parallel: both edit `ContactUserChannelSync.php` and the same PHPUnit file.

## Parallel opportunities

- T007 comments wait on T006
- T008 owner checklist can be drafted while T005/T006 run
- No other [P] pairs on different files during US1

## Implementation strategy

1. T001–T002 read-only.
2. US1 (T003 fail → T004) is MVP: Associato emails/phones.
3. US2 (T005 fail → T006) adds name on the same Tool.
4. T009 local prove. Owner UAT Rossella. No production.

## Notes

- Do not edit `application/`.
- Do not recreate table `member`.
- Do not start `006`.
- UniqueAmong validators stay; no second uniqueness class.
