# Google Calendar — implementation plan (ordered)

Spec: [CALENDAR.md](./CALENDAR.md)

## Status

| Step | Description | Status |
|------|-------------|--------|
| 0 | OAuth + External Account (admin credentials, per-user Connect) | **Done** |
| 1 | Personal `calendarSyncMode` on External Account + custom user UI + i18n | **Done** |
| 2 | `Tools\IntegrationState::isGoogleIntegrationEnabled()` | **Done** (EventPusher, EventRemover, CalendarSyncRunner) |
| 3 | Record fields + layouts + handlers (Meeting, Call, Task, Opportunity, VolunteerEmployee) | **Done** |
| 4 | Immediate push when `saveToGoogleCalendar` (`EventPusher` + hooks) | **Done** |
| 5 | Delete/unlink sync (`EventRemover`, AfterRemove, checkbox off) | **Done** |
| 6 | Scheduled job `GoogleIntegrationSyncCalendar` per `calendarSyncMode` | **Done** |
| 7 | Smokes: `bin/smoke-google-integration.php`, `bin/smoke-google-calendar-deep.php` | **Done** |

## Notion 3.4 UX tails (2026-05-27)

| ID | Item | Status |
|----|------|--------|
| 3.4a | Admin → Integrations card links to `#CalendarDateSource` / `#CalendarTemplate` | **Done** |
| 3.4d | Canonical title `{name} - {label}` via `GoogleCalendarEventTitle` | **Done** |
| 3.4e | All-day: hide `dateStartDate`/`dateEndDate` in picker; server rejects companions | **Done** |
| U6 | Opportunity uses `googleCalendarDateSourceList` / `googleCalendarEventSettings` only | **Done** |

## Backlog (not blocking one-way export)

| ID | Item |
|----|------|
| U7 | Explicit date-source UI for Meeting/Call/Task |
| FR-1–3 | Attendees, external guests, Google Meet on Call |
| **GCal-CAL-1** | **Calendar picker UI:** expose `googleCalendarId` on record panel + per-date `calendarId` in `google-calendar-opportunity-event-settings` (field exists in metadata, **missing from layouts**; API `GET /GoogleIntegration/calendar/google-calendars` works) |
| **GCal-CAL-2** | **Dedicated calendar per date source (optional, admin-only):** global master switch on **Admin → Integrations → Google** + per-row policy on **`CalendarDateSource`** (admin UI only). Modes: `primary` / `user_pick` / `auto_dedicated`. Auto-create `CRM - {label}` only when admin enabled globally **and** for that date-source row. Users cannot toggle auto-create or change which entities use it. |
| **GCal-CAL-3** | **Default color per date source (admin-only):** `defaultColorId` on `CalendarDateSource` (admin edits); users may override per record in event settings |
| **GCal-CAL-4** | **GetGoogleCalendars UX:** surface `connected: false` + error reason in UI; do not silently show only `primary` when OAuth/list fails |
| **GCal-CAL-5** | **REST push policy (document):** API-user `PUT` does not trigger Google sync (`EventPusher` skips `isApi()`); UI save or explicit job only |

### GCal-CAL-2 design notes (feasibility: **yes**, **admin-only policy**)

**Who configures what**

| Setting | Where (admin only) | Who reads at export |
|---------|-------------------|---------------------|
| Master: enable auto-create dedicated calendars | `Integration` fields (`googleCalendarAutoCreateEnabled`) | `EventPusher` / `CalendarProvisioner` |
| Per date-source: routing mode | `CalendarDateSource` row (`calendarRoutingMode`) | `DateSourceProvider` → `EventPusher` |
| Per date-source: dedicated calendar name | `CalendarDateSource` (`dedicatedCalendarName`, default `CRM - {label}`) | provisioner |
| Per date-source: default color | `CalendarDateSource` (`defaultColorId`) | `EventPusher` |
| User override: pick calendar | Record `googleCalendarId` / per-date `settings.calendarId` | only when mode = `user_pick` |

**`calendarRoutingMode` (per `CalendarDateSource` row, admin edits only)**

| Mode | Behaviour |
|------|-----------|
| `primary` | Always Google `primary` (current default) |
| `user_pick` | User selects from loaded calendar list (GCal-CAL-1) |
| `auto_dedicated` | Auto-create/find calendar by admin-defined name; **only if** global `googleCalendarAutoCreateEnabled` is true |

**Security**

- Auto-create runs server-side in `CalendarProvisioner`; **ignore** client JSON that tries to set routing mode or auto-create flags.
- `CalendarDateSource` ACL stays admin-oriented; regular users only see record-level picker when admin set `user_pick`.
- OAuth scope `calendar` already allows `calendars.insert`.

**Runtime**

- Google API: `calendarList.list` (find by `summary`) + `calendars.insert` (create).
- Idempotency: cache `{userId, targetEntityType, sourceDateType} → calendarId` in user's `ExternalAccount.data.calendarIdMap` (or link metadata).
- Naming: `CRM - {CalendarDateSource.label}` — never PHP hardcode per entity.

## Entity list

- `Meeting`, `Call`, `Task` — shared Google fields + `CalendarDateSource:main`
- `Opportunity` — per-date list + per-date settings (`googleCalendarDateSourceList`, `googleCalendarEventSettings`)
- `VolunteerEmployee` — per-date only (`googleCalendarDateSourceList`, `googleCalendarEventSettings`); no shared reminder fields
