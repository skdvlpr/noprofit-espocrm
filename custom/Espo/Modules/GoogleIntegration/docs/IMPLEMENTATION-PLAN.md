# Google Calendar — implementation plan (ordered)

Spec: [CALENDAR.md](./CALENDAR.md)

## Status

| Step | Description | Status |
|------|-------------|--------|
| 0 | OAuth + External Account (admin credentials, per-user Connect) | **Done** |
| 1 | Personal `calendarSyncMode` on External Account + custom user UI + i18n | **Done** |
| 2 | `Tools\IntegrationState::isGoogleIntegrationEnabled()` | **Done** (used by hooks; UI uses config flag) |
| 3 | Record fields + layouts + `dynamicLogic` (Meeting, Call, Task, Opportunity) | Pending |
| 4 | `Tools\Calendar\Exporter` — immediate push when `addToGoogleCalendar` | Pending |
| 5 | Hooks on save for calendar entities + Opportunity | Pending |
| 6 | Scheduled job — per user per `calendarSyncMode` (skip `none`) | Pending |
| 7 | `bin/smoke-google-calendar.php` | Pending |

## Step 1 deliverables (personal sync mode)

- Metadata: `integrations.GoogleIntegration.userView` → module view extending `views/external-account/oauth2`
- UI fields (after **Connected**): enum `calendarSyncMode` — `none` | `bidirectional` | `crmToGoogle` | `googleToCrm`
- Persist in `ExternalAccount.data.calendarSyncMode` (not on Integration admin record)
- i18n: `ExternalAccount.json` + `Integration.json` (en_US, it_IT, ru_RU) — mode labels and help
- Hide panel when global integration disabled
- Smoke: metadata asserts `userView` present; ORM can set/read `data.calendarSyncMode` on test row

## Not in admin Integration form

- ~~`calendarSyncMode` on Integration~~ — **removed**; admin keeps only `enabled`, `clientId`, `clientSecret`.

## Entity list (steps 3–5)

- `Meeting`, `Call`, `Task` — CRM calendar scopes
- `Opportunity` — `closeDate` only (Safehouse-safe)
