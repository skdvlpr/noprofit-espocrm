# Contract: Entity catalog from Metadata

**Feature**: `003-google-standalone`

Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/scopes.md
and https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-metadata.md

## Catalog

An entity type is Google-attachable when:

1. `metadata.scopes.{Type}.entity === true`, and
2. An active CalendarDateSource exists for `{Type}` (after rebuild), and
3. The date field on the source exists on `{Type}` entityDefs as `date` or
   `datetime`.

## Seeds

Installer/default date sources and default CalendarTemplates for Meeting,
Call, Task, Opportunity run **only if** step 1 is true for that type.

## Closed list ban

`CORE_ENTITY_TYPES` / `PULL_ENTITY_TYPES` MUST NOT be the only way a type
can participate. Pull-from-Google MAY keep an activity-type preference list
**intersected** with metadata (skip missing).

## Unknown types

A custom type with a date field MUST be attachable by creating a
CalendarDateSource + rebuild, with no GoogleIntegration code change that
names that type.
