# Research: Website admission leads and socio PDF

## R1 — Lead card already has the columns

**Decision**: Remove `layoutAvailabilityList: []` from Lead `taxCode`, `birthDate`, `birthPlace`, `birthProvince` and place them on detail and edit beside `contactType`. Keep volunteer-only fields (`startDate`, competences, and the rest) off the Lead layouts.

**Rationale**: Those four fields are already in `entityDefs/Lead.json`. The empty availability list hides them from the layout manager and from the card. The site already posts them. Italian labels are already in `i18n/it_IT/Lead.json`.

**Alternatives considered**: New fields with different names — convert would not copy them. Putting the Contact validator on Lead — rejects codes the site accepts.

Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/layout-manager.md

## R2 — Fiscal code on Lead is display + light cleanup, not the Contact check

**Decision**: Do not add `ItalianFiscalCode` to Lead. Keep uppercase. Allow a value longer than 16 so staff can see a bad paste. Strip spaces only. Contact keeps `ItalianFiscalCode` (16-char pattern or 11-digit VAT). Conversion fails there.

**Rationale**: The site accepts `^[A-Z0-9]{16}$` after uppercasing. Contact’s pattern is stricter (`AAAAAA00A00A000A` shape). A 400 on Lead create happens after the email and the site discards the error.

**Alternatives considered**: Same validator on both — drops applications. Required fiscal code on Lead — blocks volunteer Leads.

Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md

## R3 — Board answers are enums

**Decision**: `admissionOutcome` options `"" | Approved | Rejected`. `admissionFeePaid` options `"" | Yes | No`. Empty string is a real option; `EmptyStringToNull` sanitizer. Meeting date, book number, receipt number are ordinary date/varchar, not required.

**Rationale**: One enum value cannot be both Approved and Rejected. Dynamic Logic shows the panel only when `contactType` has `MemberContact`.

**Alternatives considered**: Two checkboxes — needs custom mutual-exclusion code and can be posted together via API. Formula-only — does not stop a direct API write as clearly as a beforeSave Forbidden.

Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/dynamic-logic.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md

## R4 — Editors are admin or Role name Member

**Decision**: `RestrictAdmissionBoard` beforeSave. If a board field changed and the user is logged in, not admin, and has no Role whose **name** is `Member`, throw Forbidden. The website integration user is not that role, so it can still create the Lead with board fields empty.

**Rationale**: Roles merge by permissiveness; a field-level “no” on every role except Member needs editing each Role record and breaks when a new role is added. Admin bypass is `ApplicationState::isAdmin()`. Role ids are not hardcoded.

**Alternatives considered**: Field ACL on one Role id — not stable across DDEV and prod. Hiding the panel in JS only — API could still write.

Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md

## R5 — PDF is a Template plus lateAfterSave

**Decision**: Seed one active PDF Template for Lead, body matching the paper module (fixed letterhead, section 2 text, blank signature lines, president label Matteo Grossi). `SyncAdmissionPdf` on `lateAfterSave` calls `Espo\Tools\Pdf\Service::generate` with ACL off (same as `ext\pdf\generate`), stores an Attachment, sets file field `admissionForm`, deletes the previous admission attachment. Skip when the Lead is not Associato, when status is Converted, and when the save only attaches the new file (`SaveOption` guard) so it does not loop.

**Rationale**: `ext\pdf\generate` needs an existing id and is documented as not working in a before-create script. This product does not use Advanced Pack workflows. `lateAfterSave` runs after commit (v10).

**Alternatives considered**: Formula only — misses create. Print-to-PDF button only — the site-created Lead would have no file until someone prints. Editing core Pdf Service — forbidden.

Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/formula/ext.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md

## R6 — Newsletter is template data, not a field

**Decision**: `pdfDefs.Lead.dataLoaderClassNameList` adds `newsletterConsent` = `yes` | `no` | `` by reading the description line the site already writes (`Newsletter: acconsente` / `non acconsente`). The template marks one box. Missing line → both empty.

**Rationale**: FR-014 forbids a new stored newsletter field. The description text is the contract.

**Alternatives considered**: Asking the site to send a bool — out of scope; the site is not modified.

Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/pdf-defs.md

## R7 — Convert copies the file, then the Lead lets go of it

**Decision**: `admissionForm` file field on Lead and Contact (same name, same type). Native convert copies it onto the Contact. When the Lead save is the conversion (status Converted, `createdContactId` set, Contact type includes Associato), clear the Lead file and delete that attachment. Do not generate a new PDF for a Converted Lead. If the Contact is not Associato, leave the Lead file.

**Rationale**: `ConvertService::getValues` copies a file field by duplicating the attachment. It does not remove the source. Forking that service is forbidden.

**Alternatives considered**: A second attachment link with a custom convert action — duplicates native copy. Deleting the Lead — native convert keeps the Lead.

Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md

## R8 — Website user

**Decision**: Do not create a user and do not read the site `.env` into git. Implement verifies on DDEV that the integration user can POST the socio payload. If Lead create/edit is missing, extend that user’s existing role for Lead create and the socio fields only. Board writes still fail the hook.

**Rationale**: The site already has a client. A second user would not be the one the site calls.

**Alternatives considered**: Hardcoding an API key — secret, and not this feature.
