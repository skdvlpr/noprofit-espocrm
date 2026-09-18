# 021 — Plan 004.1: Repair Contact-first CRM user create

**Date:** 2026-09-17  
**Agent:** Cursor Auto (`/speckit-plan`)

## State

- `specs/004.1-repair-create-crm-user/` has plan, research, data-model,
  contracts, quickstart.
- Active FEATURE_DIR remains `004.1-repair-create-crm-user`.
- 004 create-User UAT Fail explained (after-save modal dies on navigate;
  notStorable dash on detail; detailSmall missing checkbox).
- Design: Aurora `dialog-record` drawer; User POST after Contact id;
  native sendAccessInfo without password; branded templates +
  `safehouseLogo` helper; two-way `emailAddressData` /
  `phoneNumberData` sync.

## Files

- `specs/004.1-repair-create-crm-user/plan.md`
- `specs/004.1-repair-create-crm-user/research.md`
- `specs/004.1-repair-create-crm-user/data-model.md`
- `specs/004.1-repair-create-crm-user/quickstart.md`
- `specs/004.1-repair-create-crm-user/contracts/create-user-side-panel.md`
- `specs/004.1-repair-create-crm-user/contracts/access-info-email.md`
- `specs/004.1-repair-create-crm-user/contracts/email-phone-sync.md`
- `.specify/progress/README.md` (index 021)

## Verification

- Constitution Check PASS (I–XX); Formula vs Code table in research R8.
- Espo docs opened this turn: users-management, passwords, fields,
  dynamic-logic, modal, custom-views, client-defs, app-layouts,
  app-templates, template-custom-helper, hooks, orm, coding-practices,
  metadata, commands.
- Core `Sender` uses `{{siteUrl}}` as change-password URL — logo cannot
  use that field as `img src`.

## Blockers

None for plan. `/speckit-tasks` not started.

## Next steps

1. `/speckit-tasks` when the owner says so.
2. Then `/speckit-implement` (local DDEV).
3. Owner UAT V1–V7; Pass → close 004 and 004.1 together.
4. Then 003 Google UAT. Prod still Skip.
