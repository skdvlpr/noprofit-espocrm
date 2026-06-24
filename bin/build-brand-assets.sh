#!/usr/bin/env bash
# Generate navbar logo + favicon assets for EspoCRM from module branding SVG.
#
# Source: custom/Espo/Modules/NonprofitEspocrm/Resources/branding/logo.svg
# Output: client/img/{logo.svg,logo-light.svg,favicon.svg,favicon.ico,...}
#
# Usage:
#   bin/build-brand-assets.sh
#   ddev exec bash bin/build-brand-assets.sh
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC_SVG="$ROOT_DIR/custom/Espo/Modules/NonprofitEspocrm/Resources/branding/logo.svg"
OUT_DIR="$ROOT_DIR/client/img"
WORK_PNG="$ROOT_DIR/data/tmp/brand-source.png"

if [[ ! -f "$SRC_SVG" ]]; then
    echo "Missing source SVG: $SRC_SVG" >&2
    exit 1
fi

mkdir -p "$OUT_DIR" "$(dirname "$WORK_PNG")"

python3 - "$SRC_SVG" "$WORK_PNG" <<'PY'
import base64
import re
import sys
from pathlib import Path

src = Path(sys.argv[1])
out = Path(sys.argv[2])
text = src.read_text(encoding='utf-8')
matches = re.findall(r'xlink:href="(data:image/[^"]+)"', text)
if not matches:
    raise SystemExit('No embedded raster image in branding SVG — cannot build favicon.ico')

# Prefer the last embedded PNG (color artwork in exported SVGs).
href = matches[-1]
m = re.match(r'data:image/(\w+);base64,(.+)', href)
if not m:
    raise SystemExit('Unsupported embedded image format')
out.write_bytes(base64.b64decode(m.group(2)))
print(f'Extracted {m.group(1)} → {out} ({out.stat().st_size} bytes)')
PY

copy_svg() {
    local dest="$1"
    cp "$SRC_SVG" "$dest"
}

copy_svg "$OUT_DIR/logo.svg"
copy_svg "$OUT_DIR/logo-light.svg"
copy_svg "$OUT_DIR/logo-lite.svg"
copy_svg "$OUT_DIR/favicon.svg"

run_convert() {
    if command -v convert >/dev/null 2>&1; then
        convert "$@"
        return
    fi
    if command -v ddev >/dev/null 2>&1 && [[ -f "$ROOT_DIR/.ddev/config.yaml" ]]; then
        ddev exec convert "$@"
        return
    fi
    echo "ImageMagick convert not found (install locally or use DDEV)." >&2
    exit 1
}

# Paths for ddev: rewrite repo root to container /var/www/html
ddev_path() {
    local p="$1"
    if [[ "$p" == "$ROOT_DIR"* ]] && command -v ddev >/dev/null 2>&1 && [[ -f "$ROOT_DIR/.ddev/config.yaml" ]]; then
        echo "/var/www/html${p#$ROOT_DIR}"
    else
        echo "$p"
    fi
}

SRC_ARG="$(ddev_path "$WORK_PNG")"
OUT_DIR_ARG="$(ddev_path "$OUT_DIR")"

run_convert "$SRC_ARG" \
    -background none \
    -define icon:auto-resize=16,32,48,64 \
    "$OUT_DIR_ARG/favicon.ico"

run_convert "$SRC_ARG" -resize 180x180 "$OUT_DIR_ARG/apple-touch-icon.png"
run_convert "$SRC_ARG" -resize 196x196 "$OUT_DIR_ARG/favicon-196.png"
run_convert "$SRC_ARG" -resize 256x256 "$OUT_DIR_ARG/logo.png"

echo ""
echo "Brand assets written to $OUT_DIR:"
ls -la "$OUT_DIR"/logo.svg "$OUT_DIR"/favicon.svg "$OUT_DIR"/favicon.ico \
    "$OUT_DIR"/apple-touch-icon.png "$OUT_DIR"/favicon-196.png "$OUT_DIR"/logo.png
