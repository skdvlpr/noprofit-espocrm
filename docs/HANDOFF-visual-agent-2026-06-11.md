# Handoff — Visual / Aurora Agent

**Date:** 2026-06-11  
**Branch:** `feat/safehouse-aurora-theme`  
**Owner:** Visual agent (this track)

---

## Scope

- `client/custom/css/safehouse-aurora/` — themes SafehouseAurora + SafehouseAuroraLight
- `client/custom/modules/nonprofit-espocrm/` — kanban, quick-view, document list
- SafehouseCrm theme metadata + `app/client.json` cssList
- `bin/build.sh` — must include themes + fonts + safehouse-crm frontend
- **Not in scope:** Google Calendar PHP/JS business logic (see `HANDOFF-non-visual-agent-2026-06-11.md`)

---

## Done (2026-06-11)

| Item | Commit / state |
|------|----------------|
| Quick Edit restored | `431d41d` — removed `safehouse-aurora-modals.css`, z-index in `layout.css` |
| Confirm dialog glass + full border-radius | `acf84ca` |
| Tab icon palette | `122bfca` |
| Navbar glass, dropdowns | `1abc851`, `f47ffd5` |
| Variable panel search (GoogleIntegration UI) | `28c9f2f` — delegated fix but visual-adjacent |

---

## P0 — Must fix next

### 1. list-inline-edit in Safehouse ZIP (CRITICAL)

- **Current:** `client/custom/src/views/record/list-inline-edit.js` → `custom:views/record/list-inline-edit`
- **Target:** `client/custom/modules/nonprofit-espocrm/src/views/record/list-inline-edit.js` → `nonprofit-espocrm:views/record/list-inline-edit`
- **Update:** all `clientDefs` + `QuickViewDefaultNavigation.php`
- **Verify:** unzip `dist/nonprofit-espocrm-v*.zip`, confirm JS path exists; list view loads on clean install

### 2. Kanban dropdown z-index vs Quick View

- `kanban-card.css` line ~640: `z-index: 1400` > modal `1285`
- **Fix:** lower to `1240` or document intentional stacking

### 3. i18n aria-labels in kanban-item.tpl

- Replace hardcoded `aria-label="Dates"` and `"Assignment"` with translated template vars from `kanban-item.js`

---

## P1 — Aurora polish (after P0)

- Optional scoped Quick View glass (detail-modal-container only — never edit-modal grid)
- Glass admin list panels (deferred from 2026-05-27 Notion log)
- Move inline-edit CSS from JS injection to `safehouse-crm/res/css/list-inline-edit.css`
- Extend `bin/smoke-kanban-assets.php` → cover list-inline-edit + quick-view handlers
- Kanban responsive `@media` for narrow columns

---

## z-index reference (do not break)

| Layer | z-index | File |
|-------|---------|------|
| `#navbar` | 1200 | `safehouse-aurora-layout.css` |
| Quick view overlaid | 1250 | `layout.css` |
| Quick edit (active) | 1285 | `layout.css` |
| Confirm backdrop | 1295 | `safehouse-aurora.css` |
| Confirm modal | 1300 | `safehouse-aurora.css` |
| Variable panel | 2200 | `google-calendar-variable-panel.js` |
| Side menu opened | 2160 | `layout.css` |

**Never** raise modal backdrop above drawer without testing full-screen blur regression.

---

## Theme packaging checklist

```bash
bin/build.sh
unzip -l dist/nonprofit-espocrm-v*.zip | grep safehouse-aurora
```

Must contain:

- `files/custom/Espo/Modules/NonprofitEspocrm/Resources/metadata/themes/SafehouseAurora*.json`
- `files/client/custom/css/safehouse-aurora/`
- `files/client/custom/fonts/jet-brains-sans/`
- `files/client/custom/modules/nonprofit-espocrm/`

---

## Verification

```bash
ddev exec php command.php rebuild
ddev exec php bin/smoke-kanban-assets.php
ddev exec php bin/smoke-installer.php
```

**Manual:** Ctrl+Shift+R → Aurora Light/Dark → Account list Quick View → Edit → confirm dialog → Opportunity Kanban.

---

## Notion

Append logs to [Safehouse CRM project](https://app.notion.com/p/34e8d469d4058027af82f2ce986a6448). Never overwrite prior executor entries.
