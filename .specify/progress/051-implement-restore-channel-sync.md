# 051 — Implement restore contact–user sync

**Date:** 2026-09-25  
**Feature:** `specs/007.1-restore-channel-sync`

Path = Code. `ContactUserChannelSync` now runs for `wantsCrmUser` (Volunteer,
Employee, Associato). Copies prefix / first / last name and full email and
phone sets. Empty contact email does not wipe the user login email.
PHPUnit 10/10. PHPStan clean. Local rebuild. DDEV Google Calendar Sync,
Overlay Sync, and Send Push Reminders set Inactive.

Owner UAT: `specs/007.1-restore-channel-sync/checklists/owner-user-tests.md`
(Rossella). Feature is not accepted until that checklist is reported.

## Next

Owner UAT on local DDEV. Commit only if asked. No production apply.
