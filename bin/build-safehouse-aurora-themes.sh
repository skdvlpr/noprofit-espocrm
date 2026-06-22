#!/usr/bin/env bash
set -euo pipefail

# Builds a standalone Espo extension ZIP for `SafehouseAuroraThemes`
# (Safehouse Aurora dark + light themes). The same module is also bundled
# inside the SafehouseCrm package by bin/build.sh, so the themes ship with
# both the CRM and as a self-contained themes extension.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODULE_PATH="custom/Espo/Modules/SafehouseAuroraThemes"
MANIFEST_PATH="$ROOT_DIR/$MODULE_PATH/manifest.json"

if [[ ! -f "$MANIFEST_PATH" ]]; then
    echo "Missing manifest: $MANIFEST_PATH" >&2
    exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
    echo "python3 is required to read manifest.json" >&2
    exit 1
fi

VERSION="$(python3 - "$MANIFEST_PATH" <<'PY'
import json
import sys

with open(sys.argv[1], encoding="utf-8") as handle:
    data = json.load(handle)

version = data.get("version")
if not isinstance(version, str) or not version:
    raise SystemExit("manifest.json must define a non-empty string version")

print(version)
PY
)"

PACKAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/safehouse-aurora-themes-package.XXXXXX")"
trap 'rm -rf "$PACKAGE_DIR"' EXIT

THEME_CSS_PATH="client/custom/css/safehouse-aurora"
THEME_FONT_PATH="client/fonts/jet-brains-sans"

mkdir -p "$PACKAGE_DIR/files/custom/Espo/Modules" "$PACKAGE_DIR/scripts" "$ROOT_DIR/dist"

cp "$MANIFEST_PATH" "$PACKAGE_DIR/manifest.json"
cp -a "$ROOT_DIR/$MODULE_PATH" "$PACKAGE_DIR/files/custom/Espo/Modules/"
cp "$ROOT_DIR/bin/packaging/SafehouseAuroraThemes-zip-AfterInstall.php" "$PACKAGE_DIR/scripts/AfterInstall.php"

# Theme runtime assets (CSS + font) live under client/, shared with SafehouseCrm.
if [[ -d "$ROOT_DIR/$THEME_CSS_PATH" ]]; then
    mkdir -p "$PACKAGE_DIR/files/client/custom/css"
    cp -a "$ROOT_DIR/$THEME_CSS_PATH" "$PACKAGE_DIR/files/client/custom/css/"
fi

if [[ -d "$ROOT_DIR/$THEME_FONT_PATH" ]]; then
    mkdir -p "$PACKAGE_DIR/files/client/fonts"
    cp -a "$ROOT_DIR/$THEME_FONT_PATH" "$PACKAGE_DIR/files/client/fonts/"
fi

OUTPUT="$ROOT_DIR/dist/safehouse-aurora-themes-v${VERSION}.zip"
rm -f "$OUTPUT"

python3 - "$PACKAGE_DIR" "$OUTPUT" <<'PY'
import os
import sys
import zipfile

package_dir = sys.argv[1]
output = sys.argv[2]

with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
    for root, dirs, files in os.walk(package_dir):
        dirs[:] = [name for name in dirs if name != ".git"]

        for name in files:
            if name in {".DS_Store", ".gitignore", ".gitattributes"} or "_test" in name:
                continue

            path = os.path.join(root, name)
            archive.write(path, os.path.relpath(path, package_dir))
PY

echo "Built $OUTPUT"
