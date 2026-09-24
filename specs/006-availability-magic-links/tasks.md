# Tasks: Shift planner review and availability links without login

**Input**: Design documents from `/specs/006-availability-magic-links/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/availability-link.md, quickstart.md

**Tests**: PHPUnit inside DDEV for token rules, cohort gate, and resend (constitution XIV). Then send the volunteer availability template through the outbound mail already configured on local DDEV and read the received message: personal link to the shift dialog only, no CRM login link, no planner URL. No production send. No production apply.

**Organization**: US1 defects first. US2 public dialog. US3 resend. Owner amendment 2026-09-24: the public page is only the existing availability dialog, phone-width, each tick saves itself, the link stays live for 7 days or until replaced.

**Models**: Every row is **Auto** (this agent). Complexity of the feature is 7. Do not downgrade the model. Do not launch a stronger model unless the owner names it.

**Docs to cite in code comments and commits of this feature** (opened this turn):

- https://github.com/espocrm/documentation/blob/master/docs/development/entry-points.md
- https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
- https://github.com/espocrm/documentation/blob/master/docs/development/custom-css.md
- https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-client.md
- https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
- https://github.com/espocrm/documentation/blob/master/docs/development/acl.md

Native pattern to copy, not edit: `application/Espo/EntryPoints/LeadCaptureForm.php` (`NoAuth` + `ActionRenderer`). Existing dialog to reuse: `client/custom/modules/nonprofit-espocrm/src/views/activity-offer/modals/availability.js`.

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [ ] T001 Confirm feature dir `specs/006-availability-magic-links` and that `application/` stays untouched. [C1] Auto. test: no — inventory only.

## Phase 2: Foundational

**Checkpoint**: Entity and token helper exist before US2/US3. No public page yet.

- [ ] T002 Add entity `AvailabilityAccessLink` in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/entityDefs/AvailabilityAccessLink.json` and `scopes/AvailabilityAccessLink.json`: links `activityOffer` and `user`, fields `tokenHash`, `expiresAt`, `replacedAt`. No layout, no tab. ACL default is no access for non-admin. Cite orm.md. [C4] Auto. test: no — metadata; behaviour is T006.
- [ ] T003 [P] Add `custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanning/AvailabilityAccessLinkService.php`: create a random secret, store only `Espo\Core\Utils\Hasher` output, find a live row (not replaced, `expiresAt` in the future), replace previous live rows for the same user and plan. Never log the secret. [C6] Auto. test: yes — covered by T006.

## Phase 3: User Story 1 — Planner defects (P1)

**Goal**: Installer SQL, admin self-save, and address logging are gone. Availability emails still use the CRM URL until US2.

**Independent Test**: Rebuild path has no `SHOW COLUMNS` on `activity_offer_slot`. A non-cohort admin cannot write their own availability. Mail logs do not contain the recipient address.

- [ ] T004 [US1] Remove `migrateLegacyPlaceVarchar` and its call sites in `custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanningInstaller.php` and `custom/Espo/Modules/NonprofitEspocrm/Tools/Installer.php`. Do not replace it with new SQL. Cite orm.md (no hardcoded table names). [C3] Auto. test: no — absence of the SQL strings.
- [ ] T005 [US1] In `custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanning/AvailabilityWorkflow.php` `saveAvailability`, allow the write only when the target user is in the plan cohort. Remove the `isAdmin()` bypass. Cite acl.md. [C4] Auto. test: yes — T006.
- [ ] T006 [P] [US1] Unit-test the cohort gate and the link helper in `tests/unit/Espo/Modules/NonprofitEspocrm/AvailabilityAccessLinkTest.php`: non-cohort admin refused; hash is not the raw secret; replaced and expired secrets do not resolve. [C5] Auto. test: yes — this file is the test.
- [ ] T007 [US1] In `custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftEmailService.php` stop logging the recipient address. Log user id and kind only. [C2] Auto. test: no — log line review.

**Checkpoint**: US1 is done. Do not send a no-login link yet.

## Phase 4: User Story 2 — Public availability dialog (P1)

**Goal**: The email link opens only the existing shift-choice dialog, full width on a phone, and each tick is stored at once. The signed-in CRM dialog keeps Save and Cancel.

**Independent Test**: Private window, no CRM login. Page is the shift list and nothing else. A tick is stored. A second tick on the same link still works. After 7 days the link does nothing.

