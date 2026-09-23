# 039 — Implement website admission leads

**Date:** 2026-09-22  
**Feature:** `005-website-lead-admission`  
**Commits already on main (not pushed):** `399e53d` (004.3), `bf8da4f` (this spec/plan/tasks). Implementation is not committed.

## What shipped locally

- Lead card shows tax code and birth fields. No Italian fiscal-code validator on Lead. Max length 32. Spaces stripped only when the value is letters, digits, and spaces.
- Board panel **Consiglio Direttivo** only when type includes Associato. Outcome and fee are single enums. Edit is admin or Role name `Member`. Empty create is not treated as a board edit.
- One PDF template “Domanda di ammissione a socio”, replaced on change, not created for Volunteer-only.
- Native convert copies the file. Converted Associato Lead then drops its copy. Contact shows the file on the Member panel.
- Website user `site_safehouse.community` (role Website) already has Lead create/edit. Forbidden edit list does not include the socio fields. A create as that user stored the Lead and a PDF, then the probe was deleted.

## Checks

- PHPUnit AdmissionBoard, NewsletterConsent, AdmissionPdfPlan: OK
- PHPStan on the new PHP: OK
- Soft rebuild: OK
- Probe: Associato file id set, tax `AAAAAA00A00A000A` stored, Volunteer file none

## Not done

- Owner UAT of the PDF layout
- Git commit of the implementation (not requested after the tasks commit)
- Push, prod, website repo
