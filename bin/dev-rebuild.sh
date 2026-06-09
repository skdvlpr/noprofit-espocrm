#!/usr/bin/env bash
set -euo pipefail

# Canonical dev refresh after metadata / theme / client asset changes:
# 1. rebuild → clearCache + metadata + BumpAppTimestamp (appTimestamp + cacheTimestamp)
# 2. theme/cssList partials are in app/client.json — NOT @import (those skip ?r= bust)
#
# Verify: ddev exec php bin/smoke-theme-assets.php

ddev exec php command.php rebuild
