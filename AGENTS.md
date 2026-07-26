# SafehouseCrm Module Rulebook

**EspoCRM Version:** 9.3.6 | **Module:** custom/Espo/Modules/NonprofitEspocrm/
**Executor:** Antigravity AI | **Last updated:** 2026-06-28
**Language:** specs/paths/code = English | User communication = Russian

## MANDATORY PRE-TASK PROTOCOL

Before implementing ANY task, executor MUST:

1. Re-read this file in full.
2. Fetch current Notion project page and task page.
3. Read referenced files from the repository (never assume content).
4. Run: **Admin → Repair → Rebuild → Clear Cache** after EVERY metadata change.
5. Never overwrite executor logs in Notion. Append only.
6. **Notion logging (when Notion MCP is available):** Fetch project + task pages; append executor log and deploy notes (never overwrite). Update task status in Notion. **Do this proactively for every implementation/planning milestone without waiting for a separate user request.** Logs MUST be **handoff-ready**: include current state, files changed, verification performed, blockers, and exact next steps so another agent can continue after context compaction or token exhaustion. **Do not** create local markdown files as a Notion substitute. Mark tasks **Done** only when acceptance criteria are met. **NEVER** ask the user to paste executor logs manually into Notion. If Notion write tools are unavailable: (1) call `mcp_auth` for `plugin-notion-workspace-notion`; (2) verify MCP tools folder lists `notion-fetch` / `notion-update-page` (not only `mcp_auth`); (3) retry the write; (4) if tools are still missing after auth, tell the user to reconnect Notion MCP or restart the chat — do **not** offer a copy-paste log workaround.
7. **Git / remote:** Do **not** run `git push` to the remote (and do **not** create a PR) unless the **user explicitly asked** to push or publish. Prefer local commits only when the user asked to save work; if they gave no git instruction, **ask** before `git commit` as well.
8. **One task per user request (execution scope):** Implement **exactly one** Notion task / one bug / one acceptance slice per user message. If the user lists multiple issues, **append them to Notion** (backlog + ordered queue) and **do not** start the next item until the current one is verified (smoke + manual QA steps) or the user explicitly reprioritizes. **Exception:** launching **parallel subagents** for independent read-only work (explore, CI, security review) is allowed and encouraged when it speeds up the **single** active task — but do not ship code for multiple unrelated fixes in one turn.

9. **Post-fix manual QA gate:** After each bug fix, append a **Manual QA checklist** to the Notion task (English) and wait for user confirmation (or explicit reprioritize) before starting the next queued bug. Do **not** batch unrelated fixes in one turn.

10. **Notion language:** Task names, project titles, epic titles, executor logs, and acceptance criteria in Notion MUST be **English only** (no Russian/Cyrillic in Notion pages). User chat may stay Russian; Notion is the English handoff surface.

## NOTION — PROJECT & TASK TRACKERS (canonical since 2026-06-28)

**Active work** goes only into the **new** databases below. The old Gomercato tracker is **read-only archive** — do not create new tasks there.

| Role | URL | Notes |
| ---- | --- | ----- |
| **Active project (post-launch)** | https://app.notion.com/p/38d8d469d405817cbd23f6cfb3ce32af | **Nonprofit EspoCRM — Post-launch enhancements** — current implementation project |
| **Projects DB** | https://app.notion.com/p/2fb8d469d4058093a291fd990185824d | Create/update **projects** here |
| **Tasks DB** | https://app.notion.com/p/38d8d469d405805589c6000c89f3d3ab | Create/update **tasks** here (`Projects - Tasks`) |
| **Archive project (Phase 1)** | https://app.notion.com/p/34e8d469d4058027af82f2ce986a6448 | **Safehouse CRM** — Gomercato tracker; Status **Done**; executor logs are historical reference only |

**Mandatory rules:**

1. **Every new task** MUST set the **Project** relation to the project it belongs to (never orphan tasks).
2. **Every project page** MUST include: goal/overview, links to **all** related tasks from the Tasks DB, and append-only executor logs.
3. Before implementation: fetch **active project page** + **target task page** from the new DBs (step 2 of PRE-TASK PROTOCOL).
4. When closing a project phase: mark project **Done** in Projects DB; open the next project in the same DB (do not reuse the archive tracker).

