#!/usr/bin/env bash
# JobAzmoon — production backup (MySQL + upload trees)
# Usage: ./scripts/backup.sh
# Cron:  0 3 * * * /path/to/project/scripts/backup.sh >> /var/log/jobazmoon-backup.log 2>&1
# cPanel: Cron Jobs → once daily → command above (use full paths).
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
INCOMPLETE=0

mkdir -p "$BACKUP_DIR"

DB_CONNECTION="${DB_CONNECTION:-mysql}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

DB_FILE=""
FILES_FILE=""

if [[ "$DB_CONNECTION" != "mysql" && "$DB_CONNECTION" != "mariadb" ]]; then
  echo "WARN: DB_CONNECTION=$DB_CONNECTION — skipping mysqldump (use: php artisan backup:run)."
  INCOMPLETE=1
else
  if [[ -z "$DB_DATABASE" || -z "$DB_USERNAME" ]]; then
    echo "ERROR: DB_DATABASE / DB_USERNAME missing from .env" >&2
    exit 1
  fi

  if ! command -v mysqldump >/dev/null 2>&1; then
    echo "ERROR: mysqldump not found in PATH" >&2
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
  DB_FILE="$BACKUP_DIR/db_${DATE}.sql.gz"
  mysqldump "${DUMP_ARGS[@]}" | gzip -c > "$DB_FILE"
  unset MYSQL_PWD

  if [[ ! -s "$DB_FILE" ]]; then
    echo "ERROR: database dump is empty" >&2
    rm -f "$DB_FILE"
    exit 1
  fi

  if ! gzip -t "$DB_FILE" 2>/dev/null; then
    echo "ERROR: database dump failed gzip integrity check" >&2
    rm -f "$DB_FILE"
    exit 1
  fi

  echo "DB backup: $DB_FILE ($(du -h "$DB_FILE" | cut -f1))"
fi

echo "Archiving upload directories..."
FILE_LIST=()
# Private disk (paid PDFs, resume PDFs, invoices) + public assets
for d in storage/app/private storage/app/public storage/app/pdfs storage/app/resumes; do
  if [[ -d "$d" ]]; then
    FILE_LIST+=("$d")
  fi
done

if ((${#FILE_LIST[@]})); then
  FILES_FILE="$BACKUP_DIR/files_${DATE}.tar.gz"
  tar czf "$FILES_FILE" "${FILE_LIST[@]}"
  if [[ ! -s "$FILES_FILE" ]]; then
    echo "ERROR: files archive is empty" >&2
    rm -f "$FILES_FILE"
    exit 1
  fi
  echo "Files backup: $FILES_FILE ($(du -h "$FILES_FILE" | cut -f1))"
else
  echo "WARN: no upload directories found to archive — backup incomplete"
  INCOMPLETE=1
fi

# SHA256 sidecar for verification (does not contain secrets)
{
  echo "# JobAzmoon backup checksums ${DATE}"
  [[ -n "$DB_FILE" && -f "$DB_FILE" ]] && sha256sum "$DB_FILE"
  [[ -n "$FILES_FILE" && -f "$FILES_FILE" ]] && sha256sum "$FILES_FILE"
} > "$BACKUP_DIR/checksums_${DATE}.sha256"
echo "Checksums: $BACKUP_DIR/checksums_${DATE}.sha256"

if [[ -n "$S3_BUCKET" ]] && command -v aws >/dev/null 2>&1; then
  echo "Syncing to ${S3_BUCKET}..."
  aws s3 sync "$BACKUP_DIR/" "$S3_BUCKET/" \
    --exclude "*" \
    --include "db_*.sql.gz" \
    --include "files_*.tar.gz" \
    --include "checksums_*.sha256"
elif [[ -n "$S3_BUCKET" ]]; then
  echo "WARN: aws CLI not found; skipped S3 sync — off-site incomplete"
  INCOMPLETE=1
fi

find "$BACKUP_DIR" -type f -name 'db_*.sql.gz' -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true
find "$BACKUP_DIR" -type f -name 'files_*.tar.gz' -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true
find "$BACKUP_DIR" -type f -name 'checksums_*.sha256' -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true

if [[ "$INCOMPLETE" -ne 0 ]]; then
  echo "ERROR: Backup incomplete (see WARN lines above). Exit 2." >&2
  exit 2
fi

echo "Backup completed: $DATE"
