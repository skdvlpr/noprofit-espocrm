#!/usr/bin/env bash
# Build the unified NonprofitEspocrm suite ZIP (multi-module, extractable pieces).
# Local / CI only — never run on production hosts.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODULE_PATH="custom/Espo/Modules/NonprofitEspocrm"
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

PACKAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/nonprofit-espocrm-package.XXXXXX")"
trap 'rm -rf "$PACKAGE_DIR"' EXIT

mkdir -p "$PACKAGE_DIR/files/custom/Espo/Modules" \
    "$PACKAGE_DIR/files/client/custom/modules" \
    "$PACKAGE_DIR/scripts" \
    "$ROOT_DIR/dist"

cp "$MANIFEST_PATH" "$PACKAGE_DIR/manifest.json"
cp -a "$ROOT_DIR/scripts/." "$PACKAGE_DIR/scripts/"

# Suite modules (stay separate on disk for later extractability).
copy_module() {
    local rel="$1"
    if [[ -d "$ROOT_DIR/$rel" ]]; then
        cp -a "$ROOT_DIR/$rel" "$PACKAGE_DIR/files/custom/Espo/Modules/"
    fi
}

copy_frontend() {
    local rel="$1"
    if [[ -d "$ROOT_DIR/$rel" ]]; then
        cp -a "$ROOT_DIR/$rel" "$PACKAGE_DIR/files/client/custom/modules/"
    fi
}

copy_module "custom/Espo/Modules/NonprofitEspocrm"
copy_module "custom/Espo/Modules/GoogleIntegration"
copy_module "custom/Espo/Modules/WorkflowEngine"
copy_module "custom/Espo/Modules/SafehouseAuroraThemes"
copy_module "custom/Espo/Modules/BugTracker"

copy_frontend "client/custom/modules/nonprofit-espocrm"
copy_frontend "client/custom/modules/google-integration"
copy_frontend "client/custom/modules/workflow-engine"
copy_frontend "client/custom/modules/bug-tracker"

THEME_CSS_PATH="client/custom/css/safehouse-aurora"
THEME_FONT_PATH="client/fonts/jet-brains-sans"

if [[ -d "$ROOT_DIR/$THEME_CSS_PATH" ]]; then
    mkdir -p "$PACKAGE_DIR/files/client/custom/css"
    cp -a "$ROOT_DIR/$THEME_CSS_PATH" "$PACKAGE_DIR/files/client/custom/css/"
fi

if [[ -d "$ROOT_DIR/$THEME_FONT_PATH" ]]; then
    mkdir -p "$PACKAGE_DIR/files/client/fonts"
    cp -a "$ROOT_DIR/$THEME_FONT_PATH" "$PACKAGE_DIR/files/client/fonts/"
fi

DASHLET_TPL_PATH="client/custom/res/templates/dashlet.tpl"
if [[ -f "$ROOT_DIR/$DASHLET_TPL_PATH" ]]; then
    mkdir -p "$PACKAGE_DIR/files/client/custom/res/templates"
    cp "$ROOT_DIR/$DASHLET_TPL_PATH" "$PACKAGE_DIR/files/client/custom/res/templates/"
fi

# Drop GoogleIntegration docs from the suite package (keep in repo / standalone GI build).
rm -rf "$PACKAGE_DIR/files/custom/Espo/Modules/GoogleIntegration/docs"

OUTPUT="$ROOT_DIR/dist/nonprofit-espocrm-v${VERSION}.zip"
rm -f "$OUTPUT"

python3 - "$PACKAGE_DIR" "$OUTPUT" <<'PY'
import os
import sys
import zipfile

package_dir = sys.argv[1]
output = sys.argv[2]

# Slim ZIP: skip VCS junk, tests, and vendor noise under web-push / node_modules.
SKIP_DIR_NAMES = {
    ".git",
    "node_modules",
    "tests",
    "test",
    ".github",
}
SKIP_FILE_SUBSTRINGS = ("_test", ".phpunit", "phpunit.xml")
SKIP_EXACT = {".DS_Store", ".gitignore", ".gitattributes", "README.md", "CHANGELOG.md"}

with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
    for root, dirs, files in os.walk(package_dir):
        dirs[:] = [name for name in dirs if name not in SKIP_DIR_NAMES]

        rel_root = os.path.relpath(root, package_dir)
        # Extra slim for Composer web-push trees if present
        if "/vendor/" in rel_root.replace("\\", "/") and any(
            part in rel_root for part in ("web-push", "minishlink")
        ):
            dirs[:] = [d for d in dirs if d not in {"tests", "docs", "examples"}]

        for name in files:
            if name in SKIP_EXACT or any(s in name for s in SKIP_FILE_SUBSTRINGS):
                continue
            path = os.path.join(root, name)
            archive.write(path, os.path.relpath(path, package_dir))

print(output)
PY

echo "Built $OUTPUT (suite: NonprofitEspocrm + GoogleIntegration + WorkflowEngine + themes + BugTracker)"
echo "Standalone extractable builds remain: bin/build-google-integration.sh, bin/build-workflow-engine.sh, bin/build-bug-tracker.sh, bin/build-safehouse-aurora-themes.sh"
