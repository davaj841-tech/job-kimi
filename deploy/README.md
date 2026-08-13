# Server install (Linux)

**Document root must be `public/`** (cPanel/Plesk/Nginx). Never point the vhost at the project root.

Copy `.env.production.example` → `.env`, fill secrets, then:

```bash
php artisan key:generate
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```

Or: `composer run prod-optimize` after migrate + storage:link.

Persian checklist: [`docs/HOSTING.md`](../docs/HOSTING.md)

## Cron (required)

Scheduler runs job aggregation, backups, reminders, content publish.

```bash
sudo cp deploy/scheduler.cron /etc/cron.d/jobazmoon
sudo chmod 644 /etc/cron.d/jobazmoon
```

Or crontab for `www-data`:

```
* * * * * cd /var/www/jobazmoon && php artisan schedule:run >> /dev/null 2>&1
```

## Horizon (required when QUEUE_CONNECTION=redis)

```bash
sudo cp deploy/horizon.service /etc/systemd/system/horizon.service
# edit WorkingDirectory / php path
sudo systemctl daemon-reload
sudo systemctl enable --now horizon
```

Admins and operators can open `/horizon`. Extra emails: `HORIZON_ALLOWED_EMAILS`.

### Shared hosting (no Redis)

Use `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database` and a cron `queue:work --stop-when-empty` if long workers are not allowed.

## Trusted proxies / HTTPS

Behind local Nginx SSL termination:

```
TRUSTED_PROXIES=127.0.0.1,::1
```

Only PHP behind a trusted reverse proxy may use `TRUSTED_PROXIES=*`. Empty value breaks HTTPS detection (redirect loops / mixed content).

## Admin settings that must be live

1. `KAVENEGAR_API_KEY` or Settings → `sms_api_key`
2. `ZARINPAL_MERCHANT_ID` or Settings → `zarinpal_merchant_id`
3. Settings → `zarinpal_sandbox` = off (or `ZARINPAL_SANDBOX=false` if DB value is empty)
4. Turnstile: `.env` `TURNSTILE_*` and/or Admin → security
5. `APP_DEBUG=false`, `TELESCOPE_ENABLED=false`, `SESSION_SECURE_COOKIE=true`
6. Change seeded admin password immediately

Health: `/health` and `/up`

Then `./deploy.sh production`.
