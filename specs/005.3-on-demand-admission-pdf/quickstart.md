# Quickstart: On-demand admission PDF

Local DDEV `https://nonprofit-espocrm.ddev.site`. Hard-refresh.

Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md

1. Open an Associato contact that never came from a website lead.
   **Anteprima PDF** shows the form. Empty board boxes stay empty.
2. Change codice fiscale, save, click Aggiorna on the preview. The new
   code is on the page. The contact has no file in `admissionForm`.
3. Open an Associato lead. Preview matches the lead. No file stored.
4. Convert an Associato lead that has Consiglio Direttivo answers.
   Contact board matches. Neither record has an admission file. Both
   previews still open.
5. Open a volunteer-only contact. No admission preview.

Production: Skip until the owner names that apply.