## SECTION 1 — PROJECT OVERVIEW

**Module path:** `custom/Espo/Modules/NonprofitEspocrm/`**EspoCRM version:** 9.3.6
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
custom/Espo/Modules/NonprofitEspocrm/
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
  "module": "NonprofitEspocrm",
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

Navigation tabs are managed via `Tools/Installer::runPostInstall()` using `ConfigWriter`. **NEVER** edit `navbar.json` or `data/config.php` directly.

                                                  **CRM block (`$CRM` / Principali):** `Lead` → `Contact` → `Account` → `Opportunity` (F&F) → `Member` → `VolunteerEmployee`.

**Reporting dropdown (`type: group`, NOT `type: divider`):** native Espo group tab with label `$Rendicontazione` — works in **horizontal and vertical** navbars (divider groups are side-navbar only). `itemList` contains **only** reporting entities (`MealCount`, later `AssociationMealCount`). **Never** put `Opportunity` or other CRM scopes in this group.

Label via `Resources/i18n/*/Global.json` → `navbarTabs.Rendicontazione` (IT: **Rendicontazione**, EN: **Reporting**).

```
// Tools/Installer.php — single source of truth
$tabList = $this->reorderCrmNavbarBlock($tabList);
$tabList = $this->reorderReportingNavbarBlock($tabList); // inserts type: group
$configWriter->set('tabList', $tabList);
$configWriter->save();
```

CLI refresh: `ddev exec php bin/reorder-safehouse-tabs.php` (delegates to Installer).

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

- `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/formula/VolunteerEmployee.json` — uses `if {} else if {}` block syntax, direct assignments, `datetime\today`, `ifThenElse`, `number\round`, `entity\isNew` is NOT needed because it uses null checks.
- `custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/formula/MealCount.json` — uses `if (entity\isNew()) {}` block, arithmetic operators, `datetime\dayOfWeek` + `ifThenElse` chain for English day-of-week names (Italian translation lives in `Resources/i18n/it_IT/MealCount.json`).

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
namespace Espo\Modules\NonprofitEspocrm\Hooks\MealCount;

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
  "name": "NonprofitEspocrm",
  "description": "Nonprofit CRM for Safehouse organization",
  "author": "SafehouseCrm Team",
  "version": "1.0.0",
  "acceptableVersions": [">=9.3.0"],
  "releaseDate": "2026-05-10",
  "dependencies": {}
}
```

### PKG-002 — AfterInstall.php required actions

**CRITICAL — two entrypoints, one source of truth.** Espo Extension Manager
runs the ZIP-package `scripts/AfterInstall.php` after copying files. The
in-module `Espo\Modules\NonprofitEspocrm\AfterInstall` is only invoked for
direct in-tree installs (dev workflows). Both **must** delegate to the same
provisioning class so fresh ZIP installs do exactly what dev installs do.

```
// scripts/AfterInstall.php (package root — invoked by Extension Manager)
class AfterInstall {
    public function run(\Espo\Core\Container $container, array $params): void {
        (new \Espo\Modules\NonprofitEspocrm\Tools\Installer())->runPostInstall($container);
    }
}

// custom/Espo/Modules/NonprofitEspocrm/AfterInstall.php (in-tree)
class AfterInstall {
    public function run(\Espo\Core\Application $app): void {
        (new Tools\Installer())->runPostInstall($app->getContainer());
    }
}
```

`Tools\Installer::runPostInstall(Container)` is the single source of truth
for post-install provisioning:

1. Ensure Safehouse domain tabs (`VolunteerEmployee`, `Member`, `MealCount`)
   and supporting tabs (`Account`, `Opportunity`, `Document`) are present
   in `tabList`.
2. Remove `Lead` and `Case` from both `tabList` and `quickCreateList`.
3. Reorder domain entities into the top `$CRM` navbar section after
   `Contact`.
4. Provision canonical roles (`Admin`, `Employee`, `Manager`, `Volunteer`,
   `Member`) and the `Administration` team via `Tools\RoleSetup`.
5. Rebuild metadata so changes are picked up immediately.

Regression smoke: `ddev exec php bin/smoke-installer.php` — asserts all
five items above and that re-running is idempotent.

### PKG-003 — Prohibited in packaging

- No edits to `application/`, `vendor/`, `data/cache/`, `data/config.php`
- No hardcoded credentials, API keys, OAuth secrets
- No symlink hacks
- No asset versioning workarounds

### PKG-004 — Build and release

**SafehouseCrm** (vertical CRM):

```
# bin/build.sh
VERSION=$(cat manifest.json | jq -r '.version')
zip -r dist/nonprofit-espocrm-v${VERSION}.zip \
  custom/Espo/Modules/NonprofitEspocrm/ \
  --exclude "*.git*" --exclude "*.DS_Store"
