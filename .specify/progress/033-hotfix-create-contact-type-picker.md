# 033 — Hotfix 004.3: create Contact Volunteer/Employee type sticks

**Date:** 2026-09-21  
**Feature:** `004.3-contact-types-lead-convert`  
**Owner:** Confirmed Pass on DDEV (`#Contact/create` Volontario; Associato+Volontario)

## Bug

On **create** (not edit), picking Volontario or Dipendente emptied Tipo
contatto and hid extra fields. Other types and edit were fine.

## Cause

Path = **Code** (Contact record edit view). Native multiEnum
`setOptionList` (non-silent) rebuilds `this.selected` from a stale `[]`
while the model already has `["Volunteer"]` / `["Employee"]`, then
`trigger('change')` writes `[]` back. Option list only changes for those
two types (drop the mutually exclusive opposite), which is why Colleague
/ Member / Other already stuck.

Cite:
https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md

Rejected: Dynamic Logic **options** on the same field (documented side
effects; core uses the same `setOptionList` wipe).

## Fix

`restrictPersonnelTypeOptions` copies `selected` from the model, skips
when the option list is unchanged, and calls `setOptionList(options, true)`.
`edit-small` inherits. Debug ingest removed.

## Spec cycle

Updated in place (same FEATURE_DIR): spec FR-015 / SC-009 / US1 scenario
7; plan path row; research R11; tasks T040; contract; owner-user-tests
step 11; quickstart.

## Still owner-owned

- Remaining UAT steps 1–10 except this create-type Pass
- Git commit / push — ask
- Prod apply of 004.3 — not named
- Live volunteer mail — not named
