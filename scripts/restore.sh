#!/usr/bin/env bash
# JobAzmoon — restore database from a gzipped mysqldump
# Usage: ./scripts/restore.sh [path/to/db_YYYYMMDD_HHMMSS.sql.gz]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "ERROR: .env not found in $ROOT" >&2
  exit 1
fi

eval "$(php -r '
$env = file_get_contents(".env");
foreach (explode("\n", $env) as $line) {
    $line = trim($line);
    if ($line === "" || str_starts_with($line, "#") || ! str_contains($line, "=")) {
        continue;
    }
    [$k, $v] = explode("=", $line, 2);
    $k = trim($k);
    $v = trim($v);
    if ((str_starts_with($v, "\"") && str_ends_with($v, "\"")) || (str_starts_with($v, "'\''") && str_ends_with($v, "'\''"))) {
        $v = substr($v, 1, -1);
    }
    if (! preg_match("/^[A-Z0-9_]+$/", $k)) {
        continue;
    }
    echo "export ".$k."=".escapeshellarg($v)."\n";
}
')"

BACKUP_FILE="${1:-}"
if [[ -z "$BACKUP_FILE" ]]; then
  read -r -p "Backup file to restore (db_YYYYMMDD_HHMMSS.sql.gz): " BACKUP_FILE
fi

if [[ ! -f "$BACKUP_FILE" ]]; then
  # Try under default backup dir
  if [[ -f "/var/backups/jobazmoon/$BACKUP_FILE" ]]; then
    BACKUP_FILE="/var/backups/jobazmoon/$BACKUP_FILE"
  else
    echo "ERROR: backup file not found: $BACKUP_FILE" >&2
    exit 1
  fi
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [[ -z "$DB_DATABASE" || -z "$DB_USERNAME" ]]; then
  echo "ERROR: DB_DATABASE / DB_USERNAME missing from .env" >&2
  exit 1
fi

echo "WARNING: This will overwrite database '${DB_DATABASE}' from:"
echo "  $BACKUP_FILE"
read -r -p "Type RESTORE to continue: " CONFIRM
if [[ "$CONFIRM" != "RESTORE" ]]; then
  echo "Aborted."
  exit 1
fi

echo "Putting application in maintenance mode..."
php artisan down --retry=60 || true

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl stop horizon 2>/dev/null || true
  # nginx optional — keep serving maintenance if possible
fi

export MYSQL_PWD="$DB_PASSWORD"
echo "Restoring database..."
gunzip -c "$BACKUP_FILE" | mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME"
unset MYSQL_PWD

php artisan cache:clear || true
php artisan config:clear || true
php artisan queue:restart || true

if command -v systemctl >/dev/null 2>&1; then
  sudo systemctl start horizon 2>/dev/null || true
fi

php artisan up || true

echo "Restore completed."
echo "Next: restore files with tar if needed, then verify GET /health"
echo "  tar xzf /var/backups/jobazmoon/files_YYYYMMDD_HHMMSS.tar.gz -C $ROOT"
