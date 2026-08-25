# نصب برنامه (مشابه وردپرس)

## روش A — cPanel (public_html + install.php)

پس از ساخت بسته روی سیستم توسعه:

```bash
php scripts/build-cpanel-package.php
```

آپلود در `public_html`:

- `cpanel-installer/install.php`
- پوشه `lib/` (از `cpanel-installer/lib/`)
- `package/jobazmoon-core.zip` (از `dist/jobazmoon-core.zip`)

سپس: `https://your-domain.example/install.php`

مراحل نصب‌کننده:

1. **پیش‌نیاز:** PHP 8.2+، افزونه‌ها، manifest.json در zip
2. **پایگاه‌داده:** تست اتصال MySQL — اگر جدول دارد نیاز به تأیید صریح
3. **سایت و مدیر:** نام سایت، URL، ایمیل، موبایل، رمز (در گزارش نمایش داده نمی‌شود)
4. **تأیید:** خلاصه بدون secret + checkbox تأیید
5. **پایان:** migrate، seed، storage:link، cache، قفل `storage/installed`، حذف install.php و بسته؛ نمایش Cronهای schedule + queue

جزئیات مسیرها و GitHub: [`docs/CPANEL_DEPLOYMENT.md`](docs/CPANEL_DEPLOYMENT.md)  
به‌روزرسانی بعدی: [`docs/UPDATE_SYSTEM.md`](docs/UPDATE_SYSTEM.md) — **نه** install.php دوباره.

## روش B — Laravel wizard (/install)

اگر کل پروژه Laravel روی VPS است و Document Root = `public/`:

`https://your-domain.example/install`

مراحل: پیش‌نیاز → پایگاه‌داده → migrate/seed → مدیر → پایان

---

## قفل نصب

اگر فایل `storage/installed` وجود نداشته باشد، **همهٔ درخواست‌ها** (به‌جز `/up`، `/health` و `/api/v1/health`) به `/install` هدایت می‌شوند.  
اگر آن فایل وجود داشته باشد، مسیر `/install` به `/` برمی‌گردد.

## امنیت

- پیشرفت نصب در سشن نگه داشته می‌شود؛ هر مرحله فقط بعد از مرحلهٔ قبل باز است.
- همهٔ فرم‌ها CSRF دارند.
- رمز دیتابیس، APP_KEY و رمز مدیر در صفحه پایان نمایش داده **نمی‌شوند**.
- تغییر/نصب روی پایگاه دارای جدول بدون checkbox تأیید ممکن نیست.
- در صورت خطا، rollback migration و پاکسازی `.env` / پوشه job (در cPanel installer).

## تست نصب

```bash
php artisan test --filter=Install
php cpanel-installer/test-install-cli.php
```

## فایل محیط

اگر `.env` نباشد از `.env.example` کپی می‌شود. در صورت خالی بودن `APP_KEY` یک کلید ساخته می‌شود.

## سایت از قبل نصب‌شده

اگر برنامه هم‌اکنون روی سرور کار می‌کند و این به‌روزرسانی را می‌گیرید، **قبل از باز کردن سایت** این فایل را بسازید تا ویزارد دوباره اجرا نشود:

```bash
# Linux
touch storage/installed

# Windows (PowerShell)
New-Item -ItemType File -Path storage/installed -Force
```

`storage/installed` را در گیت commit نکنید تا نصب تازه روی هاست جدید دوباره از `/install` شروع شود.
