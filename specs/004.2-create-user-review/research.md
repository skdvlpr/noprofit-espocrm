# Research: Create-User review modal

**Feature**: `004.2-create-user-review`  
**Date**: 2026-09-18

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/modules.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md

gm-edu (read this turn, mail pattern only):
`gm-edu-suite/gm-edu-crm/client/custom/modules/educational/src/views/user/record/promote-edit.js`
`gm-edu-suite/gm-edu-crm/custom/Espo/Modules/Educational/Services/TeacherPromoteService.php`
`gm-edu-suite/gm-edu-crm/specs/002.5.9-promote-access-info-link/spec.md`

## R1 — When the User panel opens

**Decision**: Do **not** open on `change:createCrmUser`. Override Contact
create `save()`: if new + Volunteer/Employee + checkbox on + email
present, open review `dialog dialog-record` and **return without**
`super.save()`. Modal Save then stashes User attrs and calls Contact
save; after Contact has an id, POST User (`sourceContactId`,
`sendAccessInfo`, **no password**). Modal Cancel: no persist.

**Rationale**: Owner: fill required fields first; panel is an explicit
admin confirm. 004.1 opened too early (Nessuno + password). 004 after-save
modal died on navigate — we persist only **after** confirm, still POST
User after Contact id (handshake).

**Alternatives considered**: Checkbox opens drawer (004.1, rejected UX).
Single custom API transaction (more surface; not needed if Cancel never
POSTs). Formula. Rejected.

## R2 — Checkbox only on create

**Decision**: `createCrmUser` stays on Contact `edit` + `detailSmall`
layouts (Espo create uses edit layout) but JS `hideField` when
`!model.isNew()`. Ignore the flag on update in JS (never POST User from
edit). Dynamic Logic still Volunteer/Employee; `isNew` is not expressible
in Dynamic Logic.

**Rationale**: Owner: never on edit / Modifica. Espo detail-edit uses
detail layout (already no checkbox). `#Contact/edit/{id}` would still
show it without the isNew guard.

**Alternatives considered**: Separate create-only layout (extra
app.layouts). Rejected as unnecessary if isNew hide is reliable.

## R3 — Password fields and invite checkbox

**Decision**: `create-from-contact` User record view: **always hide**
password, confirm, generate, preview. Always **show** `sendAccessInfo`
when email is set; default true; customLabel Italian
`Invia all'utente un'email con un link per creare una password` (EN
secondary). POST omits `password`. Core Administration → Users untouched.

**Rationale**: Espo: empty password + sendAccessInfo →
`sendAccessInfoForNewUser` (link). Specified password + sendAccessInfo →
plaintext (not recommended). gm-edu Promote still shows password when
mail is off; owner asked this panel to **never** show password fields.

**Alternatives considered**: gm-edu hide-when-on / show-when-off.
Rejected: owner wants fields gone. Patch core User edit. Forbidden.

## R4 — Hide Utente CRM while creating

**Decision**: When `createCrmUser` is true on a **new** Contact, hide
`linkedUser` and clear `linkedUserId`. Uncheck → show picker again.

**Rationale**: Owner + gm-edu 002.5.9 polish (Create vs pick mutually
exclusive).

## R5 — Pre-fill Role

**Decision**: Review model `rolesIds` from Role records named Volunteer
or Employee matching `contactType`. Admin may change.

**Rationale**: Owner: role written immediately from Contact type. ORM
lookup by name; MUST NOT hardcode Role ids.

## R6 — Admin-only Volunteer/Employee

**Decision**: Frontend: if `!this.getUser().isAdmin()`, remove Volunteer
and Employee from `contactType` options. Backend: Contact `beforeSave`
hook Forbidden if type is Volunteer/Employee and user is not admin
(including type change on update).

**Rationale**: Owner: other users may create Contacts but not
volunteers/employees. Dynamic Logic cannot see isAdmin. Roles UI is not
enough (tamper). Cite acl.md `isAdmin`.

**Alternatives considered**: Role-based permission (not specified).
Rejected for v1.

## R7 — Unique User email

**Decision**: Native User has **no** `duplicateCheckFieldList`. Contact
already warns on email (skippable). Add module `recordDefs/User.json`
`duplicateWhereBuilderClassName` **and** a FieldValidator / beforeSave
that **throws** if another non-deleted User already has that address
(primary or extra). Skippable 409 alone is not enough. Same email on
Contact + **its** linked User is allowed (different entity type).

**Rationale**: Owner screenshot: three Users on one mailbox. Native
username unique does not cover email.

**Alternatives considered**: Entity Manager duplicate-check fields only
(skippable). Unique index on email_address (shared across entities —
would break Contact+User share). Rejected.

## R8 — Contact profile reflection (not a second copy)

**Decision**: Keep `ContactProfileLoader` populating User notStorable
fields from linked Contact. Mark **all** volunteer/member profile fields
on User `readOnly` + `notStorable` (including `isOccasional`). **Stop**
`writeProfileToContact` copying those fields from User saves. Staff edit
on Contact. Planner still reads Contact via `linkedUserId`. Email/phone
channel sync (004.1) stays — that is login identity, not personnel
profile.

**Rationale**: Owner: visible on both, reflection, not field copies.
Native `foreign` needs a stored belongsTo; `linkedContact` is notStorable
`noJoin`, and multiEnum foreign is a poor fit. Loader + readOnly is the
existing 004 mirror, minus the write-back that made it a two-way copy.

**Alternatives considered**: Stored `User.linkedContactId` + type
`foreign` for every profile field. Deferred (schema + sync of two links).
Keep User→Contact profile write. Rejected by owner.

## R9 — Core files

**Decision**: Zero edits under `application/` or core client. Overrides
only in NonprofitEspocrm + `client/custom/modules/nonprofit-espocrm/`.

**Rationale**: Constitution II and owner lock this turn.
