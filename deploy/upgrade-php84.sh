#!/usr/bin/env bash
# Production: single PHP runtime (8.4) for web (FPM), CLI, and cron.
# Installs php8.4-*, makes `php` the only CLI, updates Caddy, purges other PHP versions.
#
# Run as root: sudo bash deploy/upgrade-php84.sh
set -euo pipefail

TARGET_MM="8.4"
REQUIRED_EXTS=(pdo_mysql gd mbstring zip openssl curl exif xml iconv bcmath)
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/safehouse-crm}"
CRON_USER="${CRON_USER:-deploy}"

log() { echo "[upgrade-php84] $*"; }
die() { echo "[upgrade-php84] ERROR: $*" >&2; exit 1; }

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    die "Run as root (sudo bash deploy/upgrade-php84.sh)"
fi

if ! command -v apt-get >/dev/null 2>&1; then
    die "This script supports Debian/Ubuntu (apt). Adapt manually for other distros."
fi

export DEBIAN_FRONTEND=noninteractive

log "Installing PHP ${TARGET_MM} packages..."
apt-get update -qq
PACKAGES=(
    php8.4-fpm php8.4-cli php8.4-common
    php8.4-mysql php8.4-gd php8.4-mbstring php8.4-xml
    php8.4-curl php8.4-zip php8.4-intl php8.4-bcmath php8.4-exif
)
apt-get install -y -qq "${PACKAGES[@]}"

for ext in "${REQUIRED_EXTS[@]}"; do
    php8.4 -m | grep -qi "^${ext}$" || die "Missing PHP extension: ${ext}"
done

log "Setting system default CLI: php -> php8.4..."
if command -v update-alternatives >/dev/null 2>&1; then
    update-alternatives --install /usr/bin/php php /usr/bin/php8.4 84 2>/dev/null || true
    update-alternatives --set php /usr/bin/php8.4
fi

CLI_MM="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[[ "$CLI_MM" == "$TARGET_MM" ]] || die "CLI is PHP ${CLI_MM}, expected ${TARGET_MM}"

patch_caddy_socket() {
    local from="$1"
    local to="$2"
    local files=(/etc/caddy/Caddyfile)
    shopt -s nullglob
    files+=(/etc/caddy/Caddyfile.d/*.caddy)
    shopt -u nullglob
    for f in "${files[@]}"; do
        [[ -f "$f" ]] || continue
        if grep -qF "$from" "$f"; then
            sed -i "s|$(printf '%s' "$from" | sed 's/[|&\\]/\\&/g')|$(printf '%s' "$to" | sed 's/[|&\\]/\\&/g')|g" "$f"
            log "Caddy: patched ${f} (${from} -> ${to})"
        fi
    done
}

log "Updating Caddy php_fastcgi socket to php8.4-fpm..."
patch_caddy_socket 'php8.3-fpm.sock' 'php8.4-fpm.sock'
patch_caddy_socket 'php8.2-fpm.sock' 'php8.4-fpm.sock'
patch_caddy_socket 'php8.1-fpm.sock' 'php8.4-fpm.sock'

systemctl enable php8.4-fpm
systemctl restart php8.4-fpm

if systemctl is-active --quiet caddy 2>/dev/null; then
    caddy validate --config /etc/caddy/Caddyfile
    systemctl reload caddy || systemctl restart caddy
    log "Caddy reloaded"
fi

purge_old_php_versions() {
    local ver
    for ver in 8.5 8.3 8.2 8.1 8.0 7.4 7.3 7.2 7.1 7.0; do
        [[ "$ver" == "$TARGET_MM" ]] && continue
        if systemctl list-unit-files "php${ver}-fpm.service" 2>/dev/null | grep -q php; then
            systemctl stop "php${ver}-fpm" 2>/dev/null || true
            systemctl disable "php${ver}-fpm" 2>/dev/null || true
        fi
        mapfile -t old_pkgs < <(dpkg-query -W -f='${Package}\n' "php${ver}*" 2>/dev/null | grep -v '^$' || true)
        if ((${#old_pkgs[@]} > 0)); then
            log "Purging PHP ${ver} packages: ${old_pkgs[*]}"
            apt-get purge -y "${old_pkgs[@]}"
        fi
    done
    apt-get autoremove -y -qq
    apt-get autoclean -y -qq
}

log "Removing all PHP versions except ${TARGET_MM}..."
purge_old_php_versions

remaining="$(dpkg-query -W -f='${Package}\n' 'php[0-9]*' 2>/dev/null | grep -E '^php[0-9]+\.[0-9]+' | grep -v "^php${TARGET_MM}" || true)"
if [[ -n "$remaining" ]]; then
    die "Old PHP packages still installed:${remaining}"
fi

normalize_cron() {
    local tmp
    tmp="$(mktemp)"
    if ! crontab -l -u "$CRON_USER" 2>/dev/null >"$tmp"; then
        rm -f "$tmp"
        return 0
    fi
    if grep -q 'cron\.php' "$tmp"; then
        sed -i \
            -e 's|/usr/bin/php8\.[0-9]\+|php|g' \
            -e 's|/usr/local/bin/php8\.[0-9]\+|php|g' \
            -e "s|cd [^ ]*safehouse-crm|cd ${DEPLOY_PATH}|g" \
            "$tmp"
        crontab -u "$CRON_USER" "$tmp"
        log "Normalized ${CRON_USER} crontab to use plain \`php\` (system default ${TARGET_MM})"
        crontab -l -u "$CRON_USER" | grep cron.php || true
    fi
    rm -f "$tmp"
}

normalize_cron

log "Verification:"
php -v | head -1
command -v php
dpkg-query -W -f='${Package}\n' 'php[0-9]*' 2>/dev/null | grep -E '^php[0-9]+\.[0-9]+' | sort -u || true

log "Done. Single PHP ${TARGET_MM} for FPM + CLI + cron. Rebuild Espo if installed: cd ${DEPLOY_PATH} && php command.php rebuild"