```

**GoogleIntegration** (universal Google OAuth2 — separate extension, not bundled with Safehouse):

```
# bin/build-google-integration.sh
# → dist/google-integration-v$(jq -r .version custom/Espo/Modules/GoogleIntegration/manifest.json).zip
```

**MANDATORY before each release**: clean-install test on fresh EspoCRM instance.

**GoogleIntegration ZIP must include frontend** (since 2026-05-15): `bin/build-google-integration.sh` copies both
`custom/Espo/Modules/GoogleIntegration/` and `client/custom/modules/google-integration/` into the package.
Without the latter, Admin UI views load as 404 after Extension Manager install.

## SECTION 26 — ESPO EXTENSION ARCHITECTURE (EXT-\*)

Authoritative sources: [Modules (dev)](https://docs.espocrm.com/development/modules/),
[Extensions (admin)](https://docs.espocrm.com/administration/extensions/),
[ext-template](https://github.com/espocrm/ext-template),
forum: [espocrm#2334](https://github.com/espocrm/espocrm/issues/2334) (custom modules → `client/custom/modules/`).

Use this section when splitting features into **separate installable extensions** (SafehouseCrm, GoogleIntegration, future packs).

### EXT-001 — Two trees per extension (runtime vs package)

| Layer | Path (canonical at runtime) | Naming |
| ----- | --------------------------- | ------ |
| Backend (PHP, metadata, hooks) | `custom/Espo/Modules/{ModuleName}/` | CamelCase (`GoogleIntegration`, `SafehouseCrm`) |
| Frontend (AMD views, templates) | `client/custom/modules/{module-hyphen}/` | kebab-case (`google-integration`, `safehouse-crm`) |

**Runtime source of truth:**

- Backend files are edited under `custom/Espo/Modules/{ModuleName}/`.
- Frontend files are edited under `client/custom/modules/{module-hyphen}/`.
- For `GoogleIntegration`, the canonical frontend path is
  `client/custom/modules/google-integration/`.

**NOT loaded at runtime and NOT a source of truth:**

- `custom/Espo/Modules/{Module}/client/modules/...` — legacy/dead mirror path in
  this repo. Espo loader does **not** read it. Do not edit it, do not add new files
  there, and do not rely on it for packaging. If this path exists, treat it as a
  cleanup candidate after verifying no unique files remain.
- `client/custom/src/views/...` without `custom:` AMD prefix — loader resolves bare `views/...` to `client/lib/transpiled/src/` (**404** in dev).

### EXT-002 — AMD / metadata view IDs (Espo 9.3.6)

Loader `_idToPath` (see `client/lib/espo.js`):

| Metadata / `define()` ID | Resolved file |
| ------------------------ | ------------- |
| `views/foo/bar` | `client/lib/transpiled/src/views/foo/bar.js` (bundled core only) |
| `custom:views/foo/bar` | `client/custom/src/views/foo/bar.js` (global override) |
| `{hyphen}:views/foo/bar` | `client/custom/modules/{hyphen}/src/views/foo/bar.js` |
| `module/{hyphen}/views/foo/bar` | same as above (modern docs style) |

**RULE:** Integration metadata `view` / `userView` for a custom module MUST use the **module prefix**:

```json
{
  "view": "google-integration:views/admin/integrations/edit",
  "userView": "google-integration:views/external-account/oauth2"
}
```

Templates: `google-integration:external-account/oauth2` → `client/custom/modules/google-integration/res/templates/...`.

### EXT-003 — ZIP package layout (Extension Manager)

Minimum structure (matches [ext-template](https://github.com/espocrm/ext-template) output):

```
extension-package/
├── manifest.json
├── scripts/
│   └── AfterInstall.php          ← delegates to Espo\Modules\{Name}\Tools\Installer
└── files/
    ├── custom/Espo/Modules/{ModuleName}/   ← full PHP module tree
    └── client/custom/modules/{module-hyphen}/   ← REQUIRED if module has UI
