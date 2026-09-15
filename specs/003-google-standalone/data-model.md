# Data Model: Standalone Google calendar; retire WorkflowEngine

**Feature**: `003-google-standalone`  
**Date**: 2026-09-15

No new Espo entity types. Behavior changes on existing GoogleIntegration
records and on **which** CRM entity types receive Google fields.

## CalendarDateSource (existing)

Operator catalog: which entity type + date field Google Calendar may use.

| Field (logical) | Rules |
|-----------------|-------|
| target entity type | MUST be a live Espo entity (`scopes.{Type}.entity === true`) at apply time. Unknown/disabled → skip, do not fatal |
| start date field | MUST be a `date` or `datetime` field on that type’s `entityDefs` (not a frozen Meeting-only list) |
| active | Only active rows provision Google fields/hooks/layouts |
| date key / label | Unchanged |

**Validation**: Saving a date source for a type that is not an entity MUST
fail with a clear admin error. Rebuild MUST ignore stale rows whose type
disappeared.

## CalendarTemplate (existing)

Reusable Google event text per target entity type.

| Rule | Detail |
|------|--------|
| target entity type | Same as date source: any live entity, not only Meeting/Call/Task/Opportunity |
| placeholders | Resolved from **that** type’s fields (+ related), not a hardcoded Opportunity-only map |

Default seed templates (Meeting/Call/Task/Opportunity) MAY be created **only**
when those scopes exist.

## Google-capable record (derived)

Not a table. Any entity type that:

1. Is a live entity in metadata, and
2. Has at least one active CalendarDateSource.

Those types receive the existing Google per-date fields (`saveToGoogleCalendar`,
date source list, event settings, share links, …) via AdditionalBuilder.

## WorkflowEngine records (retired on this product)

| Type | After this feature |
|------|--------------------|
| WorkflowDefinition | Not installed; no admin UI |
| WorkflowConditionState | Not installed |

Uninstall uses Espo extension uninstall (official scripts) before the module
directory is deleted from git, so leftover tables follow Espo uninstall
behaviour (extension uninstall does not always drop tables — document in
implement: leave unused tables unless owner asks a hard rebuild). MUST NOT
invent a custom DROP script without approval.

## NonprofitEspocrm

No schema change. Sibling AfterInstall MUST stop calling WorkflowEngine
installer. Google installer stays optional `class_exists`.
