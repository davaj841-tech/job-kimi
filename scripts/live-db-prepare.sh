#!/bin/bash
# JobAzmoon - Database preparation for LOCAL / STAGING only
# WARNING: migrate:fresh DROPS ALL TABLES — never run on a live DB with real users.
set -euo pipefail

cd "$(dirname "$0")/.."

APP_ENV="$(grep -E '^APP_ENV=' .env 2>/dev/null | cut -d= -f2- | tr -d '\r' || true)"
if [[ "${APP_ENV}" == "production" ]]; then
  echo "Refused: live-db-prepare.sh must not run when APP_ENV=production" >&2
  exit 1
fi

if [[ "${ALLOW_FRESH_MIGRATE:-0}" != "1" ]]; then
  echo "Refused: set ALLOW_FRESH_MIGRATE=1 to confirm you accept wiping the database." >&2
  exit 1
fi

echo "=== JobAzmoon: Fresh migrate + seed (APP_ENV=${APP_ENV:-unknown}) ==="
php artisan migrate:fresh --seed --force

php artisan db:seed --class=SettingSeeder --force

echo "=== Database ready ==="
echo "Change the seeded admin password immediately after first login."