- [ ] T008 [US2] Refactor `saveAvailability` in `custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanning/AvailabilityWorkflow.php` so a trusted caller passes the target user id. `custom/Espo/Modules/NonprofitEspocrm/Controllers/ActivityOffer.php` `postActionSaveAvailability` still passes only the signed-in user after the cohort check. [C5] Auto. test: no — T006 still covers the gate; controller stays session-bound.
- [ ] T009 [US2] Add `custom/Espo/Modules/NonprofitEspocrm/EntryPoints/ShiftAvailability.php` with trait `NoAuth`. GET with a live secret renders a client page via `Espo\Core\Utils\Client\ActionRenderer` (same shape as `LeadCaptureForm`). POST with the secret and one shift id plus checked/unchecked updates that volunteer only. Unknown, replaced, or expired secret renders the Italian invalid-link page and writes nothing. Cite entry-points.md. [C7] Auto. test: yes — T014.
- [ ] T010 [US2] Add a tiny client controller `client/custom/modules/nonprofit-espocrm/src/controllers/shift-availability.js` that creates only `nonprofit-espocrm:views/activity-offer/modals/availability` with the grid payload from the entry point. No navbar, no record view. Cite modal.md. [C5] Auto. test: no — owner UAT on a phone width.
- [ ] T011 [US2] In `client/custom/modules/nonprofit-espocrm/src/views/activity-offer/modals/availability.js`, when opened with `publicLink: true`: empty `buttonList` (no Save, no Cancel), do not render description, plan place, comment, info/warning alerts, or status badges beyond the shift row. On checkbox change, POST the entry point and keep the dialog open. Signed-in `fillAvailability` in `client/custom/modules/nonprofit-espocrm/src/handlers/activity-offer/shift-actions.js` stays on the authenticated `saveAvailability` action with Save and Cancel. [C6] Auto. test: no — view branch; server rules are T014.
- [ ] T012 [P] [US2] Append phone rules to `client/custom/modules/nonprofit-espocrm/res/css/activity-offer.css` (already listed in `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/app/client.json`): the public dialog is full viewport width under 768px, shift labels wrap, checkboxes stay at least 44px tap targets. Cite custom-css.md and app-client.md. Do not add a second `cssList` entry. [C3] Auto. test: no — visual, owner UAT.
- [ ] T013 [US2] In `custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanningInstaller.php` change the availability-request template body so the button is `{availabilityUrl}` and the sentence no longer says to open the plan in the CRM. Bump the template content version so existing installs refresh the body. In `custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftEmailService.php` `sendAvailabilityRequest`, create one link per emailed user and pass that URL only into that message. Confirmation and update templates keep `{recordUrl}`. [C6] Auto. test: yes — T014 asserts the availability body contains the personal URL and not another user’s secret.
- [ ] T014 [P] [US2] Extend `tests/unit/Espo/Modules/NonprofitEspocrm/AvailabilityAccessLinkTest.php`: two users get two secrets; a tick does not replace or expire the link; a second tick still resolves; an 8-day-old secret does not; user A’s secret cannot write user B. [C5] Auto. test: yes — this file.

**Checkpoint**: A test volunteer can answer from the link. Resend still sends the old behaviour until US3.

## Phase 5: User Story 3 — Resend rotates selected links (P1)

**Goal**: Resend to a chosen set replaces only their links and emails only them.

**Independent Test**: Three volunteers mailed. Resend to two. Those two old secrets fail. The third secret still ticks. Mail count for the resend is two.

- [ ] T015 [US3] In `requestAvailability` and `requestAvailabilityForUsers` in `custom/Espo/Modules/NonprofitEspocrm/Tools/ShiftPlanning/AvailabilityWorkflow.php`, call the link service only for users who will actually be emailed. `custom/Espo/Modules/NonprofitEspocrm/Controllers/ActivityOffer.php` `postActionRequestAvailabilityForUsers` keeps its current `userIds` audience. People skipped for no address get no row. [C5] Auto. test: yes — T016.
- [ ] T016 [P] [US3] Extend `tests/unit/Espo/Modules/NonprofitEspocrm/AvailabilityAccessLinkTest.php`: resend to two of three replaces only those two hashes; the third secret still resolves. [C4] Auto. test: yes — this file.

## Phase 6: Polish

- [ ] T017 [P] Write `specs/006-availability-magic-links/checklists/owner-user-tests.md` from `quickstart.md`, including a phone-width check of the dialog and a tick that sticks without Save. [C2] Auto. test: no.
- [ ] T018 `ddev exec vendor/bin/phpunit tests/unit/Espo/Modules/NonprofitEspocrm/AvailabilityAccessLinkTest.php`, `ddev exec vendor/bin/phpstan analyse -c phpstan.neon` on the new PHP files, `ddev exec php command.php rebuild`. Inactivate Google Calendar Sync, Overlay, and Send Push Reminders if rebuild reactivated them (ORM update, no hardcoded table names). [C3] Auto. test: no — the tests are the task. Full `ddev exec bash bin/run-tests.sh` only before a later push decision.
- [ ] T020 On local DDEV, request availability for a test volunteer whose mailbox you can open. Read the received message. It must contain only that person’s link, and opening it must show the shift dialog and nothing else. The message must not contain the CRM planner URL or a login instruction. Do not print SMTP credentials. Do not send from production. Record the result in `.specify/progress/` without addresses if they are personal. [C4] Auto. test: yes — the received local message is the check.
- [ ] T019 Append `.specify/progress/` implement handoff. Do not commit, push, or apply production unless the owner asks in that turn. Do not send mail to real volunteers. [C1] Auto. test: no.

## Dependencies

- T002 and T003 before T009, T013, T015
- T004, T005, T007 before T009 (US1 before the public page)
- T006 may land with T003 and T005
- T008 before T009
- T009 before T010 and T011
- T011 before T012
- T013 after T003
- T014 after T009 and T013
- T015 after T013
- T016 after T015
- T018 after the story tasks being checked
- T020 after T013 and T011, on local DDEV outbound mail, before the feature is called done

US2 and US3 are not parallel: US3 rotates links that US2 creates.

## Parallel opportunities

- T003 and T004 (different files) after T001
- T006 with T004
- T012 with T013 after T011 has started only if T012 does not wait on T011’s class names — prefer T012 after T011
- T014 and T016 are the same test file: do not run them in parallel

## Implementation strategy

1. Phase 1–3 (US1) and stop if the SQL or admin gate is still wrong.
2. Phase 4 (US2) is the first volunteer-visible slice.
3. Phase 5 (US3) before any real resend.
4. Phase 6. Owner UAT on local DDEV, including a narrow viewport. No production apply in these tasks.

## Notes

- Signed-in CRM dialog keeps Save, Cancel, comment, and alerts.
- Public dialog: shift ticks only, autosave, link dies on expiry or resend, not on the first tick.
- Do not edit `application/`.
