# Handoff — Non-Visual Agent (Google Calendar, Backend, Data)

**Date:** 2026-06-11  
**Repo:** https://github.com/skdvlpr/noprofit-espocrm  
**Branch (visual work):** `feat/safehouse-aurora-theme`  
**EspoCRM:** 9.3.6 | **DDEV:** `https://nonprofit-espocrm.ddev.site`  
**Rulebook:** `AGENTS.md` (mandatory read before any task)

---

## 0. Agent split (agreed with user)

| Owner | Scope |
|-------|--------|
| **Visual / Aurora agent** (current) | Safehouse Aurora themes, CSS, kanban UI, quick-view/modal z-index, confirm dialogs, list-inline-edit packaging, aria-labels, responsive glass surfaces, `bin/build.sh` theme assets |
| **This handoff agent** | GoogleIntegration (OAuth, Calendar sync, validation, dead code), backend smokes, Phase 3–4 roadmap items, External Google Calendar events epic, non-UI ACL/API |

**Do not** change `client/custom/css/safehouse-aurora/` or theme metadata unless coordinating with visual agent.

---

## 1. Extension architecture (canonical)

### Two independent ZIP packages

| Extension | Backend | Frontend | Build |
|-----------|---------|----------|-------|
| **SafehouseCrm** | `custom/Espo/Modules/SafehouseCrm/` | `client/custom/modules/safehouse-crm/` + **Aurora CSS** + fonts | `bin/build.sh` |
| **GoogleIntegration** | `custom/Espo/Modules/GoogleIntegration/` | `client/custom/modules/google-integration/` | `bin/build-google-integration.sh` |

**Install order (Safehouse production):** Espo core → GoogleIntegration → SafehouseCrm (always together for Safehouse, but GoogleIntegration must remain standalone-installable).

### AMD view IDs (EXT-002)

- Google: `google-integration:views/...`
- Safehouse: `safehouse-crm:views/...`
- Core extends: `views/record/list` (OK)

### Dead paths (never edit)

- `custom/Espo/Modules/{Module}/client/modules/...` — ignored by Espo loader
- `Resources/metadata/layouts/` — use `Resources/layouts/` only

---

## 2. Current git state (visual branch, 2026-06-11)

Recent commits on `feat/safehouse-aurora-theme`:

| Commit | Summary |
|--------|---------|
| `acf84ca` | Aurora glass confirm dialogs (`.dialog-confirm` only) |
| `c0d8cd7` | GoogleIntegration i18n external field group labels |
| `28c9f2f` | Variable panel search focus + per-link section titles |
| `431d41d` | **Removed** `safehouse-aurora-modals.css` — fixed broken Quick Edit |
| `f47ffd5` | Top-bar icons, slim search, red field labels |
| `1abc851` | Slim glass navbar, dropdowns, side-menu z-index |
| `122bfca` | Unified tab icon palette (`clientDefs` colors) |

**Visual experiments rolled back:** `safehouse-aurora-modals.css` (grid on all `.modal.dialog-record` broke Edit). Do **not** reintroduce global modal grid/glass without scoping to `detail-modal-container` only.

---

## 3. Safehouse Aurora themes in extension ZIP

**Requirement:** Both themes must ship inside SafehouseCrm ZIP.

**Already implemented in `bin/build.sh`:**

1. Theme metadata in module:
   - `custom/Espo/Modules/SafehouseCrm/Resources/metadata/themes/SafehouseAurora.json`
   - `custom/Espo/Modules/SafehouseCrm/Resources/metadata/themes/SafehouseAuroraLight.json`
2. Runtime assets copied to ZIP:
   - `client/custom/css/safehouse-aurora/` (entire tree)
   - `client/custom/fonts/jet-brains-sans/`
3. Global CSS via `Resources/metadata/app/client.json`:
   - `safehouse-aurora-enum-colors.css`, `safehouse-aurora-layout.css`, `kanban-card.css`
4. i18n theme names: `Resources/i18n/*/Global.json` → `themes.SafehouseAurora`, `SafehouseAuroraLight`

**Verify after packaging:**

```bash
bin/build.sh
unzip -l dist/safehouse-crm-v*.zip | grep -E 'safehouse-aurora|SafehouseAurora|jet-brains'
```

Admin → Layout Manager → Themes → both Safehouse themes visible after clean install.

---

## 4. CRITICAL — list-inline-edit NOT in Safehouse ZIP (visual agent fixing)

