# SafehouseCrm Module Rulebook

**EspoCRM Version:** 9.3.6 | **Module:** custom/Espo/Modules/SafehouseCrm/
**Executor:** Antigravity AI | **Last updated:** 2026-05-10
**Language:** specs/paths/code = English | User communication = Russian

## MANDATORY PRE-TASK PROTOCOL

Before implementing ANY task, executor MUST:

1. Re-read this file in full.
2. Fetch current Notion project page and task page.
3. Read referenced files from the repository (never assume content).
4. Run: **Admin → Repair → Rebuild → Clear Cache** after EVERY metadata change.
5. Never overwrite executor logs in Notion. Append only.

## SECTION 1 — PROJECT OVERVIEW

**Module path:** `custom/Espo/Modules/SafehouseCrm/`**EspoCRM version:** 9.3.6
**Repository:** https://github.com/skdvlpr/noprofit-espocrm**Branch:\*\* `feat/custom-entity`

### Entities in scope:

- `Account` — modified core entity
- `FondiSovvenzioni` — Opportunity renamed/extended
- `VolontarioDipendente` — new entity (type: Person)
- `Associati` — new entity (type: Person)
- `ConteggioPasti` — new entity (type: Base)
- `Documents` — modified core entity

## SECTION 2 — DIRECTORY STRUCTURE (CANONICAL)

```
custom/Espo/Modules/SafehouseCrm/
├── Controllers/
│   └── {EntityName}.php
├── Hooks/
│   └── {EntityName}/
│       ├── BeforeSave.php
│       └── AfterSave.php
├── Resources/
│   ├── i18n/
│   │   ├── en_US/
│   │   │   └── {EntityName}.json
│   │   └── it_IT/
│   │       └── {EntityName}.json
│   ├── layouts/ ← CORRECT path for all layouts
│   │   └── {EntityName}/
│   │       ├── detail.json
│   │       ├── edit.json
│   │       ├── list.json
│   │       ├── search.json
│   │       └── ...
│   └── metadata/
│       ├── entityDefs/
│       │   └── {EntityName}.json
│       ├── scopes/
│       │   └── {EntityName}.json
│       ├── clientDefs/
│       │   └── {EntityName}.json
│       ├── aclDefs/
│       │   └── {EntityName}.json
│       └── formula/
│           └── {EntityName}.json
├── BeforeInstall.php
├── AfterInstall.php
├── BeforeUninstall.php
├── AfterUninstall.php
└── manifest.json
```

**CRITICAL: There is NO `Resources/metadata/layouts/` path.**
Layouts live ONLY in `Resources/layouts/{EntityName}/`.
Any file placed in `Resources/metadata/layouts/` will be IGNORED by EspoCRM.

## SECTION 3 — ENTITY DEFINITION RULES (ENT-\*)

### ENT-001 — Entity types

Use the correct base type:

| **Type**   | **Use for**                                          |
| ---------- | ---------------------------------------------------- |
| `Base`     | Non-person records: ConteggioPasti, FondiSovvenzioni |
| `BasePlus` | Base + stream/followers                              |
| `Person`   | People records: VolontarioDipendente, Associati      |
| `Company`  | Organisation records                                 |
| `Event`    | Calendar/time-based records                          |

### ENT-002 — entityDefs minimal structure

File: `Resources/metadata/entityDefs/{EntityName}.json`

```
{
  "fields": {
    "name": { "type": "varchar", "required": true },
    "createdAt": { "type": "datetime", "readOnly": true },
    "modifiedAt": { "type": "datetime", "readOnly": true },
    "createdBy": { "type": "link", "readOnly": true },
    "assignedUser": { "type": "link" }
  },
  "links": {},
  "indexes": {}
}
```

**PROHIBITED**: Defining entity type inside `entityDefs`. Type is defined in `scopes`.

### ENT-003 — scopes registration

File: `Resources/metadata/scopes/{EntityName}.json`

