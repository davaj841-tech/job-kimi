# استقرار روی cPanel (shared hosting)

GitHub منبع اصلی کد است. روی سرور **Composer / Node / SSH لازم نیست** — بستهٔ آماده (`jobazmoon-core.zip`) روی سیستم توسعه یا CI ساخته می‌شود.

## چیدمان مسیر

```text
/home/USER/
  job/                 ← Laravel (vendor، .env، artisan، …)
  public_html/         ← فقط فایل‌های public + index.php بازنویسی‌شده
```

`.env` فقط در `/home/USER/job/.env` است و از وب قابل دسترس نیست.

---

## جریان توسعه → پروداکشن

```text
Development (Cursor)
  → Tests (php artisan test / npm run test:unit)
  → git commit (Conventional Commits)
  → git push → GitHub (source of truth)
  → Build package (scripts/build-cpanel-package.*)
  → Upload / Git Version Control → cPanel
  → Installer (فقط بار اول) یا Update Pack
```

---

## روش A — cPanel Git Version Control (ترجیحی اگر هاست اجازه دهد)

1. در cPanel → **Git Version Control** ریپوی GitHub را clone کنید (معمولاً خارج از `public_html`، مثلاً `~/repositories/job-kimi`).
2. این روش برای **همگام‌سازی سورس** است؛ روی shared معمولاً هنوز نمی‌توانید `composer install` / `npm run build` بزنید.
3. بنابراین همچنان از **GitHub Release asset** یا ساخت لوکال، فایل `jobazmoon-core.zip` را بگیرید.
4. نصب اولیه: آپلود installer + ZIP طبق `INSTALL.md`.
5. به‌روزرسانی‌های بعدی: **Update Pack** از Admin (نه `install.php`) — جزئیات `docs/UPDATE_SYSTEM.md`.

اگر هاست PHP CLI و Composer داشته باشد، می‌توانید بعد از `git pull` روی سرور بیلد کنید؛ این سناریو شبیه VPS است و خارج از هدف shared بدون Composer است.

---

## روش B — بدون Git روی cPanel (آپلود دستی)

### نصب اولیه

1. روی سیستم توسعه / CI:

```powershell
.\scripts\build-cpanel-package.ps1
# یا: bash scripts/build-cpanel-package.sh
# یا: php scripts/build-cpanel-package.php
```

خروجی: `dist/jobazmoon-core.zip`

2. در File Manager داخل `public_html` آپلود کنید:
   - `cpanel-installer/install.php`
   - `cpanel-installer/lib/InstallEngine.php` → پوشه `lib/`
   - `dist/jobazmoon-core.zip` → `package/jobazmoon-core.zip`

3. باز کنید: `https://your-domain/install.php` و ویزارد را تا پایان بروید.

4. Installer:
   - هسته را در `~/job` می‌ریزد
   - `public` را به `public_html` کپی می‌کند
   - `index.php` را به `~/job` وصل می‌کند
   - `.env` با `QUEUE/CACHE/SESSION=database` می‌سازد
   - migrate / seed / cache
   - `install.php` و بسته را حذف می‌کند

### به‌روزرسانی (بدون install.php)

```text
GitHub Release / update:build
  → jobazmoon-update-vX.Y.Z.zip
  → Admin → سیستم → به‌روزرسانی
  → Backup → Maintenance → File update → Migration → Verify → Cache → Health
  → Success یا Rollback
```

هرگز برای آپدیت دوباره `install.php` را اجرا نکنید.

---

## Cron (بدون Horizon)

در cPanel → Cron Jobs (مسیر PHP را از Select PHP Version بردارید):

```text
* * * * * php /home/USER/job/artisan schedule:run >> /dev/null 2>&1
* * * * * php /home/USER/job/artisan queue:work database --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

Horizon / Redis برای shared **الزامی نیستند**.

---

## امنیت

| مورد | وضعیت هدف |
|------|-----------|
| Document root = فقط public | بله (`public_html`) |
| `.env` خارج از وب | `~/job/.env` |
| `vendor` / `artisan` خارج از وب | داخل `~/job` |
| حذف `install.php` بعد از نصب | خودکار (+ هشدار) |
| حذف ZIP بسته | خودکار |
| آپدیت فقط از Admin / Update Pack | بله |

---

## ساخت بسته — پیش‌نیاز لوکال

```bash
composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
npm ci
npm run build
php scripts/build-cpanel-package.php --skip-deps
```

یا یک‌جا: `php scripts/build-cpanel-package.php` (deps را خودش اجرا می‌کند؛ روی ویندوز `ext-pcntl` را ignore می‌کند و در پایان Composer را به حالت dev برمی‌گرداند).
