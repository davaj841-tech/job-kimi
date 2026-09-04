# نصب JobAzmoon روی cPanel (Shared Hosting)

این راهنما برای **نصب تمیز Production** است. برای جزئیات بسته‌سازی و به‌روزرسانی به `INSTALL.md` و `docs/CPANEL_DEPLOYMENT.md` هم مراجعه کنید.

## پیش‌نیازها

- PHP **8.2** یا **8.3** (cPanel → Select PHP Version)
- افزونه‌ها: `pdo`, `pdo_mysql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `gd`, `zip`, `curl`
- MySQL / MariaDB
- Document Root = `public_html`
- **Composer / npm / SSH روی هاست لازم نیست** اگر از Installer استفاده کنید

## چیدمان مسیر (الزامی)

```text
/home/USERNAME/
├── job/                 ← Laravel کامل (.env، vendor، storage، …)
└── public_html/         ← فقط وب (index.php → ../job)
```

Laravel را مستقیم داخل `public_html` سرو نکنید.

---

## روش توصیه‌شده — JobAzmoon Installer (بدون Composer روی هاست)

### ۱) روی سیستم توسعه / CI

```bash
bash scripts/pre-deploy-check.sh
php scripts/build-cpanel-package.php
```

خروجی:

- `dist/JobAzmoon-Installer/`
- `dist/JobAzmoon-Installer.zip`

### ۲) آپلود به cPanel

محتویات `JobAzmoon-Installer` را **مستقیم** داخل `public_html` بریزید:

```text
public_html/install.php
public_html/lib/InstallEngine.php
public_html/package/jobazmoon-core.zip
```

پوشه تودرتو نسازید (`public_html/JobAzmoon-Installer/...` مسیر `~/job` را خراب می‌کند).

### ۳) Wizard

باز کنید: `https://YOUR-DOMAIN/install.php`

Installer این‌ها را انجام می‌دهد:

- بررسی PHP / افزونه‌ها / دیسک / مجوزها
- تست اتصال MySQL
- ساخت `~/job` و استخراج بسته
- ایجاد مسیرهای `storage/*` و `bootstrap/cache`
- ساخت `.env` + `APP_KEY`
- `migrate` / seed / admin
- کپی `public` به `public_html` + `index.php` سازگار با cPanel
- `storage:link` (با fallback)
- `optimize:clear` و cacheهای production (با تحمل خطا روی shared)
- قفل نصب + حذف `install.php`

### ۴) بعد از نصب

1. SSL را در cPanel فعال کنید و `APP_URL=https://...` را تأیید کنید.
2. Cron (حداقل):

```text
* * * * * /usr/local/bin/php /home/USERNAME/job/artisan schedule:run >> /dev/null 2>&1
```

و Worker صف (هر دقیقه یک بار کافی است اگر `queue:work --stop-when-empty` در schedule باشد؛ در غیر این صورت از دستور نمایش‌داده‌شده در صفحه پایان نصب استفاده کنید).

3. ایمیل:

```bash
# از Terminal هاست (اگر CLI دارید) یا بعد از تنظیم SMTP در Admin:
cd ~/job && php artisan mail:test you@example.com --force
```

4. SMS / زرین‌پال / Turnstile را در `.env` یا پنل ادمین پر کنید.

---

## روش جایگزین — دستی (وقتی Composer روی هاست دارید)

### Database

1. cPanel → MySQL Databases → ساخت DB + User + Grant ALL
2. Host معمولاً `localhost` یا `127.0.0.1`

### کد و Document Root

1. سورس را در `~/job` بگذارید
2. `public_html/index.php` را طوری بنویسید که `~/job/vendor/autoload.php` و `~/job/bootstrap/app.php` را load کند (نسخهٔ Installer همین کار را می‌کند)
3. محتوای `job/public/*` را به `public_html` کپی کنید (به‌جز بازنویسی ناخواستهٔ installer)

### `.env`

از `.env.production.example` کپی کنید:

```bash
cd ~/job
cp .env.production.example .env
php artisan key:generate
```

حداقل‌ها:

| متغیر | مقدار پیشنهادی shared |
|--------|------------------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain` |
| `DB_*` | مقادیر cPanel |
| `QUEUE_CONNECTION` | `database` |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `database` |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `mail.your-domain.ir` (نه smtp.example.com) |
| `MAIL_PORT` | `587` یا `465` |
| `MAIL_SCHEME` | خالی / `null` برای 587؛ `smtps` برای 465 |
| `SMS_ALLOW_LOG_FALLBACK` | `false` |

Laravel 12 از `MAIL_SCHEME` استفاده می‌کند (نه `MAIL_ENCRYPTION=tls` قدیمی).

### Composer / Build

روی **ماشین توسعه** (ترجیحی):

```bash
composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
npm ci && npm run build
```

`laravel/horizon` به `pcntl/posix` نیاز دارد؛ روی shared از **database queue** استفاده کنید و Horizon را اجرا نکنید. فلگ‌های `--ignore-platform-req` فقط برای بسته‌بندی لازم‌اند.

### Migration و Storage

```bash
php artisan migrate --force
php artisan db:seed --force   # در صورت نیاز
php artisan storage:link      # اگر symlink ممکن نبود، Installer fallback دارد
```

مجوزها (نمونه؛ از `0777` پرهیز کنید):

```bash
chmod -R ug+rwx storage bootstrap/cache
```

### Cache بعد از نصب

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

اگر `route:cache` روی هاست خطا داد، بدون آن هم سایت کار می‌کند؛ خطا را در لاگ ببینید و ادامه دهید.

### Queue و Cron

همان بخش Cron در روش Installer.

تست ایمیل:

```bash
php artisan mail:test admin@your-domain.ir --force
```

رمز SMTP چاپ نمی‌شود.

---

## چک‌لیست سریع قبل از Go-Live

- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` پر شده
- [ ] `public/build/manifest.json` موجود است
- [ ] `storage/installed` یا `storage/app/installed.lock` وجود دارد
- [ ] `install.php` حذف شده
- [ ] SSL + `APP_URL` با https
- [ ] SMTP واقعی (نه host خالی / example)
- [ ] SMS credentials + `SMS_ALLOW_LOG_FALLBACK=false`
- [ ] زرین‌پال sandbox خاموش در production
- [ ] Cron schedule + queue

---

## عیب‌یابی متداول

| علامت | اقدام |
|--------|--------|
| صفحه سفید / 500 | `~/job/storage/logs/laravel.log` |
| `Please provide a valid cache path` | پوشه‌های `storage/framework/{cache,views,sessions}` را بسازید |
| `routes/install.php` missing | بسته را دوباره با `build-cpanel-package.php` بسازید |
| ایمیل ارسال نمی‌شود | `mail:test` + بررسی پورت 587/465 و احراز هویت cPanel |
| صف گیر کرده | Cron / `queue:work` و جداول `jobs` |

---

## ساخت مجدد بسته Production

```bash
bash scripts/pre-deploy-check.sh   # باید RESULT: PASS
php scripts/build-cpanel-package.php
```

**توجه:** نصب end-to-end روی cPanel واقعی باید جداگانه روی هاست تأیید شود؛ این مخزن Installer + تست‌های واحد/دود را پوشش می‌دهد.
