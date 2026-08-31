# 04 — CI/CD and deploy (legacy extract)

**Extracted:** 2026-08-31 from `.github/workflows/` and `deploy/`.

## Current workflows

### `ci.yml`

- Triggers: push/PR to `main`, `workflow_dispatch`
- Job `test`: MariaDB service `db_test`, PHP 8.4, composer, `bin/test-build.sh`, PHPStan, PHPUnit unit + integration
- Job `deploy` (needs test, `main` only): SSH key from secrets, rsync with `deploy/rsync-excludes.txt`, ensure PHP 8.4, then `php command.php rebuild` if installed

### `prod-provision-oneshot.yml`

- Manual only (`workflow_dispatch`)
- SSH: Installer::runPostInstall, BugTracker installer, quarantine leftover `bin/smoke-*` etc., security snapshot
- High risk — operator-only; do not expand without harden-CI spec

## Deploy habits

- Production is live; treat every `main` push as potential ship.
- Historical policy: no push/PR without explicit user ask; agent asks first.
- Post-deploy: rebuild/clear cache; never run local smokes on prod.
- Schema migrations historically run as one-off PHP on server with explicit approval (e.g. PrimaNota gross backfill).

## Gaps (defer to `/speckit-specify` harden CI)

- Auto-deploy on every green `main` push vs gated release
- Secret exposure review in logs/steps
- Builder scripts still in repo vs gitignore policy
- Consistency between suite ZIP install vs rsync tree deploy
