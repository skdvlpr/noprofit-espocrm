# 027 — Access-info logo CID in Gmail (Htmlizer)

**Date:** 2026-09-18  
**Agent:** Cursor Auto (stay in `specs/004.2-create-user-review`)

## State

- Owner V1–V12 checked Pass. Residual: access-info / password-change-link
  logo broken in **Gmail** (alt “Safe House”); disponibilità/shift mail
  already showed CID.
- Write surface: `custom/` only. **No** `application/` edit.
- Git commit / push / production / live volunteer resend: **not** done.

## Cause

Path = Code. Formula cannot intercept Htmlizer.

Espo EmailTemplate Processor renders bodies with
`skipInlineAttachmentHandling = true`, so `{logoHtml}` keeps
`?entryPoint=attachment&amp;id=` and `Email::getBodyForSending()` rewrites
to `cid:{id}@espo`.

Access-info (`Password\Sender`) calls
`Htmlizer::render($user, $tpl, $data, true)` — 4th arg is **skipLinks**,
5th (`skipInlineAttachmentHandling`) stays **false**. `postProcessHtml`
turns `&amp;` into `&` then replaces the URL with a **local file path**.
Gmail cannot fetch that → broken image. Core Sender is not patched
(Principle II).

Cite:
https://github.com/espocrm/documentation/blob/master/docs/development/template-custom-helper.md
https://github.com/espocrm/documentation/blob/master/docs/development/attachments.md
https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md

## Files changed

- `Tools/SafehouseLogoAttachment.php` — `entryPointSrc($id, $htmlizerSafe)`;
  access-info helper uses `$htmlizerSafe = true` (`&amp;amp;id=` so one
  Htmlizer pass leaves `&amp;id=` for CID). Shift `imgHtml(64)` unchanged.
- `TemplateHelpers/SafehouseLogo.php` — `imgHtml(160, true)`.
- Tests: `SafehouseLogoHelperTest` Htmlizer+CID encoding.
- Specs: T033; owner residual note; quickstart Gmail CID.

## Verification

- PHPUnit NonprofitEspocrm: **124 tests, 284 assertions**, OK.
- PHPStan level 5 on the two PHP files: clean.

## 2026-09-18 send (owner named samsungj73965)

- CLI `sendAccessInfoUser --userName=samsungj73965` →
  `samsungj73965@gmail.com` (User `6aad69d68a2969a37`).
- Native `Sender::sendAccessInfo` (accessInfo template, set-password
  link). Did **not** call `sendAccessInfoForNewUser` (that would reset
  the stored password).
- Rebuild for the command re-activated Google/push; re-gated Inactive.
  Dummy stays Active.

## Next steps

1. Owner checks Gmail for CID logo on this access-info.
2. Ask before git commit; never push unless asked.
