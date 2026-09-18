# Research: Contact-first volunteer/employee CRM user

**Feature**: `004-contact-first-crm-user`  
**Date**: 2026-09-15

## Docs freshness

- **Decision**: Read constitution Read-root clone this turn; cite GitHub
  blob/master in artifacts.
- **Evidence**: Opened `docs/administration/users-management.md`,
  `docs/administration/roles-management.md`, `docs/administration/fields.md`,
  `docs/administration/dynamic-logic.md`, `docs/administration/formula.md`,
  `docs/administration/commands.md`, `docs/development/hooks.md`,
  `docs/development/orm.md`, `docs/development/metadata.md`,
  `docs/development/metadata/entity-defs.md`,
  `docs/development/metadata/client-defs.md`,
  `docs/development/metadata/record-defs.md`,
  `docs/development/metadata/logic-defs.md`,
  `docs/development/metadata/app-layouts.md`,
  `docs/development/metadata/app-console-commands.md`,
  `docs/development/customize-standard-fields.md`,
  `docs/development/custom-views.md`,
  `docs/development/frontend/view-setup-handlers.md`,
  `docs/development/modal.md`, `docs/development/modules.md`,
  `docs/development/coding-practices.md`, `docs/development/coding-rules.md`,
  `docs/development/acl.md`, `docs/development/tests.md`,
  `docs/development/translation.md`.
- **Alternatives**: Cite `docs.espocrm.com` — forbidden (constitution I).

## R1 — Person identity is `linkedUser`, not Assigned User (Q1 = A)

- **Decision**: Keep Contact → User `linkedUser` (`belongsTo` User,
  `linkedUserId`). Assigned User stays Espo **own** ownership. Creating a
  CRM User from a Contact MUST set `linkedUserId` and MUST NOT replace
  Assigned User with the new volunteer/employee login.
- **Rationale**: Owner 2026-09-15 answer **A**. Official ACL **own** is
  “assigned to” / `checkOwnershipOwn` through Assigned User. Staff who own
  many Contacts would collide if Assigned User meant “this person is this
  login”.
- **Citations**:
  https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
  (level *own* = records the user is assigned to);
  https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
  (`checkOwnershipOwn` — through assigned user);
  https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
  (link / belongsTo).
- **Alternatives considered**: Use Assigned User as identity — rejected by
  owner (Q1 A). Drop `linkedUser` and infer from email — ambiguous, no
  official 1:1 Contact↔User.

## R2 — Competences stored on Contact; User may keep notStorable mirrors

- **Decision**: Add storable `activityCompetences` on Contact (same
  `multiEnum` options / `ActivityOfferSlot.options.category` translation as
  today’s User field). After verified copy, User `activityCompetences`
  becomes `notStorable: true`. `ContactProfileLoader` +
  `UserContactProfileSync::loadFromContact` already fill User hours/dates
  from Contact; competences join that list. Planner MUST read Contact via
  `linkedUserId`, not `$user->get('activityCompetences')` on a raw ORM User
  (record loaders do not run on `getEntityById`).
- **Rationale**: Owner: only the **read path** and **storage home** change;
  User-form mirrors stay (hours/dates already work this way). `notStorable`
  means rebuild does not keep a second DB column
  (`entity-defs.md` *notStorable*). Empty list stays “all categories”
  (`AvailabilityWorkflow`: `$competences === []` → allowed).
- **Citations**:
  https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
  (`notStorable`);
  https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
  (Multi-Enum);
  https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
  (`readLoaderClassNameList`, `__APPEND__`);
  https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
  (EntityManager find / get by id — no identity map, no automatic field
  loaders).
- **Alternatives considered**: Remove User volunteering panel entirely —
  owner wants mirrors. Keep User column as second store — rejected (SC-002).
  Planner keeps reading User and hopes the loader ran — rejected (ORM fetch
  in shift tools is not a Record Service read).

## R3 — Create-user checkbox + User create UI (not Formula)

- **Decision**: Contact field `createCrmUser`: bool, **notStorable**,
  default **true**, layout on create. Dynamic Logic: visible only when
  `contactType` in `Volunteer`, `Employee`. After Contact save, if the flag
  is on, type matches, and `linkedUserId` is empty, open native User create
  (`views/modals/edit` or equivalent) with first name, last name, email,
  phone copied. Pass a notStorable `sourceContactId` (or equivalent) so
  User `afterSave` **links** that Contact instead of creating a second
  person. Access-info / password stay Espo User create
  (`users-management.md`). Members: no checkbox.
- **Rationale**: Formula cannot open a modal or copy into another entity’s
  create form. Dynamic Logic is native for visibility. Custom record view
  or `viewSetupHandlers` is the documented UI extension point.
- **Citations**:
  https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
  ;
  https://github.com/espocrm/documentation/blob/master/docs/development/metadata/client-defs.md
  (`viewSetupHandlers`, `recordViews`);
  https://github.com/espocrm/documentation/blob/master/docs/development/frontend/view-setup-handlers.md
  ;
  https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
  ;
  https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
  ;
  https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
  (Send access info, password).
- **Alternatives considered**: Formula `record\create` User on Contact
  save — no username/roles/access-info UI; rejected. Advanced Pack workflow
  — constitution II, WF uninstalled on this product. Relationship-panel
  Create User only — misses default-on checkbox on Contact create.

## R4 — Stop User-first Contact create for Volunteer/Employee

- **Decision**: `UserContactProfileSync::syncFromUser` MUST keep updating
  **existing** Contacts found by `linkedUserId`. It MUST NOT insert a new
  Contact when `sourceContactId` is set (link that row). For Volunteer /
  Employee Users with **no** linked Contact, MUST NOT auto-create (Contact
  is the person start). Member-role User-first Contact create MAY remain
  until a later spec. After linking, do **not** set Contact
  `assignedUserId` to the new User.
