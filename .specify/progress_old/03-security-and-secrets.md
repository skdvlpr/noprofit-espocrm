# 03 — Security and secrets (legacy extract)

**Extracted:** 2026-08-31. Prefer Espo App Secrets going forward (constitution VI).

## Known hard rules (carry forward)

- Never commit secrets, API keys, OAuth client secrets, or passwords.
- Production tree historically under `/var/www/safehouse-crm`; siteUrl `*.safehouse.community`.
- `bin/lib/refuse-production.php` hard-exits maintenance scripts on prod hosts — keep until a hygiene spec replaces the model.
- Incident: long-lived `bin/setup-roles.php` rewrote live ACL and created test users — **removed**; Roles only via Administration → Roles; no RoleSetup / ProvisionRoleAcl auto-provision (removed ~2026-08-16).
- Deploy must not ship smoke/seed/migrate CLIs (`deploy/rsync-excludes.txt`).
- PII: Member/Volunteer/Contact personal fields — least privilege via Roles / field-level security; dynamicLogic is not security.

## Likely secret locations to audit (next compliance spec)

- GitHub Actions secrets: `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `DEPLOY_SSH_PORT`
- Espo `data/config.php` / config overrides on server (never rsync)
- Google OAuth client credentials (Integration / ExternalAccount)
- Stripe keys (site ↔ CRM; CRM may hold webhook/API config)
- VAPID keys for Web Push
- Any leftover credentials in smoke scripts or oneshots

## Preferred store

Administration → App Secrets — https://docs.espocrm.com/administration/app-secrets/
Local: `~/safehouse/espocrm-documentation/docs/administration/app-secrets.md`
