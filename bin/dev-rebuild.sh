#!/usr/bin/env bash
set -euo pipefail

# Rebuild metadata + DB, clear cache, and bump appTimestamp (SafehouseCrm BumpAppTimestamp).
ddev exec php command.php rebuild