```
{
  "entity": true,
  "object": true,
  "tab": true,
  "type": "Base",
  "module": "SafehouseCrm",
  "stream": false,
  "importable": true,
  "exportable": true,
  "acl": true,
  "aclActionList": ["read", "create", "edit", "delete"],
  "aclLevelList": ["all", "team", "own", "no"]
}
```

**PROHIBITED**: Omitting `"entity": true` — entity will not appear in UI or API.

### ENT-004 — Navigation registration

Navigation tabs are managed via `AfterInstall.php` using `ConfigWriter`. **NEVER** edit `navbar.json` or `config.php` directly.

```
// AfterInstall.php
$config = $this->getHelper('config');
$tabList = $config->get('tabList', []);
$toAdd = ['ConteggioPasti', 'VolontarioDipendente', 'Associati'];
foreach ($toAdd as $item) {
    if (!in_array($item, $tabList)) {
        $tabList[] = $item;
    }
}
$config->set('tabList', $tabList);
$config->save();
```

## SECTION 4 — FIELD RULES (FLD-\*)

### FLD-001 — Supported field types

| **Type**           | **JSON key**    | **Notes**                                      |
| ------------------ | --------------- | ---------------------------------------------- |
| Text (short)       | `varchar`       | max 255 chars                                  |
| Text (long)        | `text`          | textarea                                       |
| Rich text          | `wysiwyg`       | sanitized HTML                                 |
| Integer            | `int`           |                                                |
| Decimal            | `float`         |                                                |
| Money              | `currency`      | NEVER use float for money                      |
| Date               | `date`          |                                                |
| Date+time          | `datetime`      |                                                |
| Boolean            | `bool`          |                                                |
| Select             | `enum`          | options array required                         |
| Multi-select       | `multiEnum`     |                                                |
| Link (N:1)         | `link`          |                                                |
| Link (1:N panel)   | `linkMultiple`  |                                                |
| Link (polymorphic) | `linkParent`    |                                                |
| Auto-increment     | `autoincrement` | read-only, unique                              |
| Computed           | any type        | + `"notStorable": true`, value set via formula |

### FLD-002 — Currency fields

```
"costoPasto": {
  "type": "currency",
  "required": false,
  "default": 1.5,
  "currency": "EUR"
}
```

**PROHIBITED**: `"type": "float"` for monetary values.

### FLD-003 — Enum fields

```
"tipoPasto": {
  "type": "enum",
  "options": ["Colazione", "Pranzo", "Cena"],
  "default": "Pranzo"
}
```

Translated options go in i18n, NOT in `entityDefs`:

```
// Resources/i18n/it_IT/ConteggioPasti.json
{
  "options": {
    "tipoPasto": {
      "Colazione": "Colazione",
      "Pranzo": "Pranzo",
      "Cena": "Cena"
    }
  }
}
```

### FLD-004 — Computed/Formula fields

```
"totaleImporto": {
  "type": "currency",
  "notStorable": true,
  "readOnly": true
}
```

Value is assigned in `Resources/metadata/formula/{EntityName}.json`. **PROHIBITED**: Storing computed logic in `entityDefs` directly.

### FLD-005 — Default values

