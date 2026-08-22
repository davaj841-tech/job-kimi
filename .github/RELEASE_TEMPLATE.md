# JobAzmoon vX.Y.Z

> تاریخ انتشار: YYYY-MM-DD (شمسی: ۱۴۰۴-…)

## چه چیزهای جدیدی هست؟ (What's New)

-
-
-

## تغییرات شکسته (Breaking Changes)

- ندارد
<!-- اگر دارید، مسیر API / قرارداد پاسخ / migration اجباری را بنویسید -->

## راهنمای مهاجرت (Migration Guide)

1. کد را به تگ این نسخه به‌روز کنید: `git fetch --tags && git checkout vX.Y.Z`
2. وابستگی‌ها را نصب کنید:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

3. تنظیمات و دیتابیس:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

4. در صورت تغییر Service Worker / PWA، یک‌بار hard refresh یا به‌روزرسانی SW را به کاربران یادآوری کنید.
5. اگر Feature Flag یا تنظیم درگاه عوض شده، مقدار `.env` و پنل ادمین را بررسی کنید.

## مشارکت‌کنندگان (Contributors)

از همه کسانی که در این نسخه مشارکت داشتند سپاسگزاریم:

- @username
-