```

**Important:** The ZIP has two trees because Espo runtime has two trees. This
does **not** mean frontend files should be duplicated under
`custom/Espo/Modules/{Module}/client/modules/...`. Build scripts must copy the
canonical backend tree to `files/custom/Espo/Modules/{ModuleName}/` and the
canonical frontend tree to `files/client/custom/modules/{module-hyphen}/`.
They must exclude any legacy backend-embedded frontend mirror.

Install flow: Administration → Extensions → Upload ZIP → Install → **Rebuild + Clear Cache**.

CLI alternative: `php command.php extension --file="path/to/package.zip"` ([docs](https://docs.espocrm.com/administration/commands/#extension)).

**PROHIBITED in ZIP:** editing `application/`, `vendor/`, shipping secrets in `data/config.php`.

### EXT-004 — manifest.json (extension root, not only in-module)

In-repo module manifest: `custom/Espo/Modules/{Module}/manifest.json`.

ZIP root `manifest.json` is copied from that file by build scripts. Required fields:

```json
{
  "name": "GoogleIntegration",
  "version": "1.0.0",
  "acceptableVersions": [">=9.3.0"],
  "releaseDate": "2026-05-15"
}
```

Module order (metadata merge precedence): `custom/Espo/Modules/{Module}/Resources/module.json`:

```json
{ "order": 16 }
```

Higher `order` wins on conflicting metadata keys.

### EXT-005 — AfterInstall (single source of truth)

Same pattern as PKG-002:

1. `scripts/AfterInstall.php` in ZIP → `Tools\Installer::runPostInstall($container)`.
2. In-tree `custom/Espo/Modules/{Module}/AfterInstall.php` → same class (dev installs).

Post-install MUST: seed DB rows if needed, fix `tabList` only via `ConfigWriter` (never hand-edit `data/config.php`), then `DataManager::rebuild()`.

### EXT-006 — Optional module capabilities

| Feature | Location |
| ------- | -------- |
| API routes | `Resources/routes.json` |
| Composer deps | `composer.json` + `Resources/autoload.json` (psr-4 paths) |
| ES modules / bundle | `Resources/module.json`: `"jsTranspiled": true`, `"bundled": true`; `Resources/metadata/app/client.json` scriptList |
| Init script | `client/custom/modules/{hyphen}/lib/init.js` |
| 3rd-party JS lib | Rollup in build + `Resources/metadata/app/jsLibs.json` (ext-template README) |

### EXT-007 — Multiple extensions in one repo

This repo ships **independent** extensions:

| Extension | Backend | Frontend | Build |
| --------- | ------- | -------- | ----- |
| SafehouseCrm | `custom/Espo/Modules/NonprofitEspocrm/` | (core overrides only if needed) | `bin/build.sh` |
| GoogleIntegration | `custom/Espo/Modules/GoogleIntegration/` | `client/custom/modules/google-integration/` | `bin/build-google-integration.sh` |

**RULE:** Do not bundle GoogleIntegration inside Safehouse ZIP. Install order on fresh instance: Espo core → GoogleIntegration (if needed) → SafehouseCrm. **Standalone `safehouse-aurora-themes` ZIP:** only when SafehouseCrm is not installed — see `deploy/DEPLOY.md` (Extension install order and Aurora themes policy).

**GoogleIntegration frontend rule:** edit only
`client/custom/modules/google-integration/`. Do not mirror runtime JS/templates
into `custom/Espo/Modules/GoogleIntegration/client/modules/google-integration/`.
If a change touches GoogleIntegration UI, update the canonical frontend tree and
run `bin/build-google-integration.sh`; the build script is responsible for
putting frontend files into the extension ZIP.

Smokes: `bin/smoke-google-integration.php`, `bin/smoke-installer.php`, `bin/smoke-safehouse.php`, `bin/smoke-kanban-assets.php`.

### EXT-008 — Frontend verification checklist (per extension with UI)

After metadata or client change:

1. `ddev exec php command.php rebuild` (or `bin/dev-rebuild.sh`) — clears cache **and**
   bumps `appTimestamp` via SafehouseCrm `BumpAppTimestamp` rebuild action. Do **not**
   rely on `clear_cache.php` / `rebuild.php` alone for frontend: they update
   `cacheTimestamp` but browsers bust JS/TPL/CSS via `?r={appTimestamp}` only.
2. Normal refresh (F5) is enough once `appTimestamp` changed; hard refresh only if needed.
3. DevTools → Network: custom view URL must be **200**, e.g.  
   `/client/custom/modules/google-integration/src/views/admin/integrations/edit.js`
4. Must **not** 404 on `/client/lib/transpiled/src/views/admin/integrations/google-integration-edit.js`
5. Console: no `Cannot read properties of null (reading 'val')` on Save

**Responsive UI rule:** For all frontend work, always implement adaptive/responsive layouts that remain readable and usable across light/dark themes, desktop/laptop/tablet/mobile widths, and high zoom levels. Avoid hardcoded light-only colors unless there is a strict design requirement.

**UI labeling rule:** User-visible UI text must use translated labels, not code identifiers (camelCase/snake_case). Exceptions are only template placeholders (e.g., `{{dateEnd}}`) and other technically required raw tokens.

**Browser automation policy:** Do not run browser automation by default. Provide
manual browser QA instructions after implementation, and use browser tools only
when the user explicitly asks for browser testing or visual verification.

### EXT-009 — Known extension UI failure modes

| Symptom | Cause |
| ------- | ----- |
| Admin → Integrations blank + spinner | `view` in metadata without module prefix → AMD 404 |
| Client ID/Secret never appear | `enabled` unchecked (by design); or `fieldDataList` empty / template mismatch |
| Save disabled integration crashes | `save()` calls `fetchToModel()` on hidden password fields — call `syncEnabledFromView()` **before** `getFieldsForSave()` (stale `model.enabled` still `true` after user unchecks Abilitato) |
| Integration “always enabled” | Admin save JS crash → no PUT → `integration.enabled` and `config.integrations.GoogleIntegration` never flip to false |
| OAuth `Malformed auth code` | `encodeURI` vs `encodeURIComponent`, COOP + `popup.location`, double token exchange, `redirect_uri` slash mismatch |
| Connect uses `espo-extra.js` | `userView` not loading module view — wrong metadata path |

## SECTION 27 — GOOGLE CALENDAR EXPORT / REMINDERS (GCal-\*)

Authoritative sources: EspoCRM CRM entity metadata (`Meeting`, `Call`, `Task`),
`Espo\Core\FieldProcessing\Reminder\Saver`, Google Calendar Events API.

### GCal-001 — Applicable entities

Use Google calendar export only for records with meaningful date/time semantics:

- Core CRM: `Meeting` (`dateStart`/`dateEnd`, `reminders`), `Call` (`dateStart`/`dateEnd`, `reminders`), `Task` (`dateStart`/`dateEnd`, `reminders` with `dateField: dateEnd`).
- Safehouse: `Opportunity` (labelled Funds & Grants / Fondi e Finanziamenti) for `presentationDate` and `closeDate`. Users can export either date or both; Google link idempotency must be keyed by `sourceDateType` so the same funding record can own two Google events.
- Do **not** add calendar export to purely historical/reporting date entities by default (`MealCount`, birth dates, membership dates, document status dates) unless there is a user-facing reminder use case.

### GCal-002 — Per-user ownership

Each user chooses their own Google sync mode in **External Accounts** (`ExternalAccount.data.calendarSyncMode`).
Admin settings may define global defaults/limits/policies only. Admin settings MUST NOT override an individual user's selected mode except by globally disabling Google integration.

### GCal-003 — Save-to-Google UI

For supported entity edit/detail views, add:

- `saveToGoogleCalendar` bool (label: Save in Google Calendar / Salva in Google Calendar).
- Optional Google reminder controls shown only when `saveToGoogleCalendar = true`.
- A highlighted warning/help block when `saveToGoogleCalendar = true`: saving to Google is possible without a reminder; reminder is optional.
- A "Google" reminder type in UI only when the record is being saved to Google. Do not replace Espo native `Popup`/`Email` reminders.

### GCal-004 — Reminder mapping

Espo native reminders are arrays of `{seconds, type}` where type is `Popup` or `Email`.
Google Calendar reminders are event-level `reminders.overrides[]` with `method` (`popup` or `email`) and `minutes`.
The Google-specific reminder should be stored separately from Espo reminders unless reusing native reminders is explicitly intended by the user.

### GCal-005 — Idempotency and ACL

Google export must be idempotent per user and per source record:

- Store Google event IDs by user + entity type + record ID (not a single global field shared by all users).
- Use `extendedProperties.private` with Espo source keys (`entityType`, `entityId`, `userId`) for deduplication.
- Enforce Espo ACL before every export/update/delete.
- Never expose OAuth tokens to frontend; all Google API calls are server-side via the user's `ExternalAccount`.

### GCal-006 — Per-date settings

When a single EspoCRM record can create multiple Google events (via
`CalendarDateSource` and/or entity-specific date selectors), Google event
settings MUST be scoped per event/date key, not shared globally for the record:

- **Opportunity:** `googleCalendarOpportunityDateList` +
  `googleCalendarOpportunityEventSettings` (legacy field names; keep until unified).
- **VolunteerEmployee:** `googleCalendarDateSourceList` +
  `googleCalendarEventSettings` (same per-date settings field view as Opportunity).
- **Meeting / Call / Task:** shared record-level Google fields in metadata/layout
  unless migrated to per-date settings later.

Rules:

- Store selected dates separately from per-date settings.
- Key per-date settings by `sourceDateType` from `CalendarDateSource`
  (e.g. `main`, `endDate`, `presentationDate`, `closeDate`).
- Each date can have its own reminders, color, location, visibility,
  transparency, and description template override.
- Keep Google event links idempotent by `sourceDateType`.
- **Clean-install policy:** do not keep unused shared Google fields on entities
  that use per-date UI only (no legacy fallback reads in PHP for removed fields).

### GCal-007 — Template variables

Template variables used in Google Calendar descriptions must render scalar,
human-readable values:

- Do not offer raw link variables such as `{{account}}` in pickers if they
  render as objects.
- Offer current entity scalar fields first.
- Offer related entity fields only for links that are actually populated on the
  current record.
- Related fields must be labelled by relation and field, e.g. `(Account) Name`,
  and inserted as path variables such as `{{account.name}}`.
- Backend rendering must resolve link display names and related scalar fields so
  Google Calendar never receives `[object Object]` / `[Object object]`.

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

Legacy Italian table/column rename migrations were one-shot and have been removed from
`bin/` after all environments completed the rename. Fresh installs use English metadata
only. If an ancient DB still has Italian physical names, restore the historical migration
from git history and run it once, then rebuild. Do **not** mass-reset roles via a CLI on production.

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

## SECTION 15 — REST API REGRESSION (explore-espo-endpoints skill)

### REST-FIRST PRINCIPLE

**The `explore-espo-endpoints` skill is the preferred tool for ALL EspoCRM data
operations** — not only for testing, but also for data seeding, fixture setup,
ad-hoc record manipulation, and debugging. This skill serves as the **prototype
for the future EspoCRM MCP server**.

**When to use REST API (via skill) instead of raw PHP / ORM / SQL:**

| Scenario | Use REST API (skill) | Use PHP ORM only if… |
| -------- | -------------------- | -------------------- |
| Create / update / delete records in tests | **YES** — goes through hooks, ACL, validation | Hook-level unit test that must bypass API |
| Seed test fixtures (users, roles, records) | **YES** — `POST /api/v1/{Entity}` | Bulk import of thousands of rows |
| Read entity data for assertions | **YES** — `GET /api/v1/{Entity}/{id}?select=…` | Need internal ORM-only fields |
| Check metadata / field defs | **YES** — `GET /api/v1/Metadata?key=…` | Never; metadata is always available via REST |
| Verify ACL / IDOR | **YES** — switch `X-Api-Key` between users | Never |
| Provisioning (roles, teams, scheduled jobs) | REST when possible; PHP only for `ConfigWriter` | Direct config changes not exposed via API |

**Rules:**

1. **Read the skill first** (`~/.cursor/skills/explore-espo-endpoints/SKILL.md`)
   before writing any test or data script. It documents auth, query semantics,
   pagination, error handling, and all known entity quirks.
2. **Prefer `curl` / HTTP calls** in smoke and test scripts over
   `$entityManager->saveEntity()`. REST calls exercise the full stack (routing →
   ACL → hooks → formula → validation → ORM → DB).
3. **Raw SQL is prohibited** for record CRUD. Use only for schema inspection or
   one-shot migrations where no API equivalent exists.
4. **Improve the skill** when you discover new endpoints, undocumented behaviors,
   or error patterns. The skill is a living document and the foundation for the
   MCP server.
5. **`X-Api-Key` auth** for all automated scripts; never embed user passwords.

Behavioural contract for **EspoCRM 9.x REST** (`/api/v1/…`) matches the Cursor skill
**`explore-espo-endpoints`** (authoritative copy: `~/.cursor/skills/explore-espo-endpoints/SKILL.md`;
in this repo a local symlink may exist at `cursor-skills/` — see `.gitignore`).

### API-TEST-001 — Preconditions

1. **`siteUrl`** must be set in **Administration → Settings** to the public base URL
   (DDEV: `https://<project>.ddev.site`). The web container must reach this URL for
   HTTP self-checks.
