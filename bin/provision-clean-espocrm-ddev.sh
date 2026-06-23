#!/usr/bin/env bash
# Provision a vanilla EspoCRM DDEV project for clean extension ZIP install tests.
#
# Usage:
#   bin/provision-clean-espocrm-ddev.sh --name nonprofit-espocrm-clean-7a-gi \
#       --install /path/to/google-integration-v0.1.0.zip
#
# Options:
#   --name NAME          DDEV project name (directory: ../NAME relative to repo root)
#   --espo-version VER   EspoCRM release tag (default: 9.3.8)
#   --install ZIP        Extension ZIP to install via CLI (repeatable)
#   --skip-ddev-start    Only extract + install Espo; do not start DDEV
#   --force              Remove existing project directory first
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ESPO_VERSION="9.3.8"
PROJECT_NAME=""
INSTALL_ZIPS=()
SKIP_DDEV_START=0
FORCE=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --name) PROJECT_NAME="$2"; shift 2 ;;
        --espo-version) ESPO_VERSION="$2"; shift 2 ;;
        --install) INSTALL_ZIPS+=("$2"); shift 2 ;;
        --skip-ddev-start) SKIP_DDEV_START=1; shift ;;
        --force) FORCE=1; shift ;;
        *) echo "Unknown option: $1" >&2; exit 1 ;;
    esac
done

if [[ -z "$PROJECT_NAME" ]]; then
    echo "Error: --name is required" >&2
    exit 1
fi

PROJECT_DIR="$(cd "$ROOT_DIR/.." && pwd)/$PROJECT_NAME"
CACHE_DIR="${XDG_CACHE_HOME:-$HOME/.cache}/espocrm-releases"
ZIP_NAME="EspoCRM-${ESPO_VERSION}.zip"
CACHE_ZIP="$CACHE_DIR/$ZIP_NAME"
SITE_URL="https://${PROJECT_NAME}.ddev.site"

mkdir -p "$CACHE_DIR"
if [[ ! -f "$CACHE_ZIP" ]]; then
    echo "Downloading EspoCRM ${ESPO_VERSION}..."
    curl -fsSL -o "$CACHE_ZIP" \
        "https://github.com/espocrm/espocrm/releases/download/${ESPO_VERSION}/${ZIP_NAME}"
fi

if [[ -d "$PROJECT_DIR" ]]; then
    if [[ "$FORCE" -eq 1 ]]; then
        echo "Removing existing $PROJECT_DIR"
        rm -rf "$PROJECT_DIR"
    else
        echo "Project directory already exists: $PROJECT_DIR (use --force to recreate)" >&2
        exit 1
    fi
fi

mkdir -p "$PROJECT_DIR"
python3 - "$CACHE_ZIP" "$PROJECT_DIR" <<'PY'
import sys
import zipfile
from pathlib import Path

archive = zipfile.ZipFile(sys.argv[1])
root_prefix = None
for name in archive.namelist():
    if name.endswith('/public/index.php'):
        root_prefix = name.rsplit('/public/index.php', 1)[0] + '/'
        break
if not root_prefix:
    raise SystemExit('Could not locate Espo root in ZIP')

dest = Path(sys.argv[2])
for member in archive.namelist():
    if not member.startswith(root_prefix) or member.endswith('/'):
        continue
    rel = member[len(root_prefix):]
    target = dest / rel
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(archive.read(member))
PY

# Strip any bundled custom modules — clean install must receive them only via ZIP.
rm -rf \
    "$PROJECT_DIR/custom/Espo/Modules/GoogleIntegration" \
    "$PROJECT_DIR/custom/Espo/Modules/NonprofitEspocrm" \
    "$PROJECT_DIR/custom/Espo/Modules/SafehouseAuroraThemes" \
    "$PROJECT_DIR/client/custom/modules/google-integration" \
    "$PROJECT_DIR/client/custom/modules/nonprofit-espocrm" \
    "$PROJECT_DIR/client/custom/css/safehouse-aurora" \
    "$PROJECT_DIR/client/fonts/jet-brains-sans"

copy_smokes() {
    mkdir -p "$PROJECT_DIR/bin/lib"
    cp "$ROOT_DIR/bin/setup-roles.php" "$PROJECT_DIR/bin/"
    cp "$ROOT_DIR/bin/reorder-safehouse-tabs.php" "$PROJECT_DIR/bin/"
    cp "$ROOT_DIR/bin/provision-smoke-api-user.php" "$PROJECT_DIR/bin/"
    for f in "$ROOT_DIR"/bin/smoke-*.php; do
        cp "$f" "$PROJECT_DIR/bin/"
    done
    if [[ -d "$ROOT_DIR/bin/lib" ]]; then
        cp -a "$ROOT_DIR/bin/lib/." "$PROJECT_DIR/bin/lib/"
    fi
}

copy_smokes

if [[ "$SKIP_DDEV_START" -eq 1 ]]; then
    echo "Prepared $PROJECT_DIR (DDEV not started)."
    exit 0
fi

cd "$PROJECT_DIR"
ddev config \
    --project-type=php \
    --docroot=public \
    --php-version=8.3 \
    --database=mariadb:10.11 \
    --webserver-type=nginx-fpm \
    --project-name="$PROJECT_NAME" \
    --disable-upload-dirs-warning \
    >/dev/null

mkdir -p "$PROJECT_DIR/.ddev/nginx"
cp "$ROOT_DIR/.ddev/nginx/espocrm.conf" "$PROJECT_DIR/.ddev/nginx/espocrm.conf"

ddev start

run_install_step() {
    local action="$1"
    local data="${2:-}"
    if [[ -n "$data" ]]; then
        ddev exec bash -lc "php install/cli.php -a ${action} -d '${data}'"
    else
        ddev exec php install/cli.php -a "$action"
    fi
}

if [[ -f "$PROJECT_DIR/data/config.php" ]]; then
    echo "Espo already installed in $PROJECT_NAME"
else
    echo "Running Espo CLI installer..."
    run_install_step settingsTest 'dbPlatform=Mysql&hostName=db&dbName=db&dbUserName=db&dbUserPass=db'
    run_install_step setupConfirmation 'db-platform=Mysql&host-name=db&db-name=db&db-user-name=db&db-user-password=db'
    run_install_step checkPermission
    run_install_step saveSettings "site-url=${SITE_URL}&default-permissions-user=www-data&default-permissions-group=www-data"
    run_install_step buildDatabase
    run_install_step createUser 'user-name=admin&user-pass=admin123'
    run_install_step savePreferences 'language=en_US&timeZone=Europe/Rome&dateFormat=DD/MM/YYYY&timeFormat=HH:mm'
    run_install_step finish
    ddev exec php command.php rebuild
    ddev exec php bin/provision-smoke-api-user.php
fi

mkdir -p "$PROJECT_DIR/dist"
for zip_path in "${INSTALL_ZIPS[@]}"; do
    if [[ ! -f "$zip_path" ]]; then
        echo "ZIP not found: $zip_path" >&2
        exit 1
    fi
    cp "$zip_path" "$PROJECT_DIR/dist/"
    echo "Installing extension $(basename "$zip_path")..."
    ddev exec php command.php extension --file="dist/$(basename "$zip_path")"
    ddev exec php command.php rebuild
done

echo ""
echo "Provisioned: $SITE_URL"
echo "Admin: admin / admin123"
echo "Project: $PROJECT_DIR"
