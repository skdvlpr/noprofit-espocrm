# Production deploy — crm.safehouse.community

Continuous delivery ships this repository to the production server over
SSH/rsync. Espo arrives **uninstalled** on a fresh host so you complete the web
installer yourself and keep full control of configuration, then re-run the
workflow for subsequent code updates.

## Model

```mermaid
flowchart LR
  push["push to main / manual dispatch"] --> gha["GitHub Actions: deploy.yml"]
  gha -->|"rsync over SSH (excludes server state)"| server["crm.safehouse.community"]
  server --> caddy["Caddy reverse proxy -> php-fpm, docroot public/"]
  server -->|"data/config.php present?"| inst{installed?}
  inst -->|no| web["Manual web installer /install/"]
  inst -->|yes| rebuild["php command.php rebuild + clear cache"]
```

- The workflow **never** pushes `data/config.php`, uploads, cache, logs,
  `install/config.php`, or `custom/Espo/Custom/` (server-owned). See
  [rsync-excludes.txt](rsync-excludes.txt).
- First deploy → server has code but no `data/config.php` → finish setup at
  `https://crm.safehouse.community/install/`.
- Later deploys → workflow detects the install and runs rebuild + clear cache.
- `vendor/` is shipped from the repo (no composer step required on the server).

## Required GitHub repository secrets

Settings → Secrets and variables → Actions → **Secrets**:

| Secret | Example | Notes |
| --- | --- | --- |
| `DEPLOY_HOST` | `crm.safehouse.community` | SSH host |
| `DEPLOY_USER` | `deploy` | SSH user that owns the docroot |
| `DEPLOY_PATH` | `/var/www/safehouse-crm` | Absolute path to the Espo root (the dir that contains `public/`) |
| `DEPLOY_SSH_KEY` | `-----BEGIN OPENSSH PRIVATE KEY-----…` | Private key whose public half is in the server's `~/.ssh/authorized_keys` |
| `DEPLOY_SSH_PORT` | `22` | Optional; defaults to `22` |

Optional **Variable** (Settings → Variables): `DEPLOY_RSYNC_DELETE=1` to prune
stale code files on the server (runtime/server-state excludes still protect
`data/`, `install/config.php`, `custom/Espo/Custom/`). Default is no deletion.

## PHP runtime (single version on production)

**Policy:** production runs **one** PHP version only (currently **8.4**). Web (FPM), CLI
(`php command.php`), and cron (`php cron.php`) all use the same system default — no
side-by-side 8.3/8.4, no versioned binaries in cron.

EspoCRM 10 supports PHP 8.3–8.5; we standardize on **8.4** (DDEV + CI + prod).

One-time on the server (as root):

```bash
sudo bash /var/www/safehouse-crm/deploy/upgrade-php84.sh
```

The script:

1. Installs `php8.4-*` (FPM + CLI + extensions)
2. Sets `update-alternatives` so **`php`** → `php8.4`
3. Points Caddy at `php8.4-fpm.sock`
4. **Purges** all other `php8.3*`, `php8.2*`, … packages and stops old FPM units
5. Normalizes deploy-user cron to `php cron.php` (not `/usr/bin/php8.4`)

GitHub Actions deploy runs the same script via `sudo -n` when passwordless sudo is
configured; otherwise run manually once before the next deploy.

## Server prerequisites (one-time)

1. PHP **8.4** (EspoCRM 10 supports 8.3–8.5) + required extensions (pdo_mysql, gd,
   intl, exif, ldap optional) and a MySQL/MariaDB database.
2. A deploy user with write access to `DEPLOY_PATH`, and the PHP-FPM/web user
   able to write `data/` and `custom/` (Espo creates `data/config.php` during
   install).
3. Cron for Espo (`* * * * * cd DEPLOY_PATH && php cron.php > /dev/null 2>&1`) — plain
   `php` is correct after `upgrade-php84.sh` (single system default).
4. Caddy serving the `public/` docroot (below).

## Caddy

EspoCRM 9.x uses `public/` as the document root with an entry router. Minimal
Caddyfile:

```caddyfile
crm.safehouse.community {
    root * /var/www/safehouse-crm/public
    encode zstd gzip
    php_fastcgi unix//run/php/php8.4-fpm.sock
    file_server

    # Deny access to sensitive paths (defense in depth).
    @denied path /data/* /application/* /custom/* /vendor/* /install/config.php
    respond @denied 404

    header {
        X-Content-Type-Options nosniff
        X-Frame-Options SAMEORIGIN
        Referrer-Policy strict-origin-when-cross-origin
    }
}
```

