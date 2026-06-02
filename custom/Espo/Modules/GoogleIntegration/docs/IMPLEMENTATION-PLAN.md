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

## Backlog (not blocking one-way export)

| ID | Item |
|----|------|
| U6 | Unify Opportunity field names to `googleCalendarDateSourceList` / `googleCalendarEventSettings` |
| U7 | Explicit date-source UI for Meeting/Call/Task |
| FR-1–3 | Attendees, external guests, Google Meet on Call |

## Entity list

- `Meeting`, `Call`, `Task` — shared Google fields + `CalendarDateSource:main`
- `Opportunity` — per-date list + per-date settings (`googleCalendarOpportunity*`)
- `VolunteerEmployee` — per-date only (`googleCalendarDateSourceList`, `googleCalendarEventSettings`); no shared reminder fields
