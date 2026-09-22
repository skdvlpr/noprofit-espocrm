# Implementation Plan: Contact multi-type, Lead convert + user, Contact email unique

**Branch**: `004.3-contact-types-lead-convert` | **Date**: 2026-09-18 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004.3-contact-types-lead-convert/spec.md` plus owner plan note: Contact email uniqueness must be **as hard as User**, using native Espo mechanisms where they suffice.

## Summary

A Contact may hold **Volunteer+Member** or **Employee+Member** (only those mixes). Volunteer and Employee stay mutually exclusive. Member uses the 004.2 create-user **review** flow. Lead has a **single** type (Volunteer / Member / Generic) and the same extra person fields; native Convert copies same-named same-type fields onto Contact. Convert also offers Crea utente CRM with the same review (Cancel = Lead unchanged). Contact email is unique among Contacts (hard validator), while a linked Contact+User pair may share one address.

Path split (constitution I):

| Behaviour | Path | Why | Rejected |
|-----------|------|-----|----------|
| Type visibility / panels | **Metadata** Dynamic Logic `arrayAnyOf` | Native field logic | Custom CSS/JS show-hide |
| Same-named Lead→Contact fields | **Native ConvertService** copies type (`contactType` multiEnum) | Same name + type copies automatically | Fork `ConvertService` |
| Lead volunteer/member extras | **Not on Lead** — fill on convert Contact (`detailConvert`) | Owner: type only on Lead | Extra field panels on Lead |
| Illegal type sets | **Code** field validator | Must run on mass-update/CLI; Formula `exception\throwInvalid` is logged only, not UI | Formula-only; skippable duplicate |
| Admin-only Volunteer/Employee | **Code** existing Contact beforeSave hook, array-aware | ACL is not Dynamic Logic | Role-named “Admin” |
| Unique Contact email | **Code** native FieldValidator + `duplicateWhereBuilder` (004.2 User pattern) | Stock duplicate dialog is skippable; convert even calls `skipDuplicateCheck` | Formula `throwDuplicateConflict`; Entity Manager duplicate list alone |
| Role add/remove on type change | **Code** hook | Need Role by **name**, not hardcoded IDs; Formula `record\relate` needs IDs | Formula with Role UUID |
| Convert + review + cancel-nothing | **Code** Lead convert view in `client/custom` | Stock Convert POSTs immediately | Edit `application/` ConvertService |
| Enum → multi-value type | **Metadata** multiEnum + one-shot copy | Native field type | Second bool flags |
| Hide opposite personnel type on the picker | **Code** Contact edit view; native `setOptionList(options, true)` after copying `selected` from the model | Non-silent `setOptionList` rebuilds a stale empty `selected` and writes `[]` on create | Dynamic Logic options on the **same** field (docs: side effects) |

MUST NOT edit `application/` or core JS. Local DDEV until the owner names prod.

## Technical Context

**Language/Version**: PHP 8.4, EspoCRM 10.0.3, AMD JS in `client/custom/modules/nonprofit-espocrm/`. Local PHP **DDEV only**.

**Primary Dependencies**: Espo Metadata (entityDefs, clientDefs, logicDefs/clientDefs dynamicLogic, recordDefs, selectDefs, app/layouts), native Multi-Enum, Dynamic Logic, native Lead Convert, FieldValidation, Duplicate WhereBuilder, Hooks, ORM EmailAddress relation, Roles link-multiple.

**Storage**: MariaDB via ORM. No new entity types. `Contact.contactType` becomes multiEnum (json array + `storeArrayValues`). Lead `contactType` multiEnum `maxCount: 2` (Volunteer, Employee, Member, Generic). Volunteer/member extras are filled on convert Contact, not on Lead. Leftover User columns stay until a later owner-named hard rebuild.

**Testing**: DDEV PHPUnit (type legality, filters `arrayAnyOf`, email unique Contact, convert field map unit where possible, role sync). Owner UAT for forms + convert. Live volunteer mail Skip until named.

**Target Platform**: Local DDEV `https://nonprofit-espocrm.ddev.site`.

**Project Type**: EspoCRM module NonprofitEspocrm.

**Performance Goals**: One extra ORM query on Contact email save (same as User). Convert remains one Lead action + optional User create.