2. Canonical roles: created by extension Installer / AfterInstall on fresh instances (not a prod mass-reset CLI).
3. **Authentication for automated scripts:** `X-Api-Key` on a dedicated **`type=api`**
   user with `authMethod = ApiKey` (never embed interactive user passwords in repo
   scripts). HMAC is allowed by Espo but not required for Safehouse smoke scripts.

### API-TEST-002 — Mandatory probe sequence (skill Workflows A + D)

Execute in order when validating API-facing work or regressing completed tasks:

1. **`GET /api/v1/App/user`** with `X-Api-Key` → expect **200**. If **401**, stop: auth
   or key is wrong.
2. Read **`acl.table`** from the JSON: confirm each target entity has a row and note
   `read` / `create` levels (`no`, `own`, `team`, `all`, `yes`). **Never** collapse
   `team` into `all` when documenting capabilities.
3. **`GET /api/v1/Metadata?key=scopes`** → confirm `entity: true` for custom entities.
4. **List smoke:** `GET /api/v1/{EntityType}?select=…&maxSize=…` with **`maxSize` ≤ 200**
   and an explicit **`select`** list (skill hard rule).
5. **Metadata slice:** `GET /api/v1/Metadata?key=entityDefs.{EntityType}` after field
   renames — assert **English** keys in `fields` / enum `options` (Italian only in
   `it_IT` i18n files, not in stored enum keys).
