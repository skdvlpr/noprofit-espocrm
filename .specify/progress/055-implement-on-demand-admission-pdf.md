# 055 — Implement on-demand admission PDF

**Date:** 2026-09-25  
**Feature:** `specs/005.3-on-demand-admission-pdf`  
**Agent:** Cursor Grok 4.6 (inherit)

## State

T001–T015 done on local DDEV. Admission PDF is built at GET for Lead and
Contact. Save does not store a file. Convert leftover files are cleared.
Rebuild strips leftover `admissionForm` attachments. Owner UAT open.
No commit, push, or production apply.

Path = **Code** (GET + wipe). Formula `ext\pdf\generate` rejected.

## Files

- `Tools/Admission/AdmissionPdf.php`, `AdmissionPdfPlan.php`,
  `ContactAdmissionCopy.php`
- `Tools/Admission/Api/GetLeadAdmissionPdf.php`,
  `GetContactAdmissionPdf.php`
- `Hooks/Lead/SyncAdmissionPdf.php`
- `Resources/metadata/pdfDefs/Contact.json`
- `Resources/metadata/app/rebuild.json`
- `Core/Rebuild/StripStoredAdmissionPdfs.php`,
  `BackfillContactAdmissionFromLead.php` (comment + no file copy)
- `client/.../pdf-preview.js` (live GET comment)
- tests: `AdmissionPdfPlanTest.php`, `ContactAdmissionCopyTest.php`
- `specs/005.3-on-demand-admission-pdf/tasks.md` all `[X]`
- `checklists/owner-user-tests.md`

## Verification

- PHPUnit: 11 tests, 32 assertions, OK
- PHPStan level 5 on touched PHP: no errors
- `ddev exec php command.php rebuild`: Rebuild has been done
- Google Calendar Sync: inactivated; Send Push Reminders: inactivated;
  Overlay Sync: missing (no job with that name)

## Blockers

- Owner UAT V1–V5
- Production apply not approved

## Next steps

1. Owner runs owner-user-tests.md (Russian script in chat)
2. Fail → hotfix `005.4` if needed
3. Commit/push/prod only when named
