#!/usr/bin/env bash
# JobAzmoon — production backup (MySQL + upload trees)
# Usage: ./scripts/backup.sh
# Cron:  0 3 * * * /path/to/project/scripts/backup.sh >> /var/log/jobazmoon-backup.log 2>&1
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "ERROR: .env not found in $ROOT" >&2
  exit 1
fi

# Load .env via PHP (no secrets echoed)
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

BACKUP_DIR="${BACKUP_DIR:-/var/backups/jobazmoon}"
S3_BUCKET="${BACKUP_S3_URI:-}"
DATE="$(date +%Y%m%d_%H%M%S)"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-7}"

mkdir -p "$BACKUP_DIR"

DB_CONNECTION="${DB_CONNECTION:-mysql}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [[ "$DB_CONNECTION" != "mysql" && "$DB_CONNECTION" != "mariadb" ]]; then
  echo "WARN: DB_CONNECTION=$DB_CONNECTION — skipping mysqldump (use: php artisan backup:run)."
else
  if [[ -z "$DB_DATABASE" || -z "$DB_USERNAME" ]]; then
    echo "ERROR: DB_DATABASE / DB_USERNAME missing from .env" >&2
    exit 1
  fi

  DUMP_ARGS=(
    -h "$DB_HOST"
    -P "$DB_PORT"
    -u "$DB_USERNAME"
    --single-transaction
    --routines
    --triggers
    --databases "$DB_DATABASE"
  )

  export MYSQL_PWD="$DB_PASSWORD"
  echo "Dumping database ${DB_DATABASE}..."
  mysqldump "${DUMP_ARGS[@]}" | gzip -c > "$BACKUP_DIR/db_${DATE}.sql.gz"
  unset MYSQL_PWD
  echo "DB backup: $BACKUP_DIR/db_${DATE}.sql.gz"
fi

echo "Archiving upload directories..."
FILE_LIST=()
for d in storage/app/public storage/app/pdfs storage/app/resumes; do
  if [[ -d "$d" ]]; then
    FILE_LIST+=("$d")
  fi
done

if ((${#FILE_LIST[@]})); then
  tar czf "$BACKUP_DIR/files_${DATE}.tar.gz" "${FILE_LIST[@]}"
  echo "Files backup: $BACKUP_DIR/files_${DATE}.tar.gz"
else
  echo "WARN: no upload directories found to archive"
fi

if [[ -n "$S3_BUCKET" ]] && command -v aws >/dev/null 2>&1; then
  echo "Syncing to ${S3_BUCKET}..."
  aws s3 sync "$BACKUP_DIR/" "$S3_BUCKET/" \
    --exclude "*" \
    --include "db_*.sql.gz" \
    --include "files_*.tar.gz"
elif [[ -n "$S3_BUCKET" ]]; then
  echo "WARN: aws CLI not found; skipped S3 sync"
fi

find "$BACKUP_DIR" -type f -name 'db_*.sql.gz' -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true
find "$BACKUP_DIR" -type f -name 'files_*.tar.gz' -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true

echo "Backup completed: $DATE"
