# Server install (Linux)

Copy `.env.production.example` to `.env`, fill secrets, then:

```bash
php artisan key:generate
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

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

## Admin settings that must be live

1. `KAVENEGAR_API_KEY` or Settings → `sms_api_key`
2. `ZARINPAL_MERCHANT_ID` or Settings → `zarinpal_merchant_id`
3. Settings → `zarinpal_sandbox` = off (or `ZARINPAL_SANDBOX=false` if DB value is empty)
4. `APP_DEBUG=false`, `TELESCOPE_ENABLED=false`, `SESSION_SECURE_COOKIE=true`

Then `./deploy.sh production`.
