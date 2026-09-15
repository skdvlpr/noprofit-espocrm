# EXT — TemplateVariablesUI (planned)

Optional EspoCRM extension that **replaces** native Segnaposti-style
inserters (`nonprofit-espocrm:lib/template-variable-inserter` and
`google-integration:lib/template-variable-inserter`) with the searchable
pill side-panel formerly used only by GoogleIntegration.

## Why

- EmailTemplate, Google Calendar templates, and nonprofit template-text
  fields need field placeholders.
- Short-term unification uses **native dropdowns + Insert** (this repo).
- Long-term UX upgrade should be a **separate ZIP** so CRM / Google
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

Espo → GoogleIntegration → NonprofitEspocrm → **TemplateVariablesUI** (optional).

## Migration notes

- Source of the beautiful panel today (deprecated for direct use):
  `client/custom/modules/google-integration/src/lib/google-calendar-variable-panel.js`
- Native APIs to override:
  `client/custom/modules/nonprofit-espocrm/src/lib/template-variable-inserter.js`
  `client/custom/modules/google-integration/src/lib/template-variable-inserter.js`
  `client/custom/modules/nonprofit-espocrm/src/views/fields/template-text.js`

## Status

Design only — not packaged yet.
