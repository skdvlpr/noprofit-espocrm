# 02 — Extensions map (legacy extract)

**Extracted:** 2026-08-31 from repo manifests + Notion notes.

| Module path | Manifest name | Purpose (short) |
|-------------|---------------|-----------------|
| `custom/Espo/Modules/NonprofitEspocrm/` | NonprofitEspocrm | Vertical CRM: Member/VolunteerEmployee/MealCount, PrimaNota, Intervention, FoodParcel, shift planning (ActivityOffer), reporting, Installer tabs, Web Push bits, suite AfterInstall |
| `client/custom/modules/nonprofit-espocrm/` | (frontend) | AMD views/CSS for nonprofit UI |
| `custom/Espo/Modules/GoogleIntegration/` | GoogleCalendarDrive | OAuth2 External Accounts; CRM → Google Calendar export |
| `client/custom/modules/google-integration/` | (frontend) | Admin integration + user OAuth UI |
| `custom/Espo/Modules/WorkflowEngine/` | WorkflowEngine | Admin-configurable workflows (Espo ≥10); not Advanced Pack |
| `client/custom/modules/workflow-engine/` | (frontend) | Workflow admin UI |
| `custom/Espo/Modules/BugTracker/` | BugTracker | BugReport entity + installer |
| `custom/Espo/Modules/SafehouseAuroraThemes/` | SafehouseAuroraThemes | Aurora Light/Dark theme assets |

**Historical note:** VolunteerActivityDispatch was merged into NonprofitEspocrm (~2026-08); do not recreate as a separate extension.

**Packaging:** Suite ZIP via `bin/build.sh`; standalone builders exist per module. Constitution prefers asking before builds and gitignoring builders long-term (future spec).
