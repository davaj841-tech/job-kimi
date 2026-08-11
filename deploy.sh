#!/bin/bash
# JobAzmoon deployment
# Usage:
#   ./deploy.sh              # production defaults
#   ./deploy.sh staging
#   ./deploy.sh production
#
# Env overrides:
#   DEPLOY_BRANCH=main
#   DEPLOY_SECRET=your-secret-key   # bypass URL: /DEPLOY_SECRET
#   RUN_SEEDERS=1                   # production only
#   SKIP_MAINTENANCE=1              # skip down/up (not recommended)
set -euo pipefail

ENV_NAME="${1:-production}"
case "$ENV_NAME" in
  staging|production|prod)
    if [[ "$ENV_NAME" == "prod" ]]; then ENV_NAME="production"; fi
    ;;
  *)
    echo "Usage: $0 [staging|production]" >&2
    exit 1
    ;;
esac

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

echo "=== JobAzmoon Deployment ($ENV_NAME) ==="

if [[ ! -f .env ]]; then
  echo "ERROR: .env missing on this host. Aborting." >&2
  exit 1
fi

# Safety: do not run full deploy on a Windows/dev tree by accident
UNAME="$(uname -s 2>/dev/null || echo unknown)"
if [[ "$UNAME" == MINGW* ]] || [[ "$UNAME" == MSYS* ]] || [[ "$UNAME" == CYGWIN* ]]; then
  echo "ERROR: deploy.sh is for Linux servers only (detected Windows/Git Bash)." >&2
  exit 1
fi

MAINTENANCE_ON=0
bring_up() {
  if [[ "$MAINTENANCE_ON" -eq 1 ]]; then
    echo "=== Bringing application up ==="
    php artisan up || true
    MAINTENANCE_ON=0
  fi
}
trap bring_up EXIT

# 1. Maintenance mode (bypass: https://app.example/DEPLOY_SECRET)
if [[ "${SKIP_MAINTENANCE:-0}" != "1" ]]; then
  echo "=== Maintenance mode ==="
  SECRET="${DEPLOY_SECRET:-${MAINTENANCE_SECRET:-deploy-$(date +%s)}}"
  php artisan down --secret="$SECRET" --retry=60
  MAINTENANCE_ON=1
  echo "Bypass path: /$SECRET"
fi

# 2. Pre-deployment backup (soft-fail on staging; harder warn on production)
if [[ -x "$ROOT/scripts/backup.sh" ]]; then
  echo "=== Pre-deployment backup ==="
  BACKUP_DIR="${BACKUP_DIR:-$ROOT/storage/backups/deploy}" \
    "$ROOT/scripts/backup.sh" || {
      if [[ "$ENV_NAME" == "production" ]]; then
        echo "ERROR: pre-deploy backup failed on production. Aborting." >&2
        exit 1
      fi
      echo "WARN: pre-deploy backup failed — continuing on staging"
    }
else
  echo "WARN: scripts/backup.sh missing or not executable"
  if [[ "$ENV_NAME" == "production" ]]; then
    echo "ERROR: backup script required for production deploy." >&2
    exit 1
  fi
fi

# 3. Pull code
BRANCH="${DEPLOY_BRANCH:-main}"
git pull origin "$BRANCH"

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# 4. Database
php artisan migrate --force
if [[ "$ENV_NAME" == "staging" ]]; then
  php artisan db:seed --force
else
  if [[ "${RUN_SEEDERS:-0}" == "1" ]]; then
    php artisan db:seed --force
  else
    echo "Skipping db:seed on production (set RUN_SEEDERS=1 to force)."
  fi
fi

# 5. Public storage + cache
php artisan storage:link --force
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize

# Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod 640 .env

# 6. Horizon / queue restart
php artisan queue:restart
php artisan horizon:terminate 2>/dev/null || true
if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl reload php8.3-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || true
  sudo systemctl restart horizon 2>/dev/null || true
fi

# 7. Up (also via EXIT trap)
bring_up
trap - EXIT

echo "=== Deployment Complete ($ENV_NAME) ==="
echo "Next: curl -fsS \"\${APP_URL}/health\""
