# Quickstart: Standalone Google calendar; retire WorkflowEngine

**Feature**: `003-google-standalone`  
**Purpose**: Prove US1–US4 without a production deploy.

## Prerequisites

- DDEV up (`ddev describe` / `ddev start`)
- Constitution Read-root Espo docs clone present
- Do **not** use host PHP

## After implement (expected)

1. WorkflowEngine directories and smokes gone from the repo.
2. `ddev exec php command.php rebuild` succeeds.
3. Google client has no `nonprofit-espocrm:` requires.

## Validation commands (DDEV)

```bash
# No Google → nonprofit AMD coupling
rg 'nonprofit-espocrm:' client/custom/modules/google-integration && echo FAIL || echo PASS

# WF not in product tree
test ! -d custom/Espo/Modules/WorkflowEngine && echo PASS

# Rebuild
ddev exec php command.php rebuild

# Google smoke (must fail on coupling)
ddev exec php bin/smoke-google-integration.php
```

## Manual (local UI)

1. Google calendar template text: placeholder insert works on Safehouse
   (nonprofit still installed).
2. Administration: no WorkflowEngine.
3. (Optional stock-Espo check) If a second DDEV/vanilla tree exists: Google
   ZIP/module alone still opens placeholder screens.

## Out of scope here

- Production uninstall/push
- F-016 PrimaNota Role matrix
- BugTracker / themes
