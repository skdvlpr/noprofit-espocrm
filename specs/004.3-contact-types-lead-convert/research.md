# Research: 004.3 contact types, Lead convert, Contact email unique

**Date:** 2026-09-18  
**Feature:** `004.3-contact-types-lead-convert`

Cite (local clone opened this turn):
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
https://github.com/espocrm/documentation/blob/master/docs/administration/layout-manager.md
https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
https://github.com/espocrm/documentation/blob/master/docs/development/api-search-params.md
https://github.com/espocrm/documentation/blob/master/docs/administration/formula/array.md
https://github.com/espocrm/documentation/blob/master/docs/administration/api-before-save-script.md
https://github.com/espocrm/documentation/blob/master/docs/administration/formula/exception.md
https://github.com/espocrm/documentation/blob/master/docs/administration/formula/record.md
https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/development/modules.md
https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md

## R1 — Contact type storage

**Decision:** Change `Contact.contactType` from enum to **multiEnum** (`displayAsLabel`, `maxCount: 2`, `allowCustomOptions: false`, `storeArrayValues: true`). Keep the same field name so layouts and i18n stay.

**Rationale:** Official Multi-Enum is the native type for “several values, ordered list”
(https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md).
Checklist/array would also work; multiEnum matches today’s labelled types.

**Alternatives considered:**
- Extra bools `isVolunteer`/`isMember` — duplicates the type picker, not native-first.
- New `contactTypes` field plus leftover enum — two controls; worse UX.
- Changing the column in place without a copy — Espo jsonArray cannot read a bare `Volunteer` varchar.

**Migrate:** One-shot listed console command (same family as `copyUserActivityCompetences`): while the field is still enum, read each Contact type string, stash ids; then ship multiEnum metadata + soft rebuild; then apply writes `["Volunteer"]` etc. via ORM (no hardcoded table names). Empty type → `[]`.

## R2 — Legal combinations

**Decision:** Native `maxCount: 2` plus a **field validator** `LegalCombination` on `contactType`. UI also prevents Volunteer+Employee (options / client max). Server is source of truth (mass-update, API, convert).

Legal: `[]` or one of the current option keys, or `{Volunteer, MemberContact}`, or `{Employee, MemberContact}`.

