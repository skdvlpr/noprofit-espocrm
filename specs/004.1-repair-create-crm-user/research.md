# Research: Repair Contact-first CRM user create

**Feature**: `004.1-repair-create-crm-user`  
**Date**: 2026-09-17

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/client-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-templates.md
https://github.com/espocrm/documentation/blob/master/docs/development/template-custom-helper.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md

## R1 — Why 004 create-User failed

**Decision**: Treat the failure as a **navigation + layout** bug, not a
missing competence migration. DDEV still has prod-linked Contacts;
`Volontario User123` is Employee with empty `linkedUserId`.

**Rationale**: 004 opened User create from Contact `record/edit`
`after:save`. Espo then routes to `#Contact/view/{id}` and destroys that
view, so the modal never completes. `createCrmUser` is notStorable, so
**detail** shows “–”. Quick create uses `detailSmall`, which never had
the checkbox.

**Alternatives considered**: Keep after-save modal but parent it on
`views/edit` — still destroyed on navigate. Rejected.

## R2 — Side panel = existing `dialog-record` drawer

**Decision**: Open a User form in `views/modals/edit` (or a thin extend)
with `className` `dialog dialog-record`. Safehouse Aurora already docks
`.modal.dialog-record` to the side
(`client/custom/css/safehouse-aurora/safehouse-aurora-layout.css`).

**Rationale**: Owner asked for a panel that “comes out from the side”.
Native modal.md + this theme is that UI. Do not invent a second drawer
system.

**Alternatives considered**: Centered `views/modals/edit` (004, failed UX);
iframe; new CSS framework. Rejected.

## R3 — When to POST the User

**Decision**: Drawer is a **draft**. Confirming it only stashes User
attributes on the Contact form. **Contact save** (after the Contact has
an id) POSTs User with `sourceContactId`, `sendAccessInfo: true`, **no
password**. Existing `UserContactProfileSync::linkFromSourceContact`
sets `linkedUserId` and MUST NOT set `assignedUserId`.

**Rationale**: User create needs a Contact id for the handshake. Spec:
save Contact → User exists. Closing the drawer without confirm unchecks
the box (spec edge case).

**Alternatives considered**: POST User first (orphan login). After-save
modal (R1). Formula `record\create` (cannot send access info / password
request). Rejected.

## R4 — Checkbox visibility (create vs detail)

**Decision**: Add `layouts/Contact/edit.json` (checkbox) and map it in
`app.layouts`. Put checkbox on `detailSmall`. **Remove** `createCrmUser`
from `detail.json` (keep **linkedUser**). Dynamic Logic: visible when
`contactType` in Volunteer, Employee **and** no `linkedUserId`. Default
true on **new** records only.

**Rationale**: Espo create/edit can use a dedicated edit layout
(https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-layouts.md).
Detail should show the person link, not a notStorable dash.

**Alternatives considered**: `layoutDetailDisabled` only — create still
shares detail layout today. Rejected as the sole fix.

## R5 — Send access info, never plaintext password

**Decision**: Native User create: empty password + `sendAccessInfo`
calls `sendAccessInfoForNewUser` (set-password link). If staff typed a
password in the drawer, **strip it** before POST when send-access is on,
so core never takes the `sendPassword` path
(https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md).
Hide password fields in the drawer when send-access is on (same idea as
gm-edu promote-edit, without LearnHouse).

**Rationale**: Owner forbade raw passwords. Core already documents empty
password + link as the recommended path.

**Alternatives considered**: Custom mailer; Formula `ext\user\sendAccessInfo`
after a Formula-created User (no drawer, no handshake). Rejected.

## R6 — Branded templates

**Decision**: Module templates
`Resources/templates/accessInfo/{lang}/body.tpl` (and subject) plus
`passwordChangeLink`, with `app.templates` `module: NonprofitEspocrm`.
Logo via Htmlizer helper `safehouseLogo`
(https://github.com/espocrm/documentation/blob/master/docs/development/template-custom-helper.md)
because core `{{siteUrl}}` in access-info is the **change-password URL**,
not the site root (`Sender::getAccessInfoTemplateData`). Italian primary;
en_US + ru_RU bodies too.

**Rationale**: Template Manager files are the documented override. Helper
is the documented way to inject Safe House mark without patching
`application/`.

**Alternatives considered**: Patch Sender (forbidden); CID from
ShiftEmailService inside core Sender (cannot); hardcoded prod URL.
Rejected.

## R7 — Email and phone sets, both ways

**Decision**: Tool `ContactUserChannelSync` copies `emailAddressData` and
`phoneNumberData` (full set, primary included) between the linked
Volunteer/Employee Contact and User. Hooks: Contact `afterSave` and User
`afterSave`. Skip with unique `SaveOption`
`nonprofitSkipContactUserChannelSync`. Skip Help-seeker / no link.
Compare normalized sets to avoid loops.

**Rationale**: Email/phone are **sets**, not one varchar
(https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md).
Formula can set `emailAddress` string, not the extra rows. Food-parcel
code already reads `phoneNumberData` in this module.

**Alternatives considered**: Sync primary only (fails FR-011); gm-edu
primary-only + LearnHouse patch (out of scope). Rejected.

## R8 — Formula vs Code (constitution I)

| Behaviour | Path | Why | Rejected |
|-----------|------|-----|----------|
| Side drawer + checkbox | **Code** (JS) | Formula cannot open UI | Formula |
| Create User after Contact | **Code** (JS POST + existing User hook) | Need sendAccessInfo + sourceContactId | Formula `record\create` |
| Access email brand | **Templates + helper** | Documented Template Manager | Core Sender patch |
| Email/phone set sync | **Code** (hooks + Tool) | Multi-address not Formula-safe | Formula copy of one string |
| 004 competences / planner | Unchanged | Parent spec | Re-copy dump |

## R9 — Scope not in this amendment

004 competence copy, planner Contact read, delete-User → Inactive: no
redesign. Production copy/rebuild/mail to live volunteers: Skip.
Member checkbox: still out. 003 UAT after dual close.
