# 032 — Implement 004.3: multi-type Contact, Lead convert, unique email

**Date:** 2026-09-21  
**Agent:** Cursor Auto (`/speckit-implement`)  
**Fable 5.1:** T007 copy command; T016 rebuild + `--apply` (max 2 this session)

## State

- Feature `004.3-contact-types-lead-convert` implemented on **local DDEV**.
- Write surface: `custom/` and `client/custom/` only. **No** `application/`
  or vendored Espo edits.
- Production `crm.safehouse.community` apply **not** done (owner-named).
- Live volunteer access mail **not** sent.
- Git commit / push **not** done.

## Checklists (implement gate)

| Checklist | Total | Checked | Unchecked | Status |
|-----------|-------|---------|-----------|--------|
| requirements.md | 16 | 16 | 0 | PASS |

Owner UAT: `checklists/owner-user-tests.md` (reviewer-owned; not an implement gate).

## DDEV data

- Contact email collisions: **0** (46 Contacts scanned via EmailAddress / ORM).
- `rebuild` (soft): ok. `copyContactTypeEnumToMulti --apply`: copied=46,
  failed=0. Volunteer Contacts: **18**, all `contactType === ["Volunteer"]`.
- Rebuild re-activated DDEV Google Calendar Sync/Overlay and push reminder
  jobs; they were inactivated again (DDEV `siteUrl` only).

## Path

Metadata (multiEnum, Dynamic Logic `has`/`or`) + Code (FieldValidators, hooks,
convert view). Formula rejected for unique email and user-visible illegal
types (plan.md R2/R7). Native `arrayAnyOf` is a **search** operator, not a
Dynamic Logic condition type (`checkCondition` returns false for unknown
types) — panels use documented `has` + `or` instead.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md

## Tests

- `ddev exec vendor/bin/phpstan analyse -c phpstan.neon` — OK (254 files).
- `ddev exec vendor/bin/phpunit tests/unit` — 196 tests, 435 assertions OK.

## Notes for UAT

- Convert uses custom Lead controller (`nonprofit-espocrm:controllers/lead`)
  because stock Lead controller hardcodes `crm:views/lead/convert`.
- ConvertService was **not** forked. Unique Contact email is a hard validator
  (convert still skips the duplicate dialog).
- Role sync looks up Role by **name** (Volunteer / Employee / Member).
