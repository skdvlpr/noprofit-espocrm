# Research: On-demand admission PDF (no stored file)

**Feature**: `005.3-on-demand-admission-pdf`

Cite (opened this turn):

- https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
- https://github.com/espocrm/documentation/blob/master/docs/development/metadata/pdf-defs.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/formula/ext.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
- https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
- https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
- https://github.com/espocrm/documentation/blob/master/docs/development/api.md
- https://github.com/espocrm/documentation/blob/master/docs/development/modules.md
- https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md
- https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
- https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-rebuild.md
- https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
- https://github.com/espocrm/documentation/blob/master/docs/development/acl.md

Read in-tree (not cited as home path): `application/Espo/Tools/Pdf/Service.php`
(`generate` returns `Result`; Template `entityType` must match the
record). `application/Espo/Modules/Crm/Tools/Lead/ConvertService.php`
(`FILE` / `IMAGE` fields: `getCopiedAttachment`).

## Decision: Generate on GET; never save Attachment

**Decision**: Lead and Contact admission preview/download call
`Espo\Tools\Pdf\Service::generate` and stream the bytes. They do not
create an Attachment and do not set `admissionFormId`. Lead
`lateAfterSave` stops calling generate/store. `AdmissionPdfPlan::shouldGenerate`
becomes always false for storage (or the store path is removed).

**Rationale**: Official Print to PDF is on demand from a Template
(printing-to-pdf.md). `Service::generate` already returns contents; this
product extra-wrote a file so convert could copy it. Lead GET already
renders live and ignores the stored file. Contact GET is the only
consumer of the cache, which is why old Associati show nothing.

**Alternatives considered**:

- Formula `ext\pdf\generate` — rejected. Docs: returns attachment ID;
  always stores. Does not run on GET.
- Keep storing on save “for speed” — rejected; owner asked for zero
  storage; one-page Dompdf is enough.
- Native Print to PDF menu only — rejected; staff already use the
  bottom preview.

## Decision: Contact needs its own Template row

**Decision**: Seed a second active PDF Template with the **same HTML
body**, `entityType` Contact, distinct name (e.g. `Domanda di ammissione
a socio (Contact)`). `pdfDefs.Contact.dataLoaderClassNameList` uses the
existing `AdmissionNewsletterLoader`. Render Lead with the Lead
template; Contact with the Contact template.

**Rationale**: `Pdf\Service` throws if Template target entity type ≠
record type. printing-to-pdf.md: at least one Template per entity type.

**Alternatives considered**: Fake a Lead to print a Contact — rejected.
Share one Template id across types — core forbids it.

## Decision: Convert copies fields; wipe any copied file

**Decision**: Do not fork `ConvertService`. Native convert still copies
matching person and board fields. File fields of the same name are
copied by duplicating the Attachment (fields.md File; ConvertService).
After convert, if Lead status is Converted and both sides are Associato,
clear `admissionForm` on Lead and Contact and remove those attachments
(existing skip-option save). `ContactAdmissionCopy` MUST NOT copy the
file; it may still fill empty board fields from the lead on rebuild.

**Rationale**: Owner: if Espo natively caches on convert, delete the
cached PDF and keep field transfer. Forking convert is constitution II.

**Alternatives considered**: Remove `admissionForm` from Contact
entityDefs so convert cannot match — leftover column and Lead files
would still exist. Exclude-list in convert metadata — Espo has
`convertFields` as extra maps, not an exclude list.

## Decision: Rebuild strips leftover files; stop file backfill

**Decision**: Replace the file-copy part of
`BackfillContactAdmissionFromLead` with a RebuildAction that finds Lead
and Contact rows with `admissionFormId` set, clears the field, deletes
the Attachment (ORM `removeEntity`). Keep copying empty **board**
fields from Converted Associato leads. Register in `app.rebuild`
(app-rebuild.md). Run via `ddev exec php command.php rebuild`
(commands.md).

**Rationale**: FR-006. Soft rebuild is enough; do not `--hard` drop the
unused file column.

**Alternatives considered**: Leave old files — they waste disk and
confuse staff who look at the file field in Entity Manager.

## Decision: Path = Code

GET cannot be Formula. Attachment delete after convert cannot be
Formula without `ext\pdf\generate` (wrong direction). Workflows/BPM not
installed. Tests: PHPUnit on plan/copy/clear helpers (tests.md).
