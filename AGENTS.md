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
- `Opportunity` — modified core entity (labelled "Grants & Funding" / "Fondi e Finanziamenti" via i18n)
- `VolunteerEmployee` — new entity (Volunteers / Employees)
- `Member` — new entity (Members / Associati)
- `MealCount` — new entity (Meal Count / Conteggio Pasti)
- `Document` — modified core entity

> Entity types, field names, enum option values and PHP namespaces use English.
> Italian wording (e.g. "Volontario", "Associati", "Conteggio Pasti") lives only
> in `Resources/i18n/it_IT/*.json` so the UI keeps its localised text.

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
| `Base`     | Non-person records: MealCount, Opportunity           |
| `BasePlus` | Base + stream/followers                              |
| `Person`   | People records: VolunteerEmployee, Member            |
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
  "module": "SafehouseCrm",
  "stream": false,
  "importable": true,
  "exportable": true,
  "acl": true,
  "aclActionList": ["read", "create", "edit", "delete"],
  "aclLevelList": ["all", "team", "own", "no"]
}
```

**REQUIRED**: `"entity": true` — without it the entity will not appear in UI or API.

**OPTIONAL**: `"type": "Base"` — verified empirically in 9.3.6 that working entities (`Member`, `VolunteerEmployee`) omit it and Espo defaults correctly. Add only if explicitly needed (e.g. `Person`, `BasePlus`, `Event`).

### ENT-004 — Navigation registration

Navigation tabs are managed via `AfterInstall.php` using `ConfigWriter`. **NEVER** edit `navbar.json` or `config.php` directly.

```
// AfterInstall.php
$config = $this->getHelper('config');
$tabList = $config->get('tabList', []);
$toAdd = ['MealCount', 'VolunteerEmployee', 'Member'];
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
"foodUnitPrice": {
  "type": "currency",
  "required": false,
  "default": 1.5,
  "currency": "EUR"
}
```

**PROHIBITED**: `"type": "float"` for monetary values.

### FLD-003 — Enum fields

```
"mealType": {
  "type": "enum",
  "options": ["Breakfast", "Lunch", "Dinner"],
  "default": "Lunch"
}
```

Translated options go in i18n, NOT in `entityDefs`. Code uses English keys; the
Italian-speaking UI gets its strings from `Resources/i18n/it_IT/*.json`:

```
// Resources/i18n/it_IT/MealCount.json
{
  "options": {
    "mealType": {
      "Breakfast": "Colazione",
      "Lunch": "Pranzo",
      "Dinner": "Cena"
    }
  }
}
```

### FLD-004 — Computed/Formula fields

```
"totalAmount": {
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

| **File**            | **Purpose**                  | **Required?** |
| ------------------- | ---------------------------- | ------------- |
| `list.json`         | List view columns            | YES           |
| `detail.json`       | Detail view panels           | YES           |
| `edit.json`         | Edit form panels             | NO — Espo derives the edit form from `detail.json` if missing. Only add when the edit form must differ (e.g. exclude read-only/computed fields). |
| `filters.json`      | Search/filter fields         | YES (modern name) |
| `search.json`       | Search/filter fields (legacy)| NO — use `filters.json` for new entities |
| `listSmall.json`    | Relationship panel list      | NO            |
| `detailSmall.json`  | Quick detail in relationship | NO            |
| `massUpdate.json`   | Mass update fields           | NO            |
| `sidePanels.json`   | Right side panels in detail  | NO            |
| `bottomPanels.json` | Bottom relationship panels   | NO            |

**EMPIRICALLY VERIFIED (9.3.6)**: working entities `Member` and `VolunteerEmployee` ship without `edit.json` and rely on `detail.json`. Adding a custom `edit.json` is fine, but then **every field name and every cell** must be valid — see LAY-003.

### LAY-003 — detail.json minimal structure

```
[
  {
    "name": "Overview",
    "label": "Overview",
    "rows": [
      [{ "name": "name" }, { "name": "mealDate" }],
      [{ "name": "mealType" }, { "name": "portionCount" }],
      [{ "name": "foodUnitPrice" }, false]
    ]
  }
]
```

**CRITICAL — empty cells**: a missing cell in a row MUST be `false`, never `null`. The frontend layout converter (`s.convertDetailLayout` in `espo-main.js`) crashes with `TypeError: Cannot read properties of null (reading 'view')` on `null` cells. Symptom: clicking Create or Modifica shows the list view instead of the form.

**CRITICAL — unknown fields**: every `{"name": "..."}` cell must reference a field declared in `entityDefs.fields`. Stale references silently break the form.

### LAY-004 — list.json minimal structure

```
[
  { "name": "name", "link": true },
  { "name": "mealDate" },
  { "name": "mealType" },
  { "name": "portionCount" },
  { "name": "foodUnitPrice" }
]
```

**CRITICAL — clickable rows**: at least one column MUST have `"link": true`, otherwise rows in the list cannot be clicked to open the record. Convention: put `"link": true` on the first meaningful column (usually `name`, or `data`/`date` if name is auto-generated and may be empty).

### LAY-005 — filters.json minimal structure

File: `Resources/layouts/{EntityName}/filters.json` (modern). Older entities may still ship `search.json` — both are read but `filters.json` is preferred.

```
[
  { "name": "mealDate" },
  { "name": "mealType" }
]
```

Note: in 9.x the modern `filters.json` is a flat array of fields. The older `search.json` envelope (`{"filterList": [...], "boolFilterList": [...], ...}`) is still accepted for backwards compatibility but should not be used for new code.

### LAY-006 — After layout changes

Always run: **Admin → Repair → Rebuild → Clear Cache** Browser hard-refresh (Ctrl+Shift+R) required to see frontend changes.

## SECTION 6 — FORMULA ENGINE RULES (FRM-\*)

### FRM-001 — Formula file location

**CORRECT**: `Resources/metadata/formula/{EntityName}.json`

```
{
  "beforeSaveCustomScript": "if (entity\\isNew() && mealDate == null) {\n    mealDate = datetime\\today();\n}\ntotalAmount = portionCount * foodUnitPrice;"
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

### FRM-003 — Verified syntax (EspoCRM 9.3.6, source: [official docs](https://docs.espocrm.com/administration/formula/) + [function reference](https://docs.espocrm.com/administration/formula-functions/))

**Operators (use directly, NOT functions):**

- Arithmetic: `+`, `-`, `*`, `/`, `%`
- Comparison: `==`, `!=`, `>`, `<`, `>=`, `<=`
- Logic: `&&`, `||`, `!`
- Assignment: `=`
- Null-coalescing: `??`

Example from docs: `amount = product.listPrice - (product.listPriceConverted * discount / 100.0);`

**There are NO `add()`, `subtract()`, `multiply()`, `divide()` functions** — those are operators. Confirmed by source: `application/Espo/Core/Formula/Functions/NumberGroup/` only contains `format`, `abs`, `power`, `round`, `floor`, `ceil`, `parseInt`, `parseFloat`, `randomInt`.

**Control structures (block syntax, since 7.4):**

```
if (CONDITION) { ... } else if (CONDITION) { ... } else { ... }
while (CONDITION) { ... }
```

**Verified function namespaces (9.3.6):**

- **Logic helpers**: `ifThen(COND, CONSEQUENT)`, `ifThenElse(COND, CONSEQUENT, ALTERNATIVE)`, `isEmpty(VALUE)`, `isNotEmpty(VALUE)`, `list(...)`
- **String**: `string\concatenate`, `string\substring`, `string\contains`, `string\pos`, `string\pad`, `string\test`, `string\length`, `string\trim`, `string\lowerCase`, `string\upperCase`, `string\match`, `string\matchAll`, `string\matchExtract`, `string\replace`, `string\split`
- **Datetime** (NOT `date\` — that namespace does not exist): `datetime\today()`, `datetime\now()`, `datetime\format(VALUE)`, `datetime\date(VALUE)`, `datetime\month(VALUE)`, `datetime\year(VALUE)`, `datetime\hour(VALUE)`, `datetime\minute(VALUE)`, `datetime\dayOfWeek(VALUE)`, `datetime\addMinutes`, `datetime\addHours`, `datetime\addDays`, `datetime\addWeeks`, `datetime\addMonths`, `datetime\addYears`, `datetime\diff`, `datetime\closest`
- **Number**: `number\format`, `number\abs`, `number\power`, `number\round`, `number\floor`, `number\ceil`, `number\parseInt`, `number\parseFloat`, `number\randomInt`
- **Entity**: `entity\isNew()` (NOT bare `isNew()`), `entity\isAttributeChanged`, `entity\isAttributeNotChanged`, `entity\attribute`, `entity\setAttribute`, `entity\clearAttribute`, `entity\sumRelated`, `entity\countRelated`, `entity\isRelated`, `entity\getLinkColumn`, `entity\addLinkMultipleId`, `entity\removeLinkMultipleId`, `entity\hasLinkMultipleId`, `entity\setLinkMultipleColumn`
- **Direct attribute access**: `fieldName = value;` and `$var = fieldName;` work — no need to call `entity\setAttribute('fieldName', value)` or `entity\attribute('fieldName')` unless the attribute name is dynamic.

**Verified day-of-week**: `datetime\dayOfWeek(date)` returns **0..6** with **0 = Sunday**, 1 = Monday, ..., 6 = Saturday (confirmed in `application/Espo/Core/Formula/Functions/DatetimeGroup/DayOfWeekType.php` via Carbon `isoFormat('d')`).

**Common pitfalls (learned from MealCount debug session, 2026-05-10):**

- ❌ `add(adulti, minori)` → ✅ `adulti + minori`
- ❌ `multiply(a, b)` → ✅ `a * b`
- ❌ `date\today()` → ✅ `datetime\today()` (`date\` namespace does not exist)
- ❌ `isNew()` (bare) → ✅ `entity\isNew()`
- ❌ Empty cell `null` in detail.json → ✅ `false`
- ❌ Unbalanced parentheses in nested `ifThenElse` chains — count opens vs closes programmatically before saving.
- ❌ Marking a field `required: true` and relying on formula to fill the default — required validation runs **before** formula. Either drop `required` and let the formula fill it, or expose the field on the edit form so the user provides it.

### FRM-004 — Time-based automation

For auto-status changes triggered by date (e.g. Member.status flips to `Inactive` when `leaveDate` passes): Use a **Scheduled Job** with the canonical `SyncMemberStatus` / `SyncVolunteerEmployeeStatus` runner. Formula alone is insufficient for time-based triggers.

### FRM-005 — Verified working entities (use as templates)

Reference these files when in doubt — they are confirmed to work in 9.3.6:

- `custom/Espo/Modules/SafehouseCrm/Resources/metadata/formula/VolunteerEmployee.json` — uses `if {} else if {}` block syntax, direct assignments, `datetime\today`, `ifThenElse`, `number\round`, `entity\isNew` is NOT needed because it uses null checks.
- `custom/Espo/Modules/SafehouseCrm/Resources/metadata/formula/MealCount.json` — uses `if (entity\isNew()) {}` block, arithmetic operators, `datetime\dayOfWeek` + `ifThenElse` chain for English day-of-week names (Italian translation lives in `Resources/i18n/it_IT/MealCount.json`).

**Debugging workflow (mandatory after every formula change):**
1. Edit formula JSON.
2. Validate JSON syntax (`python -m json.tool` or `jq`).
3. Verify parenthesis balance (count `(` vs `)` programmatically).
4. Rebuild + Clear Cache (`ddev exec php clear_cache.php && ddev exec php rebuild.php` or Admin UI).
5. Hard-refresh browser.
6. Save a record (Modifica → Salva) — formula runs only on save, not on read.
7. **Check `data/logs/espo-{date}.log` immediately**. Formula failures are logged as `CRITICAL: (500) Before-save formula script failed.` Common errors:
   - `Unknown function: X` → wrong namespace or non-existent function. Cross-check against the verified list above.
   - `Incorrect parentheses usage in expression ...` → mismatched `(` and `)`.
   - `Syntax error` → typo, missing `;`, malformed assignment.
8. If no error logged but values are still null on screen, the formula succeeded but assignments did not stick. Check that target field is `storable: true` (default) and not blocked by ACL field-level read-only.

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
      "contractType": {
        "visible": {
          "conditionGroup": [
            { "type": "equals", "attribute": "type", "value": "Employee" }
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
    "mealDate": "Meal Date",
    "mealType": "Meal Type",
    "portionCount": "Portions",
    "foodUnitPrice": "Unit Cost",
    "totalAmount": "Total Amount"
  },
  "labels": {
    "MealCount": "Meal Count",
    "MealCountPlural": "Meal Counts"
  },
  "options": {
    "mealType": {
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
namespace Espo\Modules\SafehouseCrm\Hooks\MealCount;

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
        $entitiesToAdd = ['MealCount', 'VolunteerEmployee', 'Member'];

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

### PERF-002 — MealCount aggregations

Weekly/monthly totals must use DB-level GROUP BY via:

- EspoCRM Report module (preferred, no custom code)
- EntityManager query with aggregate functions

**PROHIBITED**: PHP loop over full `MealCount` dataset.

### PERF-003 — notStorable fields on list views

Before shipping any `"notStorable": true` field visible in list view: Verify it does not cause N+1 queries by checking query log.

## SECTION 14 — DEVELOPMENT WORKFLOW

### After EVERY metadata change:

1. Admin → Repair → Rebuild
2. Admin → Repair → Clear Cache
3. Browser hard-refresh (Ctrl+Shift+R)
4. Check browser console for JS errors
5. Check EspoCRM log: `data/logs/espo.log`

### Upgrading an existing database (Italian → English schema)

If the database was created **before** the English entity/field rename (tables such as
`volontario_dipendente`, `associati`, `conteggio_pasti`), run the one-shot migration **once**
per environment, then rebuild:

1. `ddev exec php bin/migrate-rename-italian.php` (idempotent; renames tables/columns, updates polymorphic `*_type` values, rewrites `role.data` keys, fixes `tabList`, prunes stale scheduled jobs)
2. `ddev exec php clear_cache.php && ddev exec php rebuild.php`
3. `ddev exec php bin/setup-roles.php`
4. Optional: `ddev exec php bin/reorder-safehouse-tabs.php`

Fresh installs from current module metadata do **not** need the migration script.

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

- Define entity name (CamelCase, e.g. `MealCount`)
- Define entity type (`Base`, `Person`, `BasePlus`, etc.)
- List all fields with types
- List all relationships
- Define source of truth for each computed field
- Define ACL matrix (who can read/create/edit/delete)

### Step 2: entityDefs

Create `Resources/metadata/entityDefs/{EntityName}.json`. Include all fields, links, indexes. Do NOT define entity type here.

### Step 3: scopes

Create `Resources/metadata/scopes/{EntityName}.json`. Set `"entity": true`, `"module": "SafehouseCrm"`. **Without this file the entity does not exist in EspoCRM.** `"type": "Base"` is optional — see ENT-003.

### Step 4: Layouts — MOST COMMON SOURCE OF BUGS

Create directory: `Resources/layouts/{EntityName}/` **NOT** `Resources/metadata/layouts/{EntityName}/`.

Minimum required files: `detail.json`, `list.json`, `filters.json`. `edit.json` is optional (Espo derives the edit form from `detail.json` if absent — see LAY-002).

Sanity-check rules to avoid silent breakage:
- Empty cells in any layout MUST be `false`, never `null` (LAY-003).
- Every `{"name": "..."}` cell MUST reference a field that exists in `entityDefs.fields`.
- `list.json` MUST have at least one column with `"link": true` (LAY-004).
- Use `filters.json` for new entities, not the legacy `search.json` (LAY-005).

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

| **Symptom**                                                   | **Most likely cause**                                                                                                                                |
| ------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| "Create" button click shows the list with `Nessun dato` instead of a form | JS error `Cannot read properties of null (reading 'view') at convertDetailLayout` — there is a `null` cell in `detail.json`/`edit.json`. Replace with `false`. Open DevTools → Console to confirm. |
| List rows are not clickable (cannot open record)              | `list.json` has no column with `"link": true` (LAY-004).                                                                                             |
| Layout empty/not loading                                      | Layouts placed in `Resources/metadata/layouts/` instead of `Resources/layouts/`.                                                                     |
| Fields unnamed in UI                                          | Missing `i18n` entry for that field.                                                                                                                 |
| 403 on all actions                                            | `aclDefs` missing.                                                                                                                                   |
| `(400) field validation failure ... type: required` on POST   | Required field expected to be auto-filled by formula — but required validation runs **before** formula. Drop `required: true` or expose the field in the edit form (FRM-003 pitfalls). |
| `(500) Before-save formula script failed. Unknown function: X`| `X` is not a real Espo formula function. Cross-check the verified list in FRM-003 (e.g. `add` → use `+`, `isNew` → `entity\isNew`, `date\today` → `datetime\today`). |
| `(500) Before-save formula script failed. Incorrect parentheses usage` | Mismatched `(` and `)` in formula — count programmatically before saving.                                                                            |
| Formula silently does nothing (no log, no values)             | Formula runs but assignments don't stick: target field is `notStorable` without `storable: true` override, or blocked by field-level ACL.            |
| Entity not in nav                                             | `scopes` `"tab": false` or `AfterInstall` not run.                                                                                                   |

## QUICK REFERENCE CARD

- **ENT**: `entityDefs` + `scopes` (entity:true, module set) = minimum to exist | `type:"Base"` is OPTIONAL
- **FLD**: `currency` ≠ `float` | `notStorable` for computed | `FieldValidator` for regex
- **LAY**: `Resources/layouts/{E}/` ← CORRECT | `Resources/metadata/layouts/` ← WRONG | empty cells = `false` not `null` | first list column needs `"link": true` | `edit.json` is OPTIONAL — falls back to `detail.json`
- **DEF**: static → `"default"` key | dynamic → formula `beforeSaveCustomScript` (don't combine with `required:true` for the same field)
- **FRM**: `Resources/metadata/formula/{E}.json` | key: `beforeSaveCustomScript` | math via `+ - * /` operators (NOT `add()/multiply()`) | `datetime\today()` (NOT `date\today()`) | `entity\isNew()` (NOT bare `isNew()`) | always check `data/logs/espo-{date}.log` after save
- **ACL**: `aclDefs` BEFORE testing | `dynamicLogic` ≠ security
- **DYN**: `clientDefs` `dynamicLogic` = UI only, always duplicate server-side
- **I18N**: `en_US` mandatory | `it_IT` required | every layout field needs label
- **HOK**: hooks only when formula insufficient | `afterSave` = idempotent
- **PKG**: `manifest.json` + `AfterInstall` rebuild | never touch `application/`
- **SEC**: ACL server-side always | no anon endpoints | test matrix per entity
- **PERF**: no unbounded queries | aggregations via DB GROUP BY | check N+1
- **REF**: working entities to copy from — `Member`, `VolunteerEmployee`, `MealCount` (all SafehouseCrm-modular, English-named).