**Problem:** `custom:views/record/list-inline-edit` lives in `client/custom/src/` but `bin/build.sh` does **not** copy it.

**Metadata references:**

- `clientDefs` Account, Opportunity, Member, VolunteerEmployee, MealCount, AccountWebsite
- `QuickViewDefaultNavigation.php` default list view

**Fix (visual agent):** Move to `safehouse-crm:views/record/list-inline-edit` under module + update metadata + extend smoke.

**You (non-visual):** Do not touch unless list-inline-edit hooks affect Google save handlers.

---

## 5. GoogleIntegration — your primary backlog

### 5.1 HIGH — OAuth admin save (EXT-009)

**File:** `client/custom/modules/google-integration/src/views/external-account/oauth2.js`

**Issue:** `save()` reads stale `model.enabled` before `fetchToModel`. Admin edit already has `syncEnabledFromView()` — replicate pattern.

**Acceptance:** Uncheck Abilitato → save → integration disabled in DB; no JS crash.

### 5.2 HIGH — Validation strings without i18n

**Files:**

- `google-calendar-opportunity-event-settings.js` (lines ~263–276)
- `google-calendar-reminders.js` (lines ~61, 116–129)

**Fix:** Add keys to `Resources/i18n/*/Global.json`, use `this.translate()`.

### 5.3 HIGH — Legacy record-level field fallbacks (GCal-006)

**File:** `google-calendar-opportunity-event-settings.js` → `normalizeItem()`

Reads removed shared fields (`googleCalendarReminderMode`, `googleCalendarLocation`, etc.). Smoke expects per-date-only layout.

**Fix:** Remove legacy reads after confirming no production data depends on them.

### 5.4 HIGH — Dead frontend views in ZIP

| File | define ID | Metadata refs |
|------|-----------|---------------|
| `src/views/calendar/google-calendar-manager.js` | `google-integration:views/calendar/google-calendar-manager` | **0** |
| `src/views/calendar-date-export-panel.js` | `google-integration:views/calendar-date-export-panel` | **0** |

**Action:** Delete or wire via `clientDefs`/calendar UI. `calendar-date-export-panel` uses stale `googleCalendarTemplateId` record-level API.

### 5.5 MEDIUM — DRY / performance

- Duplicate reminder logic: `opportunity-event-settings.js` vs `google-calendar-reminders.js` → shared lib
- Double `date-source-options` API call on same form (date-source-list + event-settings)
- Triple i18n source: entity JSON + `GoogleCalendarCapableEntities.php` + JS fallbacks

### 5.6 MEDIUM — Rename misleading view

`google-calendar-opportunity-event-settings` is used for **all** calendar-capable entities. Consider alias metadata only (rename is large).

---

## 6. Google Calendar — functional status (2026-06-11)

### Implemented ✓

- OAuth + External Account (`GoogleCalendarDrive`)
- Per-user `calendarSyncMode`
- One-way Espo → Google create/update on save (`Meeting`, `Call`, `Task`, `Opportunity`, VolunteerEmployee per-date, etc.)
- Idempotency: `GoogleCalendarEventLink` (user + entity + record + `sourceDateType`)
- Soft-delete restore without duplicate (`EventPusher::findSoftDeletedLink`)
- Per-date settings UI (`googleCalendarEventSettings` jsonArray)
- `CalendarTemplate` + `CalendarDateSource` admin entities
- Variable picker panel (search focus fixed 2026-06-11)
- E2E: `bin/test-gcal-full-lifecycle.php` (169 assertions)

### Not done / partial

- Delete sync when CRM record hard-deleted (partial via `GoogleCalendarDeletedRestorer`)
- Google → Espo pull / continuous sync
- Attendees / external guests / Google Meet on Calls
- In-app Calendar overlay (`google-calendar-manager.js` unwired)
- Visible sync success/error toast in UI
- Task 3.4a–e backlog (admin templates UX, embed settings, event title unify, all-day UX)

### Epic deferred (Task 3.4 Notion spec)

**External Google Calendar events:** entity `ExternalGoogleCalendarEvent`, Google→CRM import, Stream, Planner merge, `googleCalendarEventType` on all calendar-capable entities. Branch: `feat/google-external-events` (not started).

---

## 7. Mandatory verification commands

