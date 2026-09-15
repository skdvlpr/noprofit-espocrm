# 011 — BugTracker HTML technician-email injection

**Date:** 2026-09-15  
**Agent:** Cursor automation (critical bug investigation, cron)

## State

HEAD at start of run was still `fd3e53fb` (Prima Nota donor-pocket). PRs **#54–#62** remain open drafts and are **not** ancestors of HEAD — this run did not duplicate them.

**Shipped:** BugTracker HTML injection in technician (and closed-reporter) emails that use the seeded EmailTemplate path. `BugReport.description` is a plain `text` field; Espo `EmailTemplate\Formatter` does `nl2br($value)` with no `htmlspecialchars` in HTML mode. Installer seeds `bugTrackerNotifyEmailTemplateId` with `{BugReport.description}` inside HTML. Any user with BugReport create can POST HTML and produce a live phishing mail from CRM SMTP. The unused fallback `buildPlainNewBody()` already escaped correctly.

**Not shipped (already open or residual):** attachment steal (#62), email-export IDOR (#61), CDS fetcher ACL (#60), SubjectParty SKIP_ALL (#59), EventPusher empty-events (#58), ProtectLinkedUser (#57), Stripe incomplete unlock (#56), Covered-slot (#55), OAuth refresh wipe (#54). SubjectParty nested create ACL and export Totale vs digital filters remain residual (nested create may be product intent; Totale is collection-sum UX).

## Files changed

- `custom/Espo/Modules/BugTracker/Tools/BugReportEmailHtml.php` (new)
- `custom/Espo/Modules/BugTracker/Tools/BugReportMailer.php`
- `tests/unit/Espo/Modules/BugTracker/BugReportEmailHtmlTest.php` (new)
- `tests/integration/Espo/Modules/BugTracker/BugTrackerTest.php`
- `bin/smoke-bug-tracker.php`

## Verification

- Cloud VM has no `php`/`ddev`; unit + integration tests are written for CI (PHP 8.4 PHPUnit).
- Integration test locks both sides: raw formatter still injects `<a href=…>`; `copyForTemplate()` yields `&lt;a href=…` and does not mutate the stored BugReport.

## Blockers

- Cannot execute PHPUnit in this environment.

## Next steps

1. Merge this PR, then **#62** (attachment steal) — both BugTracker, independent.
2. Merge remaining **#54–#61**.
3. Residual: SubjectParty nested create ACL (confirm product intent before fixing); BugTracker remaining none at this severity besides #62.
