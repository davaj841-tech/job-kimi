#!/bin/bash
# JobAzmoon - Pre-launch checks (run on the server)
# Usage: ./scripts/pre-launch-checks.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

fail() { echo "FAIL: $*" >&2; exit 1; }
ok() { echo "OK: $*"; }

echo "=== JobAzmoon pre-launch ==="

[[ -f .env ]] || fail ".env missing"
grep -q '^APP_KEY=base64:' .env || fail "APP_KEY not set"
grep -qE '^APP_ENV=production' .env || echo "WARN: APP_ENV is not production"
grep -qE '^APP_DEBUG=false' .env || fail "APP_DEBUG must be false"
grep -qE '^APP_URL=https://' .env || echo "WARN: APP_URL should be https://"
grep -qE '^TELESCOPE_ENABLED=false' .env || echo "WARN: TELESCOPE_ENABLED should be false"
grep -qE '^SMS_ALLOW_LOG_FALLBACK=false' .env || echo "WARN: SMS_ALLOW_LOG_FALLBACK should be false in production"
grep -qE '^TRUSTED_PROXIES=.+' .env || echo "WARN: TRUSTED_PROXIES empty — HTTPS behind proxy may break"

[[ -d public/build ]] || fail "public/build missing — run npm run build"
[[ -e public/storage ]] || fail "public/storage missing — run php artisan storage:link"
[[ -w storage ]] || fail "storage not writable"
[[ -w bootstrap/cache ]] || fail "bootstrap/cache not writable"

php artisan about >/dev/null || fail "artisan about failed"

echo ""
echo "=== Health endpoints (local) ==="
php artisan route:list --path=health >/dev/null && ok "health route registered"
php artisan route:list --path=up >/dev/null && ok "up route registered"

echo ""
echo "=== Maintenance ==="
php artisan up || true

echo ""
echo "=== Pre-launch checks complete ==="
echo "Also verify: cron schedule:run, queue/Horizon, SMS + ZarinPal in Admin Settings."
