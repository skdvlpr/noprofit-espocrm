# 046 — Implement contact admission panels

**Date:** 2026-09-25  
**Feature:** `specs/005.2-contact-admission-panels`

PDF preview was missing because Contact `bottomPanelsDetail` listed only relationships. Added `pdfPreview`. Rebuild copies empty board fields and file from converted leads. Unit tests 4/4. Local converted leads: 0, so backfill had nothing to copy. Socio Test on prod already has the file on the contact; the panel was the gap.

## Next

Owner UAT on local DDEV after hard refresh. Spec `007` for contact–user email sync (specify only).
