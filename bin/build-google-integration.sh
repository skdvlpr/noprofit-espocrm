#!/usr/bin/env bash
set -euo pipefail

# Builds a standalone Espo extension ZIP for `GoogleIntegration` (universal Google OAuth2).
# SafehouseCrm remains a separate package from `bin/build.sh`.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODULE_PATH="custom/Espo/Modules/GoogleIntegration"
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

PACKAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/google-integration-package.XXXXXX")"
trap 'rm -rf "$PACKAGE_DIR"' EXIT

CLIENT_MODULE_PATH="client/custom/modules/google-integration"

mkdir -p "$PACKAGE_DIR/files/custom/Espo/Modules" "$PACKAGE_DIR/scripts" "$ROOT_DIR/dist"

cp "$MANIFEST_PATH" "$PACKAGE_DIR/manifest.json"
cp -a "$ROOT_DIR/$MODULE_PATH" "$PACKAGE_DIR/files/custom/Espo/Modules/"
if [[ -d "$ROOT_DIR/$CLIENT_MODULE_PATH" ]]; then
    mkdir -p "$PACKAGE_DIR/files/client/custom/modules"
    cp -a "$ROOT_DIR/$CLIENT_MODULE_PATH" "$PACKAGE_DIR/files/client/custom/modules/"
fi
cp "$ROOT_DIR/bin/packaging/GoogleIntegration-zip-AfterInstall.php" "$PACKAGE_DIR/scripts/AfterInstall.php"

OUTPUT="$ROOT_DIR/dist/google-integration-v${VERSION}.zip"
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