```bash
# After ANY metadata change:
ddev exec php command.php rebuild

# GoogleIntegration
ddev exec php bin/smoke-google-integration.php

# Safehouse domain
ddev exec php bin/smoke-safehouse.php
ddev exec php bin/smoke-installer.php
ddev exec php bin/smoke-espo-rest-catalog.php

# Google E2E (destructive test data — dev only)
ddev exec php bin/test-gcal-full-lifecycle.php
```

**REST-first:** read `~/.cursor/skills/explore-espo-endpoints/SKILL.md` before API work.

**Browser:** Ctrl+Shift+R after frontend changes; check DevTools for AMD 200s on `client/custom/modules/google-integration/...`.

---

## 8. Notion project context (read append-only logs)

**Project page:** [Safehouse CRM](https://app.notion.com/p/34e8d469d4058027af82f2ce986a6448)

**Key historical logs (do not overwrite):**

- 2026-05-13 — Phase 0–2 audit, English rename, PersonContactSync dedup fix
- 2026-05-15 — GoogleIntegration extension scaffold, BLOCKER-1–3 OAuth
- 2026-05-17–19 — Calendar save/reminder UI + export service
- 2026-05-26 — E2E lifecycle 169/169, inline list edit, Block 2 REST 33/33
- 2026-05-27 — Kanban card redesign, nginx cache fix for `/client/custom/**`
- 2026-06-08 — Kanban narrow-first glass (executor log at top of page content)

**Stale top-of-page spec:** Italian entity names, "single extension only" — ignore. Use `AGENTS.md` + append logs.

**Task pages to read:**

- Task 3.2 — Google OAuth (blocked items may be fixed — verify in browser)
- Task 3.4 — Google Calendar export (manual QA checklist + roadmap)
- Tasks 3.4a–e — backlog under 3.4
- Phase 4 — Reports (blocked: Advanced Pack not in base 9.3.6)
- Phase 5 — Packaging clean-install test
- Phase 6 — Time tracking (specced, no code)

---

## 9. Recommended execution order (non-visual agent)

### Sprint A — Stabilize GoogleIntegration (1–2 days)

1. Fix `oauth2.save()` syncEnabledFromView (H-5.1)
2. i18n validation messages (H-5.2)
3. Run full smoke + manual OAuth reconnect test
4. Remove or wire dead views (H-5.4)

### Sprint B — GCal-006 cleanup (0.5 day)

5. Remove legacy `normalizeItem()` fallbacks (H-5.3)
6. Re-run `smoke-google-integration.php` layout assertions

### Sprint C — Calendar product backlog (per Task 3.4)

7. UI sync feedback toasts
8. Delete sync edge cases audit
9. Task 3.4a–e items (prioritize with user)
10. **Do not start** ExternalGoogleCalendarEvent until user confirms

### Sprint D — Phase 4/5 (product decision)

- Advanced Pack vs custom reports
- `bin/build.sh` + `bin/build-google-integration.sh` clean-install on fresh Espo

---

## 10. Files you will touch most

```
custom/Espo/Modules/GoogleIntegration/
├── Tools/Calendar/EventPusher.php
├── Tools/Calendar/GoogleCalendarCapableEntities.php
├── Classes/Record/GoogleCalendarDeletedRestorer.php
└── Resources/metadata/...

client/custom/modules/google-integration/
├── src/views/external-account/oauth2.js      ← BLOCKER
├── src/views/fields/google-calendar-*.js
├── src/lib/google-calendar-variable-panel.js ← UI done; backend N/A
└── src/handlers/google-calendar/save-to-google-handler.js
```

---

## 11. What NOT to do

- Do not edit `application/` or `vendor/`
- Do not restore `safehouse-aurora-modals.css` without detail-only scoping
- Do not bundle GoogleIntegration inside Safehouse ZIP
- Do not overwrite Notion executor logs — append only
- Do not `git push` unless user explicitly asks
- Do not mark Notion tasks Done until acceptance criteria met

---

## 12. Handoff-ready checklist (copy to Notion when starting)

- [ ] Read `AGENTS.md` + this file
- [ ] Fetch Notion project page + target task page
- [ ] `git checkout` correct branch (likely `main` or `feat/google/...` for calendar work)
- [ ] `ddev start` + rebuild
- [ ] Run smokes (section 7)
- [ ] Append executor log to Notion with: state, files, verification, blockers, next steps

---

*Generated 2026-06-11 after frontend audit + Aurora theme session. Visual agent owns `docs/HANDOFF-visual-agent-2026-06-11.md` companion doc.*