6. **Unauthenticated negative:** `GET /api/v1/App/user` **without** `X-Api-Key` → must
   **not** be 200 (typically **401**).
7. On failure: capture response header **`X-Status-Reason`** and body
   `messageTranslation` / `messageData` (Espo **400** enum errors often include
   `type: valid`).

### API-TEST-003 — ACL / IDOR probes (second API identity)

For **server-side ACL** (not `dynamicLogic` alone), repeat critical reads under a
**constrained** API user (e.g. role **Volunteer**):

- **`VolunteerEmployee`** with `read=own`: `GET` **own** row → **200**;
  `GET` a row assigned to **another** user → **403** (IDOR must fail).
- **`Member`** when role has `read=no`: `GET /api/v1/Member/{id}` → **403**.
- **`MealCount`** with `read=own`: `GET` a row assigned to someone else → **403**.

The repo script **`bin/smoke-espo-rest-catalog.php`** automates the above for
**Admin** + **Volunteer** API users (`smoke_api_catalog`, `smoke_api_volunteer`).
It provisions its own `VolunteerEmployee` seed with `SaveOption::SKIP_ALL` so
`PersonContactSync` does not require a full mailbox graph on the API-only user.

### API-TEST-004 — How this fits other smokes

| Script | Layer |
| ------ | ----- |
| `bin/smoke-espo-rest-catalog.php` | **HTTP REST** + ACL headers (`X-Api-Key`) |
| `bin/smoke-google-integration.php` | **HTTP REST** + `GoogleIntegration` module metadata / DB row |
| `bin/smoke-safehouse.php` | ORM + formulas + scheduled jobs |
| `bin/smoke-contact-sync.php` | ORM + `PersonContactSync` invariants |
| `bin/smoke-installer.php` | Post-install / `tabList` / roles |
| `bin/smoke-lead-restore.php` | Epic 7 — Lead tab + i18n + REST CRUD |
| `bin/smoke-lead-convert.php` | Epic 7 — Lead convert flows + Volunteer ACL |
| `bin/smoke-rendicontazione.php` | Epic 7 — reporting aggregates + export totals layer |
| `bin/smoke-schema-english.php` | ORM + English enum keys after migrations |
| `bin/smoke-theme-assets.php` | Theme asset paths / overrides |
| `bin/smoke-kanban-assets.php` | Kanban client assets |
| `bin/smoke-google-calendar-deep.php` | Google Calendar integration deep smoke |
| `bin/test-gcal-full-lifecycle.php` | GCal E2E lifecycle (manual QA helper) |
| `bin/cleanup-gcal-e2e.php` | Purge GCal E2E test events/links |
| `bin/reorder-safehouse-tabs.php` | Re-run Installer tabList provisioning |
| `bin/smoke-prima-nota-stripe-commission.php` | Prima Nota gross/fee/net formula + Stripe sourced-field lock |
| `bin/migrate-prima-nota-legacy-gross.php` | One-shot: null amountGross → amountGross=amount, fee/%=0 (run after QA, before/with prod deploy) |
| `bin/seed-qa-stripe-donation.php` | Keep mock Stripe PrimaNota row for manual QA |
| `bin/dev-rebuild.sh` | clear_cache + rebuild wrapper |
| `bin/build.sh` / `bin/build-google-integration.sh` | Extension ZIP builds |

