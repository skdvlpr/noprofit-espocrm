# Contract: Google calendar UI is standalone

**Feature**: `003-google-standalone`

## MUST

- Google calendar client modules load using only `google-integration:` (or
  Espo core `views/` / `ui/`) prefixes.
- Placeholder insert on Google screens works when
  `client/custom/modules/nonprofit-espocrm/` is absent.
- Combined suite: one helper per field (no double inserter).

## MUST NOT

- `define(..., ['nonprofit-espocrm:…'], …)` in
  `client/custom/modules/google-integration/`.
- Require WorkflowEngine AMD paths (none exist today; keep it that way).

## Check

`rg "nonprofit-espocrm:" client/custom/modules/google-integration` → no
matches. Smoke fails if any remain.
