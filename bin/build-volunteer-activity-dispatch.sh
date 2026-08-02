#!/usr/bin/env bash
# Build VolunteerActivityDispatch extension ZIP (Espo 10+).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(python3 -c "import json; print(json.load(open('$ROOT/custom/Espo/Modules/VolunteerActivityDispatch/manifest.json'))['version'])")"
OUT="$ROOT/dist/volunteer-activity-dispatch-v${VERSION}.zip"
rm -f "$OUT"
mkdir -p "$ROOT/dist" "$ROOT/build/vad-pkg/files/custom/Espo/Modules/VolunteerActivityDispatch"
cp "$ROOT/custom/Espo/Modules/VolunteerActivityDispatch/manifest.json" "$ROOT/build/vad-pkg/manifest.json"
mkdir -p "$ROOT/build/vad-pkg/scripts"
cat > "$ROOT/build/vad-pkg/scripts/AfterInstall.php" <<'PHP'
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
  "$ROOT/build/vad-pkg/files/custom/Espo/Modules/VolunteerActivityDispatch/"
mkdir -p "$ROOT/build/vad-pkg/files/client/custom/modules/volunteer-activity-dispatch"
rsync -a --delete \
  "$ROOT/client/custom/modules/volunteer-activity-dispatch/" \
  "$ROOT/build/vad-pkg/files/client/custom/modules/volunteer-activity-dispatch/"
(cd "$ROOT/build/vad-pkg" && zip -qr "$OUT" manifest.json scripts files)
echo "Built $OUT"
