# 01 — Project context (legacy extract)

**Source:** Notion post-launch project + prior AGENTS prefs (2026-06 → 2026-08).  
**Extracted:** 2026-08-31. Not a full archive.

## Product

- Nonprofit / association CRM for Safehouse (Italy-facing UI: Italian i18n; code/enums English).
- Production: https://crm.safehouse.community (live since ~2026-06-28).
- Repo: https://github.com/skdvlpr/noprofit-espocrm (branch `main`).
- Target stack: **EspoCRM 10.x** (local upgraded; prod may lag — confirm before migrate).

## Phase status (high level)

- **Phase 1 (Done):** NonprofitEspocrm module, GoogleIntegration OAuth/Calendar export, Aurora themes, branding, prod ACL baseline, GitHub Actions → VPS deploy.
- **Post-launch:** Prima Nota / Stripe ledger work, Food Parcel, Intervention, shift planning, Web Push, BugTracker, WorkflowEngine — much shipped on `main`; Notion tracker often lagged code.
- Archive tracker (Gomercato / Phase 1): https://app.notion.com/p/34e8d469d4058027af82f2ce986a6448 (historical only).
- Active Notion project (read-only going forward for logs): https://app.notion.com/p/38d8d469d405817cbd23f6cfb3ce32af

## Process shift (2026-08-31)

Executor handoffs move to `.specify/progress/`. Notion is no longer the agent log surface.
