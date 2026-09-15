# Contract: WorkflowEngine removed from this product

**Feature**: `003-google-standalone`

Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/extensions.md

## Product

- `custom/Espo/Modules/WorkflowEngine/` and
  `client/custom/modules/workflow-engine/` are **not** in this git root after
  implement.
- Nonprofit `AfterInstall` sibling list does not reference WorkflowEngine.
- Packaging/smokes/tests for WorkflowEngine are gone from this repo.
- Local DDEV: extension uninstalled (if it was installed) so admin UI has no
  WorkflowEngine.

## Google / Nonprofit

- No runtime require of WorkflowEngine classes.
- Missing WorkflowEngine MUST NOT fatal (after delete, `class_exists` is
  simply false).

## Production

Uninstall on `crm.safehouse.community` is **not** this plan’s automatic
step. Same file deletion ships only when the owner pushes `main` (or
approves a prod uninstall). Call that out at implement UAT.

## Cancelled

Audit F-002 (split WF email modal from Nonprofit) is cancelled for this
repository.
