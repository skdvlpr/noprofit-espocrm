# Research: Shift planner vs official Espo behaviour

**Date**: 2026-09-24  
**Spec**: [spec.md](./spec.md)

Docs opened this turn (cite these, not the website):

- https://github.com/espocrm/documentation/blob/master/docs/development/entry-points.md
- https://github.com/espocrm/documentation/blob/master/docs/development/metadata/app-entry-points.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/emails.md
- https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
- https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
- https://github.com/espocrm/documentation/blob/master/docs/administration/workflows.md

Core read this turn: `application/Espo/Core/EntryPoint/Traits/NoAuth.php`,
`application/Espo/Modules/Crm/EntryPoints/CampaignUrl.php` (Hasher),
`application/Espo/Core/Controllers/RecordBase.php` (custom actions are
not auto-ACL’d; this module checks edit in `getOfferForEdit`).

## Decision: No-login page

**Decision**: Class entry point `ShiftAvailability` using trait `NoAuth`.
URL shape `?entryPoint=shiftAvailability&token=…`. GET shows the form.
POST submits. The secret in the email is random. The database stores
only `Hasher` output. Compare with `hash_equals` semantics via Hasher
equality. Wrong, expired, consumed, or replaced tokens render an Italian
“link no longer valid” page and write nothing.

**Rationale**: Official entry points are the documented way to render a
form without authentication. `CampaignUrl` already uses `NoAuth` plus
`Espo\Core\Utils\Hasher` for a secret in a query string. That is the
native pattern to copy, not a new auth scheme.

**Alternatives considered**:

- Metadata `app.entryPoints` — documented as of v10.1. This product is
  10.0.3. Rejected.
- Advanced Pack Workflows — paid BPM. Constitution forbids assuming it.
  Rejected after reading workflows docs.
- Portal user / volunteer login — still a sign-in. Owner asked for no
  CRM login.
- Putting the raw token in a field — leaks if the record is readable.
  Rejected. Hash only.

## Decision: Email content

**Decision**: Availability-request template replaces `{recordUrl}` with
a per-recipient `{availabilityUrl}` built at send time. The received
message points only at the shift dialog. It does not ask the volunteer
to log in or open the planner. Other shift emails (confirmation, admin
digest, plan updated) stay on `{recordUrl}`. Prove the volunteer
template by sending it through the outbound mail already configured on
local DDEV and reading the message. Do not send from production.

**Rationale**: `ShiftPlanningInstaller` template “Turni — Richiesta
disponibilità” tells the volunteer to open the plan in the CRM. That is
why a password is required today. `ShiftEmailService::sendTemplated`
already loops recipients and can attach one URL each. System outbound
address and skip-when-no-address stay.

**Alternatives considered**: One shared link for the cohort. Rejected.
The owner asked for one link per volunteer, and a shared link cannot
meet “cannot see another person”.

## Decision: Resend

**Decision**: `requestAvailabilityForUsers` (already “selected users
only, no invite wipe”) creates a new link per selected user who has an
email, marks that user’s previous live link replaced, and emails only
that set. `requestAvailability` (full cohort) does the same for everyone
it emails.

**Rationale**: Controller `ActivityOffer::postActionRequestAvailabilityForUsers`
already limits the audience. Missing piece is link rotation.

## Defects (in scope — fix before the public link)

### D1. Availability email requires CRM login

The request template links `{recordUrl}` (“Apri la pianificazione
turni”). That URL is the plan inside the CRM. `saveAvailability` uses
the signed-in user. A volunteer who is not logged in cannot answer.

This is the behaviour the owner wants changed. It is also the reason
magic links wait until D2–D4 are fixed: the new page must not copy the
session method or the raw SQL.

### D2. Hardcoded table SQL

`ShiftPlanningInstaller::migrateLegacyPlaceVarchar` runs `SHOW COLUMNS`
and `UPDATE` on `` `activity_offer_slot` ``. Constitution I: module PHP
must not hardcode SQL table names. Called from the installer on rebuild.

**Fix**: Stop shipping that SQL. If a one-shot copy from the old varchar
is still required, do it through Entity Manager / metadata attributes,
or drop the one-shot once `placeCity` is the stored value. No new
`SHOW COLUMNS`.

### D3. Admin can save availability as themselves without being in the cohort

`AvailabilityWorkflow::saveAvailability` allows the current user when
they are in the cohort **or** `isAdmin()`. An administrator who is not
a volunteer still gets `ActivityInvite` rows under their own user id.

**Fix**: Only a cohort member can write their own availability on the
logged-in path. Organisers keep edit on the plan (`getOfferForEdit`).
They do not invent a volunteer answer for themselves.

### D4. Availability mail logs the recipient address

`ShiftEmailService` info-log includes `to={address}`. The send path is
touched for per-person URLs. Log the user id and the kind, not the
address and not the token.

## Reviewed and not treated as defects

- `Services/ActivityOfferSlot::getCalenderQuery` drops `$userId` and
  honours `$skipAcl`. The method name and flag match
  `Espo\Modules\Crm\Tools\Calendar\Service`, which calls
  `getCalenderQuery` (core spelling). Calendar UNION needs the same
  columns. Not a fork of ACL.
- `EmailTemplate` processor `withApplyAcl(false)` matches system sends
  of a provisioned template. The public page must still not dump other
  volunteers.
- `requestAvailability` uses `getOfferForEdit`, so a user without edit
  on that plan cannot send mail. Custom controller actions are not
  ACL-checked by `RecordBase` (it returns true); the service check is
  the real gate and it is present.
- Confirmation and update emails still open the CRM. Out of scope.
- Hardcoded SQL in `ContactTypeEnumToMulti`, `ContactActivityCompetences`,
  and `DropRetiredPartyTables` is outside the planner. Deferred, not
  this feature.
- Duplicate docblocks in `AvailabilityWorkflow` / `ShiftPlanningSupport`
  are noise, not a user-facing defect.

## Decision: Logged-in save stays

Staff and volunteers who are already in the CRM can keep
`postActionSaveAvailability`. Refactor the write so the trusted caller
passes the target user. The controller passes the session user only
after the cohort check. The entry point passes the user id stored with
the link and never the session user.

## Open items

None. Lifetime is 7 days (spec assumption). Resend replaces the previous
link immediately.
