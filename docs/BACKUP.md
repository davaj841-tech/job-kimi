# Backup & Disaster Recovery

**RTO: 4 hours** | **RPO: 24 hours**

JobAzmoon stores critical state in MySQL (or SQLite locally), Redis (cache/queues), and uploaded files under `storage/app/public`, `storage/app/pdfs`, and `storage/app/resumes`.

---

## Objectives

| Metric | Target | Meaning |
|--------|--------|---------|
| **RTO** | ≤ 4 hours | Time from declare-outage to serving traffic again |
| **RPO** | ≤ 24 hours | Maximum acceptable data loss (daily backup cadence) |

Monthly restore drill on a staging clone is required to keep RTO realistic.

---

## Layers

| Layer | Tool | Schedule | Retention | Location |
|-------|------|----------|-----------|----------|
| App ZIP (DB dump + PDFs + resumes + public) | `php artisan backup:run` (`BackupService`) | Daily 03:00 via Scheduler | Last 7 zips | `storage/backups/backup-*.zip` |
| OS-level DB + upload trees | `scripts/backup.sh` | Cron 03:00 (production) | `BACKUP_KEEP_DAYS` (default 7) | `/var/backups/jobazmoon` (or `BACKUP_DIR`) |
| Pre-deploy snapshot | `deploy.sh` → `backup.sh` | Every production deploy | Same as OS-level | `storage/backups/deploy` or `BACKUP_DIR` |
| Off-site | `aws s3 sync` when `BACKUP_S3_URI` set | With shell backup | Bucket lifecycle | e.g. `s3://jobazmoon-backups/` |
| Optional | Spatie Laravel Backup | See below | Per config | Published disks |

Scheduler cron:

```bash
* * * * * cd /var/www/jobazmoon && php artisan schedule:run >> /dev/null 2>&1
```

Dedicated shell backup:

```bash
0 3 * * * /var/www/jobazmoon/scripts/backup.sh >> /var/log/jobazmoon-backup.log 2>&1
```

---

## Environment variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `BACKUP_DIR` | `/var/backups/jobazmoon` | Local dump/tar destination for `backup.sh` |
| `BACKUP_KEEP_DAYS` | `7` | Delete local `db_*.sql.gz` / `files_*.tar.gz` older than N days |
| `BACKUP_S3_URI` | _(empty)_ | If set and `aws` CLI exists, sync dumps/tars to this prefix |
| `DB_*` | from `.env` | mysqldump / restore credentials (never hard-code) |

Production `deploy.sh` **aborts** if `scripts/backup.sh` is missing or fails. Staging soft-warns and continues.

---

## Database backup

Prefer the script (loads `.env`, never prints passwords):

```bash
./scripts/backup.sh
# → $BACKUP_DIR/db_YYYYMMDD_HHMMSS.sql.gz
# → $BACKUP_DIR/files_YYYYMMDD_HHMMSS.tar.gz
```

Manual equivalent:

```bash
mysqldump -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
  --single-transaction --routines --triggers --databases "$DB_DATABASE" \
  | gzip > "db_$(date +%Y%m%d_%H%M%S).sql.gz"
```

`--single-transaction` keeps InnoDB consistent without long table locks.

Non-MySQL (SQLite/local): use `php artisan backup:run` instead of mysqldump.

---

## File backup

Included by `backup.sh` when present:

- `storage/app/public`
- `storage/app/pdfs`
- `storage/app/resumes`

```bash
tar czf "files_$(date +%Y%m%d).tar.gz" \
  -C /var/www/jobazmoon \
  storage/app/public storage/app/pdfs storage/app/resumes
```

---

## Redis

Enable AOF (or RDB) in `redis.conf`:

```conf
appendonly yes
appendfsync everysec
```

Back up `appendonly.aof` / `dump.rdb` with the same retention. Redis is mostly soft state (cache, sessions, Horizon metrics); rebuild from MySQL after restore unless you depend on durable queue payloads — drain or pause Horizon before cutover.

---

## Recovery procedure

1. Declare incident; put site in maintenance: `php artisan down --secret=… --retry=60`
2. Stop workers: `sudo systemctl stop horizon` (and any separate `queue:work`)
3. Restore DB:

   ```bash
   ./scripts/restore.sh /var/backups/jobazmoon/db_YYYYMMDD_HHMMSS.sql.gz
   # interactive confirm: type RESTORE
   ```

   Or:

   ```bash
   gunzip -c /var/backups/jobazmoon/db_YYYYMMDD_HHMMSS.sql.gz \
     | mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD"
   ```

4. Restore files:

   ```bash
   tar xzf /var/backups/jobazmoon/files_YYYYMMDD_HHMMSS.tar.gz -C /var/www/jobazmoon
   ```

5. `php artisan storage:link` if needed; `chown -R www-data:www-data storage bootstrap/cache`
6. Caches: `php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. `php artisan queue:restart`; start Horizon / php-fpm / nginx
8. `php artisan up`
9. Verify:
   - `curl -fsS "$APP_URL/health"`
   - smoke login + exam list + one wallet balance read
   - Horizon workers Active; no flood of Failed jobs

**Target clock:** steps 1–8 should fit inside the 4h RTO when backups are on the same host or already synced from S3.

---

## Verification & alerts

| Check | How |
|-------|-----|
| Backup succeeded today | `ls -lt /var/backups/jobazmoon/db_*.sql.gz \| head` or S3 listing |
| Cron exit code | non-zero → page ops (`/var/log/jobazmoon-backup.log`) |
| App ZIP | Admin Backups UI or `ls storage/backups/backup-*.zip` |
| Monthly drill | Restore to staging; time the run; update this doc if RTO slips |
| Integrity | Spot-check `gunzip -t db_….sql.gz` and open tar listing |

---

## Shell scripts

| Script | Purpose |
|--------|---------|
| `scripts/backup.sh` | mysqldump + tar uploads → `BACKUP_DIR` (+ optional S3) |
| `scripts/restore.sh` | Interactive DB restore from `.sql.gz` (maintenance + stop Horizon) |

Both read `.env` from the project root; they never echo passwords.

---

## In-app backups (admin)

- Command: `php artisan backup:run` (scheduled daily 03:00 in `routes/console.php`)
- Admin UI: Backups page → queues `CreateBackupJob`
- Output: `storage/backups/backup-*.zip` (DB SQL + pdfs/resumes/public)
- Cleanup: `BackupService` keeps the newest archives (7-day policy)

---

## Spatie Laravel Backup (alternative)

When Packagist is reachable:

```bash
composer require spatie/laravel-backup
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
cp config/backup.php.example config/backup.php
```

Defaults in `config/backup.php.example`:

- `source.files.include` → `storage/app/public`, `pdfs`, `resumes`
- `destination.disks` → `s3` when `AWS_BUCKET` is set, else `local`
- Retention via Spatie cleanup (7 days keep-all)

**Command conflict:** Spatie and JobAzmoon both register `backup:run`. Before switching, rename the app command to `ja:backup` and update `routes/console.php` + admin jobs.

---

## Secrets & safety

- Credentials only via `.env` / environment
- Do not commit `storage/backups/` or `/var/backups/`
- Encrypt off-site copies (S3 SSE or client-side) in production
- Restrict filesystem permissions on backup dirs (`750`, owner deploy/www)
- RPO 24h assumes **daily success**; alert on non-zero exit of `backup.sh`
- Never restore production dumps onto a shared laptop without redacting PII if leaving the secure network
