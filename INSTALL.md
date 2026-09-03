# نصب JobAzmoon روی cPanel (مشابه وردپرس)

هدف: فقط پوشه **JobAzmoon-Installer** را روی هاست آپلود کنید، `install.php` را باز کنید، و بدون Composer / npm / SSH پیچیده نصب را تمام کنید.

---

## 1) آماده‌سازی بسته روی سیستم توسعه (یا CI)

از ریشه پروژه:

```bash
php scripts/build-cpanel-package.php
```

اگر `vendor` و `public/build` از قبل آماده‌اند:

```bash
php scripts/build-cpanel-package.php --skip-deps
```

خروجی:

```text
dist/jobazmoon-core.zip
dist/JobAzmoon-Installer/
  ├── install.php
  ├── lib/InstallEngine.php
  ├── package/jobazmoon-core.zip
  ├── INSTALL.md
  └── README-INSTALL.txt
dist/JobAzmoon-Installer.zip
```

بسته هسته شامل `vendor/` و `public/build/` است. فایل `.env` واقعی، رمزها، یا credential داخل ZIP قرار **نمی‌گیرد**.

---

## 2) چه چیزی را روی هاست آپلود کنیم؟

یکی از این دو:

**الف)** محتویات `dist/JobAzmoon-Installer/` را داخل `public_html` بریزید  
**ب)** `dist/JobAzmoon-Installer.zip` را مستقیم داخل `public_html` Extract کنید (فایل‌ها در **ریشه** ZIP هستند، نه داخل پوشه تو در تو)

ساختار نهایی روی هاست **قبل از نصب** باید این باشد:

```text
public_html/
├── install.php
├── lib/
│   └── InstallEngine.php
└── package/
    └── jobazmoon-core.zip
```

اگر بعد از Extract مسیر `public_html/JobAzmoon-Installer/install.php` دیدید، یعنی ZIP قدیمی بوده — فایل‌ها را یک سطح بالا به `public_html` منتقل کنید تا `public_html/install.php` شود.

---

## 3) محل آپلود در cPanel

1. وارد **File Manager** شوید.
2. به پوشه `public_html` (Document Root دامنه) بروید.
3. فایل‌های نصب‌کننده را آپلود کنید (ساختار بالا).
4. در **MySQL Databases** یک دیتابیس و یک کاربر بسازید و کاربر را به دیتابیس وصل کنید.
5. در **Select PHP Version** نسخه **۸.۲ یا ۸.۳** را انتخاب کنید و افزونه‌های زیر را فعال کنید:

   `pdo`, `pdo_mysql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `dom`, `ctype`, `json`, `fileinfo`, `gd`, `zip`  
   (پیشنهادی: `curl`, `intl`)

Document Root را روی پوشه `job` نگذارید. همان `public_html` بماند.

---

## 4) URL نصب

```text
https://YOUR-DOMAIN/install.php
```

---

## 5) اطلاعات لازم هنگام نصب (Wizard)

### مرحله ۱ — بررسی سیستم

PHP (نسخه از `composer.json` بسته)، افزونه‌ها، وجود `jobazmoon-core.zip`، وجود frontend build (`manifest.json`)، فضای دیسک، نوشتنی بودن مسیرها.

اگر مورد قرمز (غیر هشدار) باشد، Continue غیرفعال است.

### مرحله ۲ — پایگاه‌داده

- Host (معمولاً `localhost` یا `127.0.0.1`)
- Port (معمولاً `3306`)
- Database Name
- Username
- Password

دکمه **Test Database Connection** را بزنید. تا اتصال موفق نشود ادامه ممکن نیست.  
اگر دیتابیس از قبل جدول دارد، باید صریحاً تأیید کنید (Installer هرگز `DROP DATABASE` / `migrate:fresh` نمی‌زند).

### مرحله ۳ — سایت و مدیر

- Site Name
- Site URL (`https://...`)
- Admin Name / Email / Mobile
- Admin Password + Confirm

