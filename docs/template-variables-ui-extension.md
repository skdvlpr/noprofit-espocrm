# EXT — TemplateVariablesUI (planned)

Optional EspoCRM extension that **replaces** the shared native Segnaposti-style
inserter (`nonprofit-espocrm:lib/template-variable-inserter`) with the searchable
pill side-panel formerly used only by GoogleIntegration.

## Why

- WorkflowEngine, EmailTemplate, and Google Calendar templates all need field
  placeholders.
- Short-term unification uses **native dropdowns + Insert** (this repo, 2026-08).
- Long-term UX upgrade should be a **separate ZIP** so CRM / Workflow / Google
  sync do not own presentation.

## Package layout (target)

```
template-variables-ui/
├── manifest.json
├── scripts/AfterInstall.php
└── files/
    ├── custom/Espo/Modules/TemplateVariablesUI/
    └── client/custom/modules/template-variables-ui/
        ├── src/lib/variable-panel.js          ← moved from google-calendar-variable-panel
        ├── src/lib/template-variables.js
        └── src/views/fields/template-text.js  ← overrides nonprofit-espocrm template-text
```

## Install order

Espo → GoogleIntegration → WorkflowEngine → NonprofitEspocrm → **TemplateVariablesUI** (optional).

## Migration notes

- Source of the beautiful panel today (deprecated for direct use):
  `client/custom/modules/google-integration/src/lib/google-calendar-variable-panel.js`
- Shared native API to override:
  `client/custom/modules/nonprofit-espocrm/src/lib/template-variable-inserter.js`
  `client/custom/modules/nonprofit-espocrm/src/views/fields/template-text.js`

## Status

Design only — not packaged yet.
