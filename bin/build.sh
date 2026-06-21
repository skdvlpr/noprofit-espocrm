#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODULE_PATH="custom/Espo/Modules/SafehouseCrm"
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

PACKAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/safehouse-crm-package.XXXXXX")"
trap 'rm -rf "$PACKAGE_DIR"' EXIT

THEME_CSS_PATH="client/custom/css/safehouse-aurora"
THEME_FONT_PATH="client/fonts/jet-brains-sans"
FRONTEND_MODULE_PATH="client/custom/modules/safehouse-crm"

mkdir -p "$PACKAGE_DIR/files/custom/Espo/Modules" "$PACKAGE_DIR/scripts" "$ROOT_DIR/dist"

cp "$MANIFEST_PATH" "$PACKAGE_DIR/manifest.json"
cp -a "$ROOT_DIR/$MODULE_PATH" "$PACKAGE_DIR/files/custom/Espo/Modules/"
cp -a "$ROOT_DIR/scripts/." "$PACKAGE_DIR/scripts/"

# Safehouse Aurora themes: metadata lives in the module; runtime assets live under client/.
if [[ -d "$ROOT_DIR/$THEME_CSS_PATH" ]]; then
    mkdir -p "$PACKAGE_DIR/files/client/custom/css"
    cp -a "$ROOT_DIR/$THEME_CSS_PATH" "$PACKAGE_DIR/files/client/custom/css/"
fi

if [[ -d "$ROOT_DIR/$THEME_FONT_PATH" ]]; then
    mkdir -p "$PACKAGE_DIR/files/client/fonts"
    cp -a "$ROOT_DIR/$THEME_FONT_PATH" "$PACKAGE_DIR/files/client/fonts/"
fi

if [[ -d "$ROOT_DIR/$FRONTEND_MODULE_PATH" ]]; then
    mkdir -p "$PACKAGE_DIR/files/client/custom/modules"
    cp -a "$ROOT_DIR/$FRONTEND_MODULE_PATH" "$PACKAGE_DIR/files/client/custom/modules/"
fi

DASHLET_TPL_PATH="client/custom/res/templates/dashlet.tpl"
if [[ -f "$ROOT_DIR/$DASHLET_TPL_PATH" ]]; then
    mkdir -p "$PACKAGE_DIR/files/client/custom/res/templates"
    cp "$ROOT_DIR/$DASHLET_TPL_PATH" "$PACKAGE_DIR/files/client/custom/res/templates/"
fi

OUTPUT="$ROOT_DIR/dist/safehouse-crm-v${VERSION}.zip"
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
