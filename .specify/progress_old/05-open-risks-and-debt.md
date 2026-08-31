# 05 — Open risks and debt (legacy extract)

**Extracted:** 2026-08-31 from Notion post-launch page (as of ~2026-08-16) + repo skim.  
Statuses may be stale — re-verify in compliance audit.

## Process / governance debt (addressed this session)

- AGENTS.md was a second bible vs Espo docs → replaced by constitution + slim AGENTS.
- Notion executor logs lagged git → retired in favour of `.specify/progress/`.

## Product / tech debt (examples — not exhaustive)

- Full custom-module compliance vs official Espo docs **not yet audited** (next major spec).
- Many `bin/smoke-*.php` scripts; constitution wants proper PHPUnit long-term (`bin/` hygiene spec).
- GoogleIntegration / Nonprofit acceptableVersions still allow 9.3.x in some manifests while target is Espo 10+.
- Cross-extension coupling and suite vs standalone packaging need a documented dependency strategy.
- Secrets may still live outside App Secrets (OAuth, Stripe, VAPID).
- Historical Role auto-provision removed; Roles must be maintained manually in Admin UI — prod ACL drift risk.
- Notion backlog items often left in Testing awaiting Manual QA; several P0–P3 themes (portal cancelled; reporting module draft; bidirectional GCal; Time Tracking) unfinished.
- Soft-delete restore / bank reconcile (CRM-OPS-01) and Prima Nota dashlet enhancements (S16–S21) were open as of mid-August notes.
- Lab experiment: retire Member + VolunteerEmployee toward Contact STI — Contact STI rewrite was a blocker; status unknown without re-audit.

## Immediate next engineering (after constitution)

1. Spec: compliance audit of `custom/Espo/Modules/**`
2. Spec: tests + `bin/` hygiene
3. Spec (optional): harden CI/CD