- **Rationale**: Spec edge case: User save must not recreate the old
  “always write a new Contact” loop. Assigned User is ownership (R1).
- **Citations**:
  https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
  (`afterSave`, unique hook name, `SaveOption`);
  https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md
  (`Hooks\`, `Tools\`).
- **Alternatives considered**: Keep auto-create for Volunteer Users —
  duplicates the Contact-first save. Set `assignedUserId` = new User as
  today — contradicts Q1 A.

## R5 — Delete User → Contact Inactive (unchanged)

- **Decision**: Keep `Hooks/User/InactivateLinkedContacts` (`afterRemove`,
  `linkedUserId` OR `portalUserId` → `personnelStatus` Inactive,
  `SKIP_ALL`). Do not delete the Contact.
- **Rationale**: Owner confirmed. Official hook is `afterRemove`.
- **Citations**:
  https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
  (`afterRemove`).
- **Alternatives considered**: Soft-delete Contact with User — rejected
  (US4). Formula on User remove — Formula does not replace remove hooks.

## R6 — Formula vs Code (constitution I)

| Behaviour | Path | Why | Rejected |
|-----------|------|-----|----------|
| `monthlyHours`, default `startDate`/`joinDate`, date-window `personnelStatus` | **Formula** (already `formula/Contact.json` before-save) | Attribute math on the same Contact; Entity Manager before-save script | Recode in PHP |
| Create-CRM-user checkbox visibility | **Dynamic Logic** (clientDefs today; logicDefs optional later) | Native form conditions | Formula (no UI) |
| Open User create + copy name/email/phone | **Code** (JS view / viewSetupHandler) | Needs a modal | Formula `record\create`; BPM |
| Link Contact after User save; no duplicate Contact | **Code** (User `afterSave` + Tools) | Needs `sourceContactId` / existing link | Formula on User (cannot reliably target the Contact just created in the other request without a flag) |
| Planner competences | **Code** (`ShiftPlanningSupport::getUserCompetences`) | PHP eligibility | Formula |
| Copy User competences → Contact then drop User column | **Code** (console command + metadata) | One-shot data + schema | Formula sandbox; ad-hoc SQL table names in runtime |
| `isUser` from `linkedUserId`/`portalUserId` | **Code** (existing Contact `beforeSave`) | Derived flag | Formula duplicate |

Workflows/BPM: not installed; constitution forbids assuming Advanced Pack.

**Citations**:
https://github.com/espocrm/documentation/blob/master/docs/administration/formula.md
;
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
;
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md

## R7 — Layouts, i18n, rebuild

- **Decision**: Put `activityCompetences` (and `createCrmUser` on create)
  on NonprofitEspocrm Contact **detail** layout; `app.layouts.Contact.detail.module`
  is already `NonprofitEspocrm`. User volunteering panel MAY keep mirror
  fields including competences. New strings: `en_US` + `it_IT` (constitution
  XVII); keep `ru_RU` where the module already has Contact/User files.
  After metadata: `ddev exec php command.php rebuild` (or `php rebuild.php`
  inside DDEV). Production rebuild only after owner-approved deploy.
- **Citations**:
  https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md
  ;
  https://github.com/espocrm/documentation/blob/master/docs/development/translation.md
  ;
  https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
- **Alternatives considered**: Edit `application/` layouts — forbidden
  (constitution II). Host PHP rebuild — forbidden (XVIII).

## R8 — Competence copy then drop User column (owner-gated wipe)

- **Decision**: Ordered steps: (1) Contact field + rebuild (adds column);
  (2) copy User → linked Volunteer/Employee Contact via listed console
  command (`app.consoleCommands`, ORM only, no hardcoded table names at
  runtime); (3) planner reads Contact; (4) User field `notStorable`; (5)
  rebuild drops User column. Local wipe of Users/Contacts + prod sample
  import is **P2 / owner-gated** and MUST NOT run in implement until the
  owner names that exact action. Production copy/drop likewise.
- **Citations**:
  https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-console-commands.md
  ;
  https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
  ;
  https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
  ;
  https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md
  (`Classes\ConsoleCommands\`).
- **Alternatives considered**: Drop User column before copy — data loss.
  Hard rebuild `--hard` as the migrator — destructive, not a copy.
  Runtime SQL `user.activity_competences` — constitution I / II (use ORM
  attribute names).

## R9 — 003 owner UAT deferred (queue, not cancelled)

- **Decision**: Feature `003-google-standalone` is implemented and
  committed locally (`9f8864c`). Owner UAT U1–U7 on
  `specs/003-google-standalone/checklists/owner-user-tests.md` remains
  **required** and is **deferred until 004 is closed**. 004 is now the
  active plan/implement track by owner interrupt (2026-09-15).
- **Rationale**: Owner: calendar/WF closing tests after this feature;
  volunteer/employee → User flow is more important now.
- **Alternatives considered**: Block 004 plan until 003 UAT — owner
  overrode Principle III “UAT before next main feature” for this turn.
  Silently mark 003 accepted — forbidden.

## R10 — Tests (propose at `/speckit-tasks`, not invent coverage %)

- **Decision**: Unit-test Tools that decide link-vs-create and competence
  read-from-Contact (the bugs those tests catch: duplicate Contact;
  planner still on User column). No metadata-dump tests. Owner UAT
  checklist for 004 is written at implement-done, not this plan command.
- **Citations**:
  https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
  ; constitution XIV.
- **Alternatives considered**: Integration-only — slower, still needed for
  rebuild/column drop later. Skip PHPUnit because UAT exists — constitution
  XIV forbids that for real logic.

## Unresolved clarifications

None. Q1 = A. Mirrors allowed. 003 UAT deferred, not dropped.