**Constraints**: MUST NOT edit core. MUST NOT skippable-only unique email. MUST NOT persist Contact/User on convert Cancel. MUST NOT create new Contacts/Users on prod in this spec. MUST NOT `rebuild --hard` as part of type migrate.

**Scale/Scope**: Contact + Lead metadata/layouts/i18n, Contact edit + Lead convert JS, validators, primary filters, UserContactProfileSync array types, role sync hook, one-shot type-value copy command.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I Official-docs / native-first | fields multi-enum; dynamic-logic; convert Lead; duplicate-check; field validators; formula array\includes; hooks; modules | PASS — see research |
| II Extensions only | NonprofitEspocrm + `client/custom` only | PASS |
| III One active spec | FEATURE_DIR `004.3`; amendment of 004.2 | PASS |
| IV Doc-backed planning | GitHub blob cites in this plan + research/contracts | PASS |
| V Docs beat whim | Unique email uses native validator framework, not a skippable dialog (owner: same hardness as User) | PASS |
| VI Secrets | No new secrets | PASS |
| VII Safe deploy | Local DDEV; prod Skip | PASS |
| VIII Git | Ask before commit; never push unless asked | PASS |
| IX Builders vs ZIPs | No new ZIP required | PASS |
| X Rebuild | `ddev exec php command.php rebuild` after metadata; **soft** | PASS |
| XI Migrations | Enum→multiEnum copy **before** relying on array filters; existing duplicate Contact emails listed, not auto-merged | PASS |
| XII Legacy | Extends 004.2 review; does not reopen User-first volunteering | PASS |
| XIII New tech | None | PASS |
| XIV Tests | PHPUnit + owner UAT | PASS |
| XV Models | Score at `/speckit-tasks` | PASS |
| XVI Progress | `.specify/progress/030-…` | PASS |
| XVII Communication | Chat RU; artifacts EN | PASS |
| XVIII DDEV ↔ prod | Local DDEV | PASS |
| XIX Native fields | multiEnum + maxCount; displayAsLabel; Lead four options + legal combos | PASS |
| XX Live REST | Not required for design | PASS |

**Post-design re-check:** PASS. Formula rejected for unique email and for user-visible illegal-type errors. Native ConvertService kept. Core files untouched.

## Project Structure

### Documentation (this feature)

```text
specs/004.3-contact-types-lead-convert/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── contact-types.md
│   ├── unique-contact-email.md
│   ├── lead-types-and-convert.md
│   ├── convert-create-user.md
│   └── role-sync.md
└── tasks.md                 # /speckit-tasks — not this command
```

### Source Code (repository root)

```text
custom/Espo/Modules/NonprofitEspocrm/
├── Classes/DuplicateWhereBuilders/Contact.php
├── Classes/FieldValidators/Contact/EmailAddress/UniqueAmongContacts.php
├── Classes/FieldValidators/Contact/ContactType/LegalCombination.php
├── Classes/Select/Contact/PrimaryFilters/{Volunteers,VolunteersEmployees,Employees,Associati}.php
├── Classes/ConsoleCommands/CopyContactTypeEnumToMulti.php   # one-shot
├── Hooks/Contact/RestrictPersonnelTypeToAdmin.php           # array-aware
├── Hooks/Contact/SyncRolesToLinkedUser.php                  # new
├── Tools/ContactEmailUniqueness.php                         # or generalize UserEmailUniqueness
└── Resources/
    ├── metadata/entityDefs/{Contact,Lead,User}.json
    ├── metadata/recordDefs/Contact.json
    ├── metadata/clientDefs/{Contact,Lead}.json
    ├── metadata/app/layouts.json            # Lead layouts + Contact detailConvert
    ├── i18n/{en_US,it_IT,ru_RU}/{Contact,Lead,User}.json
    └── layouts/{Contact,Lead}/…

client/custom/modules/nonprofit-espocrm/src/views/
├── contact/record/edit.js                   # Member + combo create-user; type max
└── lead/convert.js                          # wrap crm convert + review

tests/unit/Espo/Modules/NonprofitEspocrm/
```

**Structure Decision**: Extend NonprofitEspocrm only. Prefer module layouts via `metadata/app/layouts.json` `module` (Espo ≥8.1).

## Complexity Tracking

> No constitution violations. Convert two-phase persist is extra UI on a **custom view**, not a core fork.
