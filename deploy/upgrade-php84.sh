#!/usr/bin/env bash
# Switch production host to PHP 8.4 for EspoCRM (web + CLI + cron must match).
# Run on the server as root or via: sudo bash deploy/upgrade-php84.sh
#
# Idempotent: safe to re-run; exits 0 when PHP 8.4 is already active everywhere.
set -euo pipefail

REQUIRED_EXTS=(pdo_mysql gd mbstring zip openssl curl exif xml iconv bcmath)

log() { echo "[upgrade-php84] $*"; }
die() { echo "[upgrade-php84] ERROR: $*" >&2; exit 1; }

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    die "Run as root (sudo bash deploy/upgrade-php84.sh)"
fi

if ! command -v apt-get >/dev/null 2>&1; then
    die "This script supports Debian/Ubuntu (apt). Adapt manually for other distros."
fi

export DEBIAN_FRONTEND=noninteractive

log "Installing PHP 8.4 packages..."
apt-get update -qq
PACKAGES=(php8.4-fpm php8.4-cli php8.4-common php8.4-mysql php8.4-gd php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-intl php8.4-bcmath php8.4-exif)
apt-get install -y -qq "${PACKAGES[@]}"

for ext in "${REQUIRED_EXTS[@]}"; do
    php8.4 -m | grep -qi "^${ext}$" || die "Missing PHP extension: ${ext}"
done

log "Setting system default php CLI to 8.4..."
if command -v update-alternatives >/dev/null 2>&1; then
    update-alternatives --set php /usr/bin/php8.4 2>/dev/null || ln -sf /usr/bin/php8.4 /usr/local/bin/php-safehouse
fi

CLI_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[[ "$CLI_VER" == "8.4" ]] || die "CLI still on PHP ${CLI_VER}, expected 8.4"

log "Updating Caddy php_fastcgi socket (8.3 -> 8.4) if present..."
CADDYFILES=(/etc/caddy/Caddyfile /etc/caddy/Caddyfile.d/*.caddy)
for f in "${CADDYFILES[@]}"; do
    [[ -f "$f" ]] || continue
    if grep -q 'php8\.3-fpm\.sock' "$f"; then
        sed -i 's|php8\.3-fpm\.sock|php8.4-fpm.sock|g' "$f"
        log "Patched $f"
    fi
done

systemctl enable php8.4-fpm
systemctl restart php8.4-fpm

if systemctl is-active --quiet caddy 2>/dev/null; then
    caddy validate --config /etc/caddy/Caddyfile
    systemctl reload caddy || systemctl restart caddy
    log "Caddy reloaded"
fi

if systemctl is-active --quiet php8.3-fpm 2>/dev/null; then
    systemctl stop php8.3-fpm || true
    systemctl disable php8.3-fpm || true
    log "Stopped php8.3-fpm"
fi

log "PHP versions:"
php -v | head -1
php8.4 -v | head -1

CRON_LINE='* * * * * cd /var/www/safehouse-crm && /usr/bin/php8.4 cron.php > /dev/null 2>&1'
if crontab -l -u deploy 2>/dev/null | grep -q 'cron.php'; then
    log "Reminder: ensure deploy user cron uses php8.4 explicitly:"
    echo "  $CRON_LINE"
fi

log "Done. PHP 8.4 active (CLI + FPM). Run rebuild from deploy path if Espo is installed."