**Epic 7 smokes (run together after Safehouse navbar/Lead changes):**

```bash
ddev exec php bin/smoke-installer.php
ddev exec php bin/smoke-lead-restore.php
ddev exec php bin/smoke-lead-convert.php
ddev exec php bin/smoke-rendicontazione.php
```

Run REST smoke after metadata / ACL / field-key changes that affect the API surface:

```bash
ddev exec php bin/smoke-espo-rest-catalog.php
```

### GIT-001 — Commits and pushes (user consent)

- **`git push`** (and opening/updating a **Pull Request**): only after the user **explicitly** requests it (e.g. «запушь», «push», «открой PR»). Otherwise stop at local changes or a local commit and **ask**.
- **`git commit`**: if the user did not mention committing, **ask** whether to commit (and with what message). If they said «закоммить» but not «пуш», commit locally and **do not push** until they confirm.
- **Force-push / destructive git operations**: never without explicit user approval.

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

Create `Resources/metadata/scopes/{EntityName}.json`. Set `"entity": true`, `"module": "NonprofitEspocrm"`. **Without this file the entity does not exist in EspoCRM.** `"type": "Base"` is optional — see ENT-003.

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
- **EXT**: backend `custom/Espo/Modules/{Camel}/` + frontend `client/custom/modules/{hyphen}/` | metadata views use `{hyphen}:views/...` | ZIP includes both trees | see EXT-001–EXT-009
- **SEC**: ACL server-side always | no anon endpoints | test matrix per entity
- **PERF**: no unbounded queries | aggregations via DB GROUP BY | check N+1
- **GIT**: no `git push` / PR without explicit user request | ask before commit if unclear
- **API-REST**: **REST-first** — use skill `explore-espo-endpoints` for ALL record CRUD, test fixtures, assertions, and debugging (not raw SQL/ORM) | `App/user` + `acl.table` first | `select` + `maxSize`≤200 | `X-Status-Reason` on fail | improve skill when gaps found | skill = MCP prototype
- **REF**: working entities to copy from — `Member`, `VolunteerEmployee`, `MealCount` (all SafehouseCrm-modular, English-named).
