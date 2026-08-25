#!/usr/bin/env bash
# Build production ZIP for cPanel installer: dist/jobazmoon-core.zip
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
exec php "$ROOT/scripts/build-cpanel-package.php" "$@"