**Rationale:** Formula Before-save `exception\throwInvalid` (v10) is **not shown to the user**
(https://github.com/espocrm/documentation/blob/master/docs/administration/formula/exception.md).
API Before-save `recordService\throwBadRequest` skips mass-update
(https://github.com/espocrm/documentation/blob/master/docs/administration/api-before-save-script.md).
`validatorClassNameList` is the documented field-validation extension point
(https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md).

**Alternatives considered:** Dynamic Logic `invalid` — frontend only. Formula-only — fails mass-update or UX.

## R3 — Dynamic Logic after enum→array

**Decision:** Replace `type: in` / `equals` on `contactType` with **`arrayAnyOf`**
(https://github.com/espocrm/documentation/blob/master/docs/development/api-search-params.md).
Volunteer panel: any of Volunteer, Employee. Member panel: MemberContact. Shared fields (tax, birth*): any of Volunteer, Employee, MemberContact. `createCrmUser`: those three **and** `linkedUserId` empty **and** record is new (existing 004.2 create-only).

**Rationale:** Documented operator for multi-enum. Formula `array\includes` is for scripts, not layout visibility.

## R4 — Primary filters

**Decision:** Keep named filters; where-clause uses `arrayAnyOf` semantics in Select Primary Filter classes (`contactType` contains Volunteer, etc.). Occasional NULL-safe rule stays.

**Rationale:** Current `'contactType' => 'Volunteer'` matches enum equality and would drop Volunteer+Member.

## R5 — Lead type and convert copy

**Decision:** Lead `contactType` **multiEnum**, `maxCount: 2`, options
`Volunteer`, `Employee`, `MemberContact`, `Other` (labels Volunteer /
Employee / Member / Generic). Same legal sets as Contact. Lead layouts
MUST NOT show volunteer/member extra fields; staff fill those on convert
Contact (`detailConvert`). Native Convert copies `contactType` because
name and type match.

**Rationale:** Native `ConvertService::getValues` copies a field only when
**name and type match**
(`application/Espo/Modules/Crm/Tools/Lead/ConvertService.php` — read this
turn, not edited). Owner UAT 2026-09-21: extras on convert, second type
by hand, Employee on Lead.

A Lead `leadType` **enum** would **not** copy onto Contact `contactType`
**multiEnum**. Extra panels on Lead were double-entry.

Account / Opportunity stay on `convertEntityList`. Contact `detailConvert`
layout in NonprofitEspocrm (app/layouts.json `module`) adds type + extra
fields + Crea utente CRM.

Historical Leads with empty type: treat as Generic (`Other`) on convert UI default.

## R6 — Convert + create User

**Decision:** Custom `client/custom` Lead **convert view** extending stock `crm:views/lead/convert`. Convert button: if Crea utente CRM on → same 004.2 review `dialog-record`; Cancel → no `Lead/action/convert` and the named view is cleared so Convert again reopens the review; Confirm → native convert POST then User create (004.2 payload, roles from Contact types). If checkbox off → stock convert only. Same clear-on-cancel on Contact create Save.

**Rationale:** Stock convert POSTs immediately
(https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md).
`ConvertService::processContact` uses `CreateParams::withSkipDuplicateCheck()` — another reason unique email cannot be only the skippable dialog.

MUST NOT fork ConvertService in `application/`.

**Defaults:** checkbox on for Volunteer/Member, off for Generic. Generic User: no Volunteer/Employee/Member roles.

Volunteer convert remains admin-only via existing type ACL (Contact create Forbidden).

## R7 — Unique Contact email (owner plan correction)

**Decision:** Same pattern as 004.2 User:

1. Keep/extend `scopes.Contact.duplicateCheckFieldList` with `emailAddress` (already in NonprofitEspocrm: email + phone) for the **warning** dialog on create/update (`updateDuplicateCheck: true` on Contact recordDefs).
2. Hard `validatorClassNameList` on Contact `emailAddress`: another Contact (`deleted` false) already owns that address via the native EmailAddress relation. Case-insensitive. Extra addresses included.
3. Allowed: same address on Contact and **that** Contact’s `linkedUser`. Not allowed: two Contacts; skippable “Save anyway”.

**Rationale:** Duplicate WhereBuilder is native but skippable
(https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md).
Owner: “as hard as User.” Convert skips duplicate check (R6). Formula `record\exists('Contact', 'emailAddress=', …)` misses extra addresses and is skippable if `throwDuplicateConflict`.

Generalize `UserEmailUniqueness` to an entity-type parameter **or** a sibling `ContactEmailUniqueness` sharing the EmailAddress repository (no SQL table names).

**Existing duplicates:** list on DDEV before enabling the hard validator in a way that blocks legacy rows; do not auto-merge. Owner cleans; prod list/apply only when named.

Lead email uniqueness stays stock skippable duplicate (not requested).

## R8 — Role sync

**Decision:** Contact afterSave hook: if `linkedUserId` set and types changed, add/remove **only** Roles whose **name** is Volunteer, Employee, Member to match `contactType`. Other Roles untouched. Lookup Role by name via ORM.

**Rationale:** Native User can have multiple Roles
(https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md).
Formula `record\relate` needs Role ids (brittle across DDEV/prod).

Create-user review pre-fills all matching Role names (004.2 already sets one).

## R9 — Shared person fields

**Decision:** Keep one layout row for tax/birth* in Overview (already shared). Volunteer and Member panels keep only exclusive fields. When both types selected, both panels visible (R3). User reflection already loads those Contact fields; ensure Member+Volunteer both populate the loader.

**Rationale:** Visual de-dupe is layout, not a second store.

## R10 — Admin-only types

**Decision:** Extend `RestrictPersonnelTypeToAdmin` to treat `contactType` as a list: if the new value **contains** Volunteer or Employee and that set changed, require `isAdmin`. Member-only remains allowed for regular staff.

## R11 — Create-form Volunteer/Employee picker must not clear

**Decision:** In the Contact record edit view, copy `selected` from the
model, then call native multiEnum `setOptionList(options, true)` only
when the option list actually changed. Do **not** use non-silent
`setOptionList` on `change:contactType`.

**Rationale:** Official Multi-Enum
(https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md)
and custom record views
(https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md).
On create, the field’s `selected` is still `[]` when the record view
handles `change:contactType`. Non-silent `setOptionList` rebuilds
`selected` from that empty list (to drop Employee while Volunteer is
chosen) and `trigger('change')` writes `[]` back. Colleague / Member /
Other do not change the option list, so they already stuck. Edit of an
existing type often already had a non-empty `selected`.

**Rejected:** Dynamic Logic **options** that depend on the same field —
documented side effects
(https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md).
Core also uses `setOptionList`; same wipe.

## Unresolved

None for planning. Prod apply, live mail, and duplicate-email merge stay owner-named.