Notes:
- EspoCRM ships rewrite rules for the `public/` docroot; `php_fastcgi` +
  `file_server` covers SPA + API (`/api/v1/...`) routing via `public/index.php`
  and `public/api/v1/index.php`.
- After install, set **Administration → Settings → Site URL** to
  `https://crm.safehouse.community` (required for REST self-checks / WebSocket).

## First production install checklist

1. Push to `main` (or run the workflow manually) → code lands on the server.
2. Open `https://crm.safehouse.community/install/` and complete the installer
   (DB credentials, admin user).
3. Set Site URL; run **Administration → Rebuild**.
4. Pick a theme (Safehouse Aurora / Aurora Light) and configure integrations
   (Google) — or import them later via the migration scripts (see Phase 6).
5. Re-run the deploy workflow for the next code change; it will auto-rebuild.

## Extension install order and Aurora themes policy

ZIPs are built **only on local/CI** via `bin/build*.sh` → `dist/*.zip`.
**Builders (`bin/build*.sh`, `bin/packaging/`) never ship to production** (rsync excludes).

### Preferred production extension flow

1. Build ZIP(s) locally / CI → artifacts in `dist/` (gitignored).
2. Upload to prod via Administration → Extensions (or `php command.php extension --file=…`).
3. Rebuild + Clear Cache.
4. **Delete the ZIP from the server** after Extension Manager has unpacked files.
5. Do **not** leave `dist/`, builders, or packaging scripts on prod.

| ZIP | Extension ID | Purpose |
| --- | --- | --- |
| `google-integration-v*.zip` | `GoogleCalendarDrive` | Google OAuth2, Calendar/Drive |
| `nonprofit-espocrm-v*.zip` | `NonprofitEspocrm` | Nonprofit CRM — **includes bundled `SafehouseAuroraThemes` + CSS/fonts** |
| `bug-tracker-v*.zip` | `BugTracker` | Optional bug report FAB |
| `workflow-engine-v*.zip` | `WorkflowEngine` | Optional admin workflows |
| `safehouse-aurora-themes-v*.zip` | `SafehouseAuroraThemes` | Themes only (stock Espo **without** NonprofitEspocrm) |

### Production / full Safehouse stack

Install order, then **Rebuild** after each ZIP:

1. `google-integration-v*.zip`
2. `nonprofit-espocrm-v*.zip`
3. (optional) `bug-tracker-v*.zip` / `workflow-engine-v*.zip`

Do **not** install `safehouse-aurora-themes-v*.zip` on the same instance as NonprofitEspocrm
(themes already bundled).

**Current CD note:** GitHub Actions still tree-rsyncs `custom/` + `client/custom/` for day-to-day
deploys (rebuild only). Extension ZIPs remain the clean install / upgrade channel. Rsync
excludes builders, smokes, `_local/`, and `dist/`.

Maintenance CLIs (`bin/smoke-*`, `seed-*`, `migrate-*`, `build*.sh`, …) are **excluded from
rsync** and must not be executed on production (see `bin/README.PRODUCTION.txt`).

### Standalone `safehouse-aurora-themes` ZIP

Use only on Espo instances **without** NonprofitEspocrm — for example vanilla Espo
where you want Safehouse Aurora / Aurora Light themes but not the CRM module.
After install, run **Rebuild** and pick a theme under **Layout Manager →
Themes**.

### Verify after install

- `php command.php extension --list` — expect `GoogleCalendarDrive` +
  `NonprofitEspocrm` on a full stack; **one** themes source (bundled inside CRM, not
  a separate `SafehouseAuroraThemes` extension row).
- Hard-refresh browser; DevTools → Network: Google admin view must load from
  `client/custom/modules/google-integration/…` (HTTP 200), not
  `client/lib/transpiled/…` (404).
- Smokes (from repo `bin/` on the server or a DDEV sibling): `smoke-installer.php`,
  `smoke-google-integration.php`, `smoke-safehouse.php`, `smoke-theme-assets.php`.

See also AGENTS.md **EXT-007** (multiple extensions in one repo).


```bash
rsync -rlptz --human-readable \
  -e "ssh -p 22" \
  --exclude-from=deploy/rsync-excludes.txt \
  ./ deploy@crm.safehouse.community:/var/www/safehouse-crm/
ssh deploy@crm.safehouse.community 'cd /var/www/safehouse-crm && [ -f data/config.php ] && php command.php rebuild || echo "Run /install/ first"'
```
