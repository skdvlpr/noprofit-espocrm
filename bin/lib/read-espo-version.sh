#!/usr/bin/env bash
# Print EspoCRM version for test-build (package.json fallback when CLI is unavailable).
read_espo_version() {
    local version=""

    if command -v php >/dev/null 2>&1 && [[ -f command.php ]]; then
        version="$(php command.php version 2>/dev/null | tr -d '\r\n' || true)"
    fi

    if [[ "$version" =~ ^10\.[0-9]+\.[0-9]+$ ]]; then
        echo "$version"
        return 0
    fi

    python3 - <<'PY'
import json
from pathlib import Path
print(json.loads(Path("package.json").read_text(encoding="utf-8"))["version"])
PY
}
