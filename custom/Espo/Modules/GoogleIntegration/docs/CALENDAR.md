# Google Calendar integration — product & technical specification

**Module:** `GoogleIntegration` (universal; stock EspoCRM + CRM, optional SafehouseCrm)  
**EspoCRM:** 9.3.x  
**Last updated:** 2026-05-20

## Prerequisites (admin, once)

1. **Admin → Integrations → Google (Calendar & Drive):** enabled, Client ID / Secret saved.
2. Google Cloud: Calendar API + Drive API enabled; OAuth consent screen test users (or published app).

## Per user (personal, optional)

Each user opens **Personal → External Accounts → Google (Calendar & Drive)**:

1. **Connect** (OAuth) — required for any Google Calendar use.
2. **Calendar sync mode** (`calendarSyncMode`) — **optional per user**; stored on that user’s External Account row (`GoogleIntegration__{userId}` → `data.calendarSyncMode`).

| Mode | Key | Behaviour |
|------|-----|-----------|
| **Off / no background sync** | `none` | Default. No automatic sync. User may still use **Add to Google Calendar** on individual records. |
| **Variant 1 — Full bidirectional** | `bidirectional` | User’s CRM calendar activities ↔ that user’s Google Calendar. |
| **Variant 2 — CRM → Google only** | `crmToGoogle` | Push CRM changes to Google for this user only. |
| **Variant 3 — Google → CRM only** | `googleToCrm` | Import/update from Google into CRM for this user only. |

- Default for new connections: `none` (user opts in explicitly).
- User A and User B may choose **different** modes.
- Scheduled job `GoogleIntegrationSyncCalendar` (`Jobs\SyncCalendar`, every 5 minutes) processes only users where: integration enabled globally **and** External Account connected (`enabled`) **and** `calendarSyncMode` is not `none`.
- `crmToGoogle` / `bidirectional`: re-pushes CRM records with `saveToGoogleCalendar=true` modified in the last 30 days (max 100 per user per run).
- `googleToCrm` / `bidirectional`: updates `Meeting` / `Call` / `Task` dates from Google events that carry matching `extendedProperties.private` keys (max 100 per run).

**Admin does not set sync mode** — only credentials and global enable/disable. Each user chooses their own mode (or leaves `none`).

---

## Visibility rule

Calendar sync mode, per-record Google fields, and sync job actions apply only when:

- `Integration.GoogleIntegration.enabled === true` (global), **and**
- the current user has a connected External Account (for personal settings / their token), **and**
- server-side checks pass (never rely on `dynamicLogic` alone).

UI on External Accounts and on Meeting/Call/Task/Opportunity/VolunteerEmployee forms is hidden when the global integration is off.

---

## Per-record export (overrides personal sync mode)

On **create/edit** for calendar-capable entities:

| Entity | Calendar source |
|--------|-----------------|
| **Meeting**, **Call**, **Task** | `dateStart` / `dateEnd`, `reminders` |
| **Opportunity** | `closeDate` (core field; IT label *Data Chiusura* via i18n). Entity type is always `Opportunity`; Safehouse only relabels (*Fondi e Finanziamenti*). |
| **VolunteerEmployee** (SafehouseCrm) | `startDate` / `endDate` via `CalendarDateSource` (`main`, `endDate`); UI: `googleCalendarDateSourceList` + per-date `googleCalendarEventSettings` (same model as Opportunity). |

Checkbox **`saveToGoogleCalendar`** (bool):

- When **checked** on save → create/update Google event **immediately** using the **acting user’s** token (same request as save).
- If the HTTP push throws, **VolunteerEmployee** schedules queue job `PushGoogleCalendarEntity` (delay 30s, queue `e0`) to retry with the same user — requires Espo **Jobs** runner (`php command.php job` / cron) to process the queue.

When checkbox is **true**, show per-date settings (not shared reminder/template fields on the record). **VolunteerEmployee** fields: `googleCalendarDateSourceList`, `googleCalendarEventSettings`, optional `googleCalendarId`. At least one date source must be selected when export is enabled (server validation).

**Delete / unlink:** Unchecking `saveToGoogleCalendar` or removing the CRM record deletes linked Google events for the acting user (or all users on record delete). Stale per-date links use each link’s stored `calendarId` (never guessed as `primary`).

**All calendar-capable entities:** UI is provisioned dynamically from active `CalendarDateSource` rows (`saveToGoogleCalendar`, `googleCalendarDateSourceList`, `googleCalendarEventSettings`). No per-entity static metadata or legacy field names.

**Safehouse compatibility:** English field keys in metadata; Italian UI labels in `SafehouseCrm` + `GoogleIntegration` i18n.

---

## Security

- Tokens: per user `ExternalAccount` only; no cross-user use without ACL.
- Personal settings: user edits only their own `GoogleIntegration__{userId}` (admin may view per Espo ACL).

---

## Verification

```bash
ddev exec php bin/smoke-google-integration.php
ddev exec php bin/smoke-espo-rest-catalog.php
# Planned:
ddev exec php bin/smoke-google-calendar.php
```

Manual: two users, different `calendarSyncMode` → background sync differs per user; checkbox on one Meeting still pushes for that user even if mode is `none`.