### مرحله ۴ — تأیید و نصب

خلاصه بدون نمایش رمز/کلید. پس از تأیید:

1. استخراج امن ZIP به `~/job`
2. کپی دارایی‌های `public` به `public_html`
3. بازنویسی `index.php` برای boot کردن `../job`
4. ساخت `.env` production (`APP_DEBUG=false`, queue/cache/session=database)
5. تولید `APP_KEY`
6. `migrate --force`
7. seed امن production (بدون داده آزمایشی مخرب)
8. `storage:link`
9. ایجاد کاربر Admin
10. `config:cache` / `view:cache` / `route:cache` / `event:cache` (در حد امکان)
11. قفل `storage/installed`
12. حذف `install.php`، `InstallEngine.php`، `package/`

### مرحله ۵ — پایان

گزارش Verification، Cronهای Scheduler و Queue، لینک ورود به سایت.

---

## 6) بعد از نصب چه کار کنید؟

1. اگر `install.php` هنوز هست، فوراً از File Manager حذف کنید.
2. SSL را در cPanel فعال کنید.
3. دو Cron از صفحه پایان را در **Cron Jobs** (هر دقیقه) اضافه کنید:

   - `schedule:run`
   - `queue:work database --stop-when-empty --max-time=50 --tries=3` (با `flock` در صورت وجود)

4. تنظیمات Mail / SMS / درگاه پرداخت را در پنل ادمین یا `.env` داخل `~/job` تکمیل کنید.
5. به‌روزرسانی بعدی از **Update Pack** ادمین است — دوباره `install.php` را اجرا نکنید.

جزئیات بیشتر: [`docs/CPANEL_DEPLOYMENT.md`](docs/CPANEL_DEPLOYMENT.md) و [`docs/UPDATE_SYSTEM.md`](docs/UPDATE_SYSTEM.md)

ساختار بعد از نصب:

```text
/home/USER/
├── job/                 ← Laravel + vendor + .env
└── public_html/         ← وب (index.php → ../job)
```

---

## 7) در صورت خطا، لاگ از کجا؟

| محل | مسیر |
|---|---|
| لاگ Laravel | `~/job/storage/logs/laravel.log` |
| لاگ نصب‌کننده (در صورت نوشتن) | `~/job/storage/logs/installer.log` |
| خطای صفحه نصب | پیام فارسی بدون رمز/APP_KEY |
| PHP error در cPanel | Errors / MultiPHP INI / error_log دامنه |

اگر نصب نیمه‌کاره ماند (`incomplete` / `corrupted`):

1. از `~/job` و دیتابیس بکاپ بگیرید.
2. فقط در صورت اطمینان، پوشه `~/job` و جداول ناقص را پاک کنید.
3. دوباره `install.php` + بسته را آپلود و نصب را از نو اجرا کنید.

Installer در حالت قفل‌شده (`storage/installed` + `.env`) اجازه نصب مجدد نمی‌دهد.

---

## امنیت

- CSRF روی همه فرم‌ها
- ZIP extraction با جلوگیری از path traversal / symlink / ZIP bomb
- Artisan فقط از طریق Kernel داخلی PHP (بدون `shell_exec`)
- رمز دیتابیس، رمز ادمین، و `APP_KEY` در UI نمایش داده نمی‌شوند
- `.env` داخل `~/job` است (خارج از Document Root)
- `.htaccess` فایل‌های حساس را Deny می‌کند
- حذف خودکار Installer بعد از موفقیت (و Deny در صورت شکست حذف)

---

## روش جایگزین — Laravel `/install` روی VPS

اگر Document Root = `public/` پروژه کامل است (نه بسته‌بندی cPanel):

`https://YOUR-DOMAIN/install`

این مسیر جدا از `install.php` بسته‌بندی‌شده است.

---

## تست محلی

```bash
php artisan test --filter=Install
php cpanel-installer/test-install-cli.php
php scripts/build-cpanel-package.php --skip-deps
```
