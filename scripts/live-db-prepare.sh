#!/bin/bash
# JobAzmoon - Phase 11 Step 4.1: Database preparation for live testing
# WARNING: migrate:fresh DROPS ALL TABLES — never run on a live DB with real users.
set -e

cd /var/www/jobazmoon

echo "=== JobAzmoon: Fresh migrate + seed ==="
php artisan migrate:fresh --seed --force

# SettingSeeder already runs via DatabaseSeeder; re-run if you need to refresh settings only.
php artisan db:seed --class=SettingSeeder --force

echo "=== Database ready for live testing ==="
echo "Admin seed mobile (check AdminUserSeeder): typically 09120000000"
