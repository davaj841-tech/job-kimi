# استقرار جاب‌آزمون روی هاست

## مهم قبل از هر چیز

1. **Document Root باید پوشه `public/` باشد** (نه ریشه پروژه). اگر ریشه را باز کنید، `.env` و `vendor` لو می‌روند.
2. PHP **۸.۲ یا ۸.۳**، MySQL/MariaDB، دسترسی SSH ترجیحاً.
3. روی هاست بسازید: `npm ci && npm run build` (یا بیلد لوکال را آپلود کنید چون `public/build` در گیت نیست).
4. بعد از آپلود: `php artisan storage:link` — بدون آن لوگو/پیوست‌ها 404 می‌شوند.
5. برای آپلود APK در تنظیمات: `upload_max_filesize` و `post_max_size` حداقل `64M`؛ در Nginx هم `client_max_body_size 64M`.

---

## پروفایل ۱ — VPS لینوکس (پیشنهادی)

فایل: کپی `.env.production.example` → `.env`

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate   # فقط بار اول
php artisan migrate --force
php artisan storage:link --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- Cron: `* * * * * cd /path/to/app && php artisan schedule:run`
- صف: Redis + Horizon (`deploy/horizon.service`) یا `queue:work`
- جزئیات بیشتر: `deploy/README.md` و `./deploy.sh production`

### پروکسی / HTTPS

اگر Nginx روی همان سرور SSL را تمام می‌کند:

```
TRUSTED_PROXIES=127.0.0.1,::1
```

اگر Cloudflare مستقیم به PHP می‌زند، CIDRهای Cloudflare را بگذارید، یا فقط پشت Nginx داخلی:

```
TRUSTED_PROXIES=*
```

(`*` فقط وقتی امن است که PHP فقط از پروکسی قابل اعتماد در دسترس باشد.)

---

## پروفایل ۲ — هاست اشتراکی cPanel (بدون Redis / بدون Composer روی سرور)

مستند کامل: **[CPANEL_DEPLOYMENT.md](./CPANEL_DEPLOYMENT.md)**

در `.env` (installer همین را می‌نویسد):

```
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SMS_ALLOW_LOG_FALLBACK=false
TELESCOPE_ENABLED=false
ZARINPAL_SANDBOX=false
```

- بسته را لوکال/CI بسازید: `php scripts/build-cpanel-package.php` → `dist/jobazmoon-core.zip`
- نصب اولیه: `install.php` (یک‌بار) — بعد حذف می‌شود
- آپدیت: Update Pack از پنل Admin — نه `install.php`
- Cron: `schedule:run` + `queue:work database --stop-when-empty` (بدون Horizon)

### GitHub → cPanel

| روش | توضیح |
|-----|--------|
| **A (ترجیحی)** | cPanel Git Version Control برای sync سورس + ZIP آماده برای runtime |
| **B** | آپلود دستی installer + `jobazmoon-core.zip` |

جزئیات هر دو روش در `CPANEL_DEPLOYMENT.md`.

---

## چک‌لیست راه‌اندازی

| مورد | مقدار |
|------|--------|
| `APP_URL` | دامنه واقعی با https |
| `APP_KEY` | تولیدشده |
| `APP_DEBUG` | `false` |
| دیتابیس | migrate شده |
| `storage` + `bootstrap/cache` | قابل نوشتن (775) |
| `public/storage` | لینک فعال |
| `public/build` | بیلد Vite موجود |
| SMS | `KAVENEGAR_API_KEY` یا تنظیمات پنل |
| زرین‌پال | Merchant + sandbox خاموش |
| Turnstile | `.env` یا تنظیمات امنیت پنل |
| Sanctum | `SANCTUM_STATEFUL_DOMAINS=دامنه,www.دامنه` |
| رمز ادمین | بعد از seed عوض شود (`ADMIN_SEED_PASSWORD` موقع seed) |

بررسی سلامت: `https://your-domain/health` و `https://your-domain/up`

پنل Vue: `/admin` — Filament: `/filament`

---

## چیزهایی که نباید روی سرور بروند

- `.env` لوکال با `APP_DEBUG=true`
- اجرای `scripts/live-db-prepare.sh` روی دیتابیس واقعی
- اسکریپت‌های ریست پسورد بدون `APP_ENV=local`
