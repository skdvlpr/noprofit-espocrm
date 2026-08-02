#!/usr/bin/env bash
# Build VolunteerActivityDispatch extension ZIP (Espo 10+).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MANIFEST_PATH="$ROOT/custom/Espo/Modules/VolunteerActivityDispatch/manifest.json"

if [[ ! -f "$MANIFEST_PATH" ]]; then
    echo "Missing manifest: $MANIFEST_PATH" >&2
    exit 1
fi

VERSION="$(python3 - "$MANIFEST_PATH" <<'PY'
import json
import sys

with open(sys.argv[1], encoding="utf-8") as handle:
    version = json.load(handle).get("version")

if not isinstance(version, str) or not version:
    raise SystemExit("manifest.json must define a non-empty string version")

print(version)
PY
)"

OUT="$ROOT/dist/volunteer-activity-dispatch-v${VERSION}.zip"
PKG="$ROOT/build/vad-pkg"

rm -f "$OUT"
rm -rf "$PKG"
mkdir -p \
    "$ROOT/dist" \
    "$PKG/scripts" \
    "$PKG/files/custom/Espo/Modules/VolunteerActivityDispatch" \
    "$PKG/files/client/custom/modules/volunteer-activity-dispatch"

cp "$MANIFEST_PATH" "$PKG/manifest.json"

cat > "$PKG/scripts/AfterInstall.php" <<'PHP'
<?php
class AfterInstall {
    public function run(\Espo\Core\Container $container): void {
        (new \Espo\Modules\VolunteerActivityDispatch\Tools\Installer())->runPostInstall($container);
    }
}
PHP

rsync -a --delete \
    --exclude 'manifest.json' \
    "$ROOT/custom/Espo/Modules/VolunteerActivityDispatch/" \
    "$PKG/files/custom/Espo/Modules/VolunteerActivityDispatch/"

rsync -a --delete \
    "$ROOT/client/custom/modules/volunteer-activity-dispatch/" \
    "$PKG/files/client/custom/modules/volunteer-activity-dispatch/"

if command -v zip >/dev/null 2>&1; then
    (cd "$PKG" && zip -qr "$OUT" manifest.json scripts files)
else
    python3 - "$PKG" "$OUT" <<'PY'
import sys
import zipfile
from pathlib import Path

pkg = Path(sys.argv[1])
out = Path(sys.argv[2])
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as archive:
    for path in pkg.rglob("*"):
        if path.is_file():
            archive.write(path, path.relative_to(pkg).as_posix())
PY
fi

echo "Built $OUT"
