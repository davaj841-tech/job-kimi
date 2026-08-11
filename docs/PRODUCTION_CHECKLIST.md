# Production deploy checklist

Target: `https://jobazmoon.ir` (or `APP_URL` on the VPS)

Preferred: run **`./deploy.sh production`** on the Linux server — it already covers steps 1–7 below (maintenance → backup → pull/build → migrate → cache → Horizon → up).

## Manual sequence (if not using deploy.sh)

```bash
cd /var/www/jobazmoon   # adjust path

# ۱. Maintenance mode (bypass: https://jobazmoon.ir/your-secret-key)
php artisan down --secret="your-secret-key"

# ۲. Backup دستی (قبل deploy)
./scripts/backup.sh

# ۳. Deploy code + assets
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
# or: ./deploy.sh production   # includes all steps

# ۴. Migration + public disk
php artisan migrate --force
php artisan storage:link --force

# ۵. Cache clear + rebuild
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ۶. Horizon restart
php artisan horizon:terminate
# supervisor/systemd will respawn Horizon

# ۷. Up
php artisan up
```

## One-liner on server

```bash
DEPLOY_SECRET='your-secret-key' ./deploy.sh production
curl -fsS https://jobazmoon.ir/health | jq .
```

## First-time host (once)

See **[deploy/README.md](../deploy/README.md)**:

- Cron: `* * * * * php artisan schedule:run`
- systemd: `deploy/horizon.service`
- `.env` from `.env.production.example` (`APP_KEY`, SMS, ZarinPal, Redis, `SESSION_SECURE_COOKIE=true`, `TELESCOPE_ENABLED=false`)
- Admin Settings: `sms_api_key`, `zarinpal_merchant_id`, `zarinpal_sandbox=false`

## Notes

| Item | Detail |
|------|--------|
| Windows / Laragon | `deploy.sh` refuses to run — deploy only on the Linux VPS |
| Backup failure | Aborts production deploy; soft-warn on staging |
| Seeders | Skipped on production unless `RUN_SEEDERS=1` |
| Bypass | Visit `https://jobazmoon.ir/<secret>` while in maintenance |
| Post-check | `/health`, CSP header, then full [MONITORING_CHECKLIST.md](./MONITORING_CHECKLIST.md) |

## After deploy

See **[MONITORING_CHECKLIST.md](./MONITORING_CHECKLIST.md)** — Sentry, Horizon, Telescope, Cloudflare, CSP log, Zarinpal.

## Status (local agent)

Cannot execute production deploy from this workstation (script is Linux-only; live `/health` previously timed out from this network). Run the sequence on the VPS SSH session.
