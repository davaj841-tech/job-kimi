#!/bin/bash
# JobAzmoon - Phase 11 Step 5.1: Pre-launch final checks (last 5 minutes)
# Run on production: cd /var/www/jobazmoon && ./scripts/pre-launch-checks.sh
set -e

cd /var/www/jobazmoon

echo "=== 1. Maintenance mode (must be UP) ==="
php artisan up

echo ""
echo "=== 2. Disk space (need > 20% free) ==="
df -h /

echo ""
echo "=== 3. RAM (need > 20% free) ==="
free -h

echo ""
echo "=== 4. Services ==="
sudo systemctl status nginx --no-pager -l || true
sudo systemctl status php8.3-fpm --no-pager -l || true
sudo systemctl status redis --no-pager -l || true
sudo supervisorctl status jobazmoon-worker:* || true

echo ""
echo "=== 5. Recent Laravel errors (expect empty) ==="
if [ -f storage/logs/laravel.log ]; then
  MATCHES=$(tail -n 50 storage/logs/laravel.log | grep -iE "error|critical|emergency" || true)
  if [ -z "$MATCHES" ]; then
    echo "OK: no error/critical/emergency in last 50 log lines"
  else
    echo "WARN: found issues:"
    echo "$MATCHES"
    exit 1
  fi
else
  echo "OK: no laravel.log yet"
fi

echo ""
echo "=== Pre-launch checks complete ==="