- **Static default**: `"default": "value"` in `fieldDefs`.
- **Dynamic default** (e.g. today's date): use formula `beforeSaveCustomScript`.

### FLD-006 — Validation

- **Required**: `"required": true` in `fieldDefs`
- **Read-only**: `"readOnly": true` in `fieldDefs`
- **Regex/custom validation** (e.g. Codice Fiscale): implement as PHP `FieldValidator` class

**PROHIBITED**: Using `throwError()` in `beforeSaveCustomScript` for validation. `throwError()` is available only in API Script context, NOT in beforeSave formula.

## SECTION 5 — LAYOUT RULES (LAY-\*) ← CRITICAL

### LAY-001 — Correct layout path

- **CORRECT**: `Resources/layouts/{EntityName}/{type}.json`
- **INCORRECT**: `Resources/metadata/layouts/{EntityName}/{type}.json` ← **IGNORED BY ESPOCRM**

EspoCRM module loader reads layouts from `Resources/layouts/`. Files in `Resources/metadata/layouts/` are NOT loaded.

### LAY-002 — Layout types

| **File**            | **Purpose**                  |
| ------------------- | ---------------------------- |
| `list.json`         | List view columns            |
| `detail.json`       | Detail view panels           |
| `edit.json`         | Edit form panels             |
| `search.json`       | Search/filter fields         |
| `listSmall.json`    | Relationship panel list      |
| `detailSmall.json`  | Quick detail in relationship |
| `massUpdate.json`   | Mass update fields           |
| `sidePanels.json`   | Right side panels in detail  |
| `bottomPanels.json` | Bottom relationship panels   |

### LAY-003 — detail.json minimal structure

```
[
  {
    "label": "Overview",
    "rows": [
      [{ "name": "name" }, { "name": "dataPasto" }],
      [{ "name": "tipoPasto" }, { "name": "numeroPorzioni" }],
      [{ "name": "costoPasto", "fullWidth": false }, false]
    ]
  }
]
```

### LAY-004 — list.json minimal structure

```
[
  { "name": "name" },
  { "name": "dataPasto" },
  { "name": "tipoPasto" },
  { "name": "numeroPorzioni" },
  { "name": "costoPasto" }
]
```

### LAY-005 — search.json minimal structure

```
{
  "boolFilterList": [],
  "primaryFilter": null,
  "presetFilterList": [],
  "filterList": [
    { "name": "dataPasto" },
    { "name": "tipoPasto" }
  ]
}
```

### LAY-006 — After layout changes

Always run: **Admin → Repair → Rebuild → Clear Cache** Browser hard-refresh (Ctrl+Shift+R) required to see frontend changes.

## SECTION 6 — FORMULA ENGINE RULES (FRM-\*)

### FRM-001 — Formula file location

**CORRECT**: `Resources/metadata/formula/{EntityName}.json`

```
{
  "beforeSaveCustomScript": "ifThen(isEmpty(dataPasto), setValue('dataPasto', date\\today())); setValue('totaleImporto', multiply(attribute('numeroPorzioni'), attribute('costoPasto')));"
}
```

**PROHIBITED**:

- `entityDefs.formula` key — stale, not supported in 9.x
- `logicDefs` — not a valid EspoCRM metadata key

### FRM-002 — Formula execution context

Formula runs ONLY on:

- Record save (UI form submit)
- API PUT/POST to entity endpoint
- Mass update

Formula does NOT run on:

- Page load
- Field value change (unless dynamicLogic triggers a save)
- Scheduled background refresh

### FRM-003 — Safe verified functions (EspoCRM 9.x)

- **Math**: `add()`, `subtract()`, `multiply()`, `divide()`, `round()`
- **String**: `string\test()`, `string\contains()`, `string\length()`
- **Date**: `date\today()`, `date\now()`, `date\diff()`, `date\addDays()`
- **Logic**: `ifThen()`, `ifElse()`, `isEmpty()`, `isNotEmpty()`
- **Entity**: `attribute()`, `setValue()`

**WARNING - TO BE VERIFIED BY EXECUTOR**: `date\dayOfWeek()` — not confirmed in 9.3.6 formula docs. Verification step: check [EspoCRM Formula Docs](https://docs.espocrm.com/administration/formula/) for datetime functions list.

### FRM-004 — Time-based automation

For auto-status changes triggered by date (e.g. Associati → Inattivo when `dataDimissione` passes): Use **Scheduled Job** with "Execute Formula" action. Formula alone is insufficient for time-based triggers.

## SECTION 7 — ACL RULES (ACL-\*)

### ACL-001 — ACL file

File: `Resources/metadata/aclDefs/{EntityName}.json`

```
{
  "actionList": ["read", "create", "edit", "delete", "stream"],
  "levelList": ["all", "team", "own", "no"],
  "read": "team",
  "create": "yes",
  "edit": "own",
  "delete": "own",
  "stream": "team"
}
```

**RULE**: ACL must be defined BEFORE the entity is tested. Default for new entities without `aclDefs`: admin-only access.

### ACL-002 — Field-level ACL

Sensitive fields (salary, medical) must be restricted at `aclDefs` level. **PROHIBITED**: Using `dynamicLogic` to hide sensitive fields — it is client-side only.

## SECTION 8 — DYNAMIC LOGIC RULES (DYN-\*)

### DYN-001 — clientDefs dynamicLogic

File: `Resources/metadata/clientDefs/{EntityName}.json`

```
{
  "dynamicLogic": {
    "fields": {
      "tipoContratto": {
        "visible": {
          "conditionGroup": [
            { "type": "equals", "attribute": "tipo", "value": "Dipendente" }
          ]
        }
      }
    }
  }
}
```

**CRITICAL**: `dynamicLogic` is CLIENT-SIDE ONLY. Never use it as a security or data-integrity mechanism. All logic must be duplicated server-side in formula or hooks.

## SECTION 9 — LOCALIZATION RULES (I18N-\*)

### I18N-001 — File structure

- `Resources/i18n/en_US/{EntityName}.json` ← MANDATORY
- `Resources/i18n/it_IT/{EntityName}.json` ← Required for Safehouse project

### I18N-002 — i18n JSON structure

```
{
  "fields": {
    "name": "Name",
    "dataPasto": "Meal Date",
    "tipoPasto": "Meal Type",
    "numeroPorzioni": "Portions",
    "costoPasto": "Unit Cost",
    "totaleImporto": "Total Amount"
  },
  "labels": {
    "ConteggioPasti": "Meal Count",
    "ConteggioPastiPlural": "Meal Counts"
  },
  "options": {
    "tipoPasto": {
      "Colazione": "Breakfast",
      "Pranzo": "Lunch",
      "Cena": "Dinner"
    }
  }
}
```

**RULE**: Entity label MUST exist in both `en_US` and `it_IT`. Missing label = empty string in UI (silent failure).

## SECTION 10 — HOOKS RULES (HOK-\*)

### HOK-001 — When to use hooks

Use hooks ONLY when formula cannot achieve the goal:

- Cross-entity writes (creating a related record on save)
- External API calls
- Complex conditional logic requiring PHP services

### HOK-002 — Hook file path

- `Resources/Hooks/{EntityName}/BeforeSave.php`
- `Resources/Hooks/{EntityName}/AfterSave.php`

### HOK-003 — Hook class structure

```
<?php
namespace Espo\Modules\SafehouseCrm\Hooks\ConteggioPasti;

use Espo\Core\Hook\HookInjection;
use Espo\ORM\Entity;

class BeforeSave
{
    public static int $order = 9;

    public function __construct(
        private \Espo\Core\ORM\EntityManager $entityManager
    ) {}

    public function run(Entity $entity, array $options = []): void
    {
        // logic here
    }
}
```

**PROHIBITED**: Raw SQL in hooks. Use EntityManager/Repository only. **RULE**: `afterSave` hooks must be idempotent.

## SECTION 11 — EXTENSION PACKAGING RULES (PKG-\*)

### PKG-001 — manifest.json

```
{
  "name": "SafehouseCrm",
  "description": "Nonprofit CRM for Safehouse organization",
  "author": "SafehouseCrm Team",
  "version": "1.0.0",
  "acceptableVersions": [">=9.3.0"],
  "releaseDate": "2026-05-10",
  "dependencies": {}
}
```

### PKG-002 — AfterInstall.php required actions

```
<?php
class AfterInstall
{
    public function run(\Espo\Core\Application $app): void
    {
        // 1. Add tabs to navigation
        $config = $app->getContainer()->get('config');
        $tabList = $config->get('tabList', []);
        $entitiesToAdd = ['ConteggioPasti', 'VolontarioDipendente', 'Associati', 'FondiSovvenzioni'];

        foreach ($entitiesToAdd as $tab) {
            if (!in_array($tab, $tabList)) {
                $tabList[] = $tab;
            }
        }
        $config->set('tabList', $tabList);
        $config->save();

        // 2. Rebuild metadata
        $app->getContainer()->get('dataManager')->rebuild();
    }
}
```

### PKG-003 — Prohibited in packaging

- No edits to `application/`, `vendor/`, `data/cache/`, `data/config.php`
- No hardcoded credentials, API keys, OAuth secrets
- No symlink hacks
- No asset versioning workarounds

### PKG-004 — Build and release

```
# bin/build.sh
VERSION=$(cat manifest.json | jq -r '.version')
zip -r dist/safehouse-crm-v${VERSION}.zip \
  custom/Espo/Modules/SafehouseCrm/ \
  --exclude "*.git*" --exclude "*.DS_Store"
```

**MANDATORY before each release**: clean-install test on fresh EspoCRM instance.

## SECTION 12 — SECURITY RULES (SEC-\*)

### SEC-001 — Security baseline

- Every API route requires EspoCRM authentication (no anonymous endpoints)
- ACL enforced server-side on every entity action
- `dynamicLogic` is NOT a security mechanism
- WYSIWYG fields: verify EspoCRM native sanitizer is active in config

### SEC-002 — Security test matrix (required per entity)

- [ ] GET `/api/v1/{Entity}` without auth → 401
- [ ] GET `/api/v1/{Entity}` with wrong-role user → 403
- [ ] GET `/api/v1/{Entity}/{otherId}` cross-user IDOR → 403
- [ ] POST with oversized file upload → reject
- [ ] XSS payload in wysiwyg field → sanitized on output
- [ ] Duplicate record submit → idempotent result

## SECTION 13 — PERFORMANCE RULES (PERF-\*)

### PERF-001 — No unbounded queries

All list views must use EspoCRM pagination (`maxSize` parameter). Never load full entity dataset into PHP memory for aggregation.

### PERF-002 — ConteggioPasti aggregations

Weekly/monthly totals must use DB-level GROUP BY via:

- EspoCRM Report module (preferred, no custom code)
- EntityManager query with aggregate functions

**PROHIBITED**: PHP loop over full `ConteggioPasti` dataset.

### PERF-003 — notStorable fields on list views

Before shipping any `"notStorable": true` field visible in list view: Verify it does not cause N+1 queries by checking query log.

## SECTION 14 — DEVELOPMENT WORKFLOW

### After EVERY metadata change:

1. Admin → Repair → Rebuild
2. Admin → Repair → Clear Cache
3. Browser hard-refresh (Ctrl+Shift+R)
4. Check browser console for JS errors
5. Check EspoCRM log: `data/logs/espo.log`

### Entity creation checklist:

- [ ] `entityDefs/{EntityName}.json` created
- [ ] `scopes/{EntityName}.json` created with `"entity": true`
- [ ] `layouts/{EntityName}/detail.json` in `Resources/layouts/` (NOT metadata/layouts)
- [ ] `layouts/{EntityName}/edit.json`
- [ ] `layouts/{EntityName}/list.json`
- [ ] `layouts/{EntityName}/search.json`
- [ ] `i18n/en_US/{EntityName}.json` with all field labels
- [ ] `i18n/it_IT/{EntityName}.json` with all field labels
- [ ] `aclDefs/{EntityName}.json`
- [ ] `formula/{EntityName}.json` (if computed fields exist)
- [ ] `clientDefs/{EntityName}.json` (if dynamicLogic needed)
- [ ] Rebuild + Clear Cache
- [ ] Verify "Create" button appears and works
- [ ] Verify layout renders correctly
- [ ] Run security test matrix

## SECTION 25 — NO-FAIL ENTITY CREATION RULEBOOK

This section is the authoritative step-by-step guide for creating any new entity.

### Step 1: Plan

- Define entity name (CamelCase, e.g. `ConteggioPasti`)
- Define entity type (`Base`, `Person`, `BasePlus`, etc.)
- List all fields with types
- List all relationships
- Define source of truth for each computed field
- Define ACL matrix (who can read/create/edit/delete)

### Step 2: entityDefs

Create `Resources/metadata/entityDefs/{EntityName}.json`. Include all fields, links, indexes. Do NOT define entity type here.

### Step 3: scopes

Create `Resources/metadata/scopes/{EntityName}.json`. Set `"entity": true`, `"type": "Base"`, `"module": "SafehouseCrm"`. **Without this file the entity does not exist in EspoCRM.**

### Step 4: Layouts — MOST COMMON SOURCE OF BUGS

Create directory: `Resources/layouts/{EntityName}/` **NOT** `Resources/metadata/layouts/{EntityName}/` Create minimum required files: `detail.json`, `edit.json`, `list.json`, `search.json`.

### Step 5: i18n

Create `Resources/i18n/en_US/{EntityName}.json` and `Resources/i18n/it_IT/{EntityName}.json`. Every field used in any layout MUST have a label entry.

### Step 6: aclDefs

Create `Resources/metadata/aclDefs/{EntityName}.json`. Without this, only admin can access the entity.

### Step 7: Formula (if needed)

Create `Resources/metadata/formula/{EntityName}.json`. Use `beforeSaveCustomScript` key.

### Step 8: clientDefs (if needed)

Create `Resources/metadata/clientDefs/{EntityName}.json`. Use only for UI behavior (dynamicLogic, view overrides). Never for security.

### Step 9: Rebuild

Admin → Repair → Rebuild → Clear Cache. Browser hard-refresh.

### Step 10: Verify

- Navigation tab appears.
- List view loads with correct columns.
- "Create" button opens form.
- Form fields render correctly.
- Save works.
- Security test matrix passes.

### Known failure modes:

| **Symptom**                  | **Most likely cause**                                |
| ---------------------------- | ---------------------------------------------------- |
| "Create" button does nothing | `scopes` missing OR layout in wrong path             |
| Layout empty/not loading     | layouts in `metadata/layouts/` instead of `layouts/` |
| Fields unnamed in UI         | Missing `i18n` entry                                 |
| 403 on all actions           | `aclDefs` missing                                    |
| Formula silently fails       | Syntax error in `beforeSaveCustomScript`             |
| Entity not in nav            | `scopes` `"tab": false` or `AfterInstall` not run    |

## QUICK REFERENCE CARD

- **ENT**: `entityDefs` + `scopes` (entity:true) = minimum to exist
- **FLD**: `currency` ≠ `float` | `notStorable` for computed | `FieldValidator` for regex
- **LAY**: `Resources/layouts/{E}/` ← CORRECT | `Resources/metadata/layouts/` ← WRONG
- **DEF**: static → `"default"` key | dynamic → formula `beforeSaveCustomScript`
- **FRM**: `Resources/metadata/formula/{E}.json` | key: `beforeSaveCustomScript`
- **ACL**: `aclDefs` BEFORE testing | `dynamicLogic` ≠ security
- **DYN**: `clientDefs` `dynamicLogic` = UI only, always duplicate server-side
- **I18N**: `en_US` mandatory | `it_IT` required | every layout field needs label
- **HOK**: hooks only when formula insufficient | `afterSave` = idempotent
- **PKG**: `manifest.json` + `AfterInstall` rebuild | never touch `application/`
- **SEC**: ACL server-side always | no anon endpoints | test matrix per entity
- **PERF**: no unbounded queries | aggregations via DB GROUP BY | check N+1
