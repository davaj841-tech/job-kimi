# Backup & Disaster Recovery — JobAzmoon

**RPO:** ≤ 24 hours (daily 03:15 scheduler + optional 03:00 OS cron)  
**RTO:** ≤ 4 hours (maintenance + restore dump/files + health check)  
**Do not restore `.env` from backup.** `APP_KEY` and DB credentials live only on the server.

This document is the restore runbook. Keep a printed/off-site copy.

---

## What is backed up

| Component | App ZIP (`php artisan backup:run`) | OS (`scripts/backup.sh`) |
|-----------|------------------------------------|--------------------------|
| MySQL / MariaDB dump | `database.sql` (real mysqldump; **fails if dump is empty/placeholder**) | `db_YYYYMMDD_HHMMSS.sql.gz` |
| SQLite | `database.sqlite` + marker `database.sql` | not used (run artisan) |
| Private uploads | `storage/app/private` (PDFs, resume PDFs, invoices) | same + legacy `storage/app/pdfs`, `storage/app/resumes` |
| Public uploads | `storage/app/public` (avatars, blog, banners, logos) | same |
| Manifest / checksums | `manifest.json` (sha256 of files) | `checksums_*.sha256` |
| `.env` / `APP_KEY` | **never** | **never** |
| Redis / Horizon | not included (rebuild queues) | not included |

Incomplete backups are **not** treated as success: artisan returns exit `1`, `backup.sh` returns `2`, ZIP is deleted if verify fails.

---

## Create a backup

### Application ZIP (Windows / cPanel / shared hosting)

```bash
php artisan backup:run
php artisan backup:run --verify=backup-2026-08-20_031500.zip
```

Output: `storage/backups/backup-YYYY-MM-DD_His.zip`

Scheduler (requires `* * * * * php artisan schedule:run`):

```
backup:run  daily  03:15
```

cPanel Cron example:

```
* * * * * cd /home/USER/jobazmoon && /usr/local/bin/php artisan schedule:run >/dev/null 2>&1
```

Or a dedicated daily job:

```
15 3 * * * cd /home/USER/jobazmoon && /usr/local/bin/php artisan backup:run >> storage/logs/backup-schedule.log 2>&1
```

### OS-level dump (Linux production)

```bash
./scripts/backup.sh
```

Writes to `BACKUP_DIR` (default `/var/backups/jobazmoon`):

- `db_*.sql.gz`
- `files_*.tar.gz` (`private` + `public`)
- `checksums_*.sha256`

Install cron: `cp deploy/backup.cron /etc/cron.d/jobazmoon-backup`

cPanel Cron:

```
0 3 * * * /home/USER/jobazmoon/scripts/backup.sh >> /home/USER/logs/jobazmoon-backup.log 2>&1
```

Off-site: set `BACKUP_S3_URI=s3://bucket/prefix/` and install AWS CLI. Missing `aws` **fails** the script as incomplete (exit 2).

Pre-deploy: `./deploy.sh production` already runs `scripts/backup.sh` and aborts on failure.

---

## Retention

| Layer | Env | Policy |
|-------|-----|--------|
| App ZIP | `BACKUP_KEEP_COUNT` (default 7) | Keep newest N zip files |
| Shell dumps | `BACKUP_KEEP_DAYS` (default 7) | Delete `db_*` / `files_*` / `checksums_*` older than N days |
| S3 | bucket lifecycle (configure in AWS) | App does not delete remote objects |

Same-disk backups die with the server. Off-site (`BACKUP_S3_URI`) is required for real DR.

---

## Verify a backup

```bash
php artisan backup:run --verify=backup-….zip
# or
sha256sum -c /var/backups/jobazmoon/checksums_YYYYMMDD_HHMMSS.sha256
gzip -t /var/backups/jobazmoon/db_….sql.gz
```

Admin (super_admin): `GET /api/v1/admin/backups/verify?path=backup-….zip`

A valid ZIP has:

1. Real `database.sql` **or** `*.sqlite` (not the old “mysqldump unavailable” placeholder)
2. `manifest.json` status ≠ `incomplete`
3. Non-trivial file size

---

## Restore procedure (production)

Work as root/deploy user. Confirm you have a **current** snapshot first.

### A. Restore from OS dumps (preferred)

1. Copy dumps off-site onto the target host if needed.
2. Confirm `.env` exists and `APP_KEY` is the original production key.
3. Maintenance + restore:

```bash
cd /var/www/jobazmoon
./scripts/restore.sh /var/backups/jobazmoon/db_YYYYMMDD_HHMMSS.sql.gz \
  /var/backups/jobazmoon/files_YYYYMMDD_HHMMSS.tar.gz
```

The script:

- integrity-checks gzip
- requires typing `RESTORE`
- `php artisan down`
- stops Horizon if systemd is present
- imports MySQL via `MYSQL_PWD` (password not on argv)
- extracts the files tarball into the project root
- `storage:link`, cache/config clear, queue restart, `artisan up`

4. Health:

```bash
curl -fsS "$APP_URL/up"
# or GET /health if enabled
```

5. Login as super_admin, spot-check: users, a paid PDF download, a resume PDF, a recent payment.

### B. Restore from application ZIP (staging / small sites)

```bash
php artisan down
# After placing ZIP in storage/backups:
# Admin UI → Backups → restore  OR  use BackupService in tinker only on staging
php artisan up
```

Admin restore is **super_admin only**, verifies the ZIP first, takes a safety snapshot, then overwrites DB + `storage/app/private` + `storage/app/public`. Max upload: `BACKUP_RESTORE_MAX_KB` (default 512000 KB). Large production dumps must use `scripts/restore.sh`.

Legacy ZIPs that stored `pdfs/` and `resumes/` at zip root are mapped into `storage/app/private/`.

### C. SQLite (local/dev)

ZIP contains `*.sqlite`. Restore copies it over `database.connections.sqlite.database`. Do not mix a SQLite ZIP with a MySQL production database.

---

## Failure handling

| Failure | Result |
|---------|--------|
| `mysqldump` missing or dump empty | Exception / exit 1 — **no** success ZIP |
| gzip corrupt | `backup.sh` deletes dump, exit 1 |
| No upload dirs | `backup.sh` exit 2 (incomplete) |
| S3 URI set but `aws` missing | exit 2 |
| ZIP fails verify | ZIP deleted; job `failed()` logs error without secrets |
| Queue job fails | `CreateBackupJob::failed()` → `storage/logs` |

Never log DB passwords. mysqldump/mysql use `MYSQL_PWD`, not `--password=` on the process list.

Admin API **does not** return `full_path`. Download is basename-allowlisted (`backup-*.zip` under `storage/backups`).

---

## Secrets

Backups contain PII and password hashes. Treat ZIP/SQL as confidential.

- Encrypt at rest on S3 (bucket default encryption).
- Restrict download to super_admin (already gated).
- Audit: `backup.queued`, `backup.downloaded`, `backup.restored`, `backup.restore_failed`, `backup.deleted`.
- Rotate DB password if a dump leaked; `APP_KEY` is **not** in the archive — losing `.env` is a separate incident (re-encrypt cookies/sessions; users re-login).

---

## Monthly drill

1. Restore latest dump onto a **staging** clone (different `APP_URL` / DB name).
2. Time the restore (must stay within RTO).
3. Confirm a paid PDF and a wallet ledger row match production.
4. Record date + operator in the ops log.

---

## Related files

| Path | Role |
|------|------|
| `app/Services/BackupService.php` | ZIP create / verify / restore |
| `app/Console/Commands/BackupRunCommand.php` | `backup:run` |
| `scripts/backup.sh` / `scripts/restore.sh` | OS dumps |
| `deploy/backup.cron` | nightly shell cron |
| `deploy/scheduler.cron` | `schedule:run` every minute |
| `routes/console.php` | daily `backup:run` at 03:15 |
