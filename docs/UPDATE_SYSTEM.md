# سیستم به‌روزرسانی JobAzmoon

## خلاصه

پس از نصب اولیه روی cPanel، نسخه‌های بعدی با آپلود یک **Update Pack** از پنل مدیریت نصب می‌شوند — بدون آپلود مجدد کل پروژه.

نسخه مرکزی: `config/version.php` + در زمان اجرا `storage/app/updates/CURRENT_VERSION`

فرمت نسخه: Semantic Versioning `MAJOR.MINOR.PATCH`

---

## برای مدیر سایت (بدون دانش Laravel)

1. از توسعه‌دهنده فایل `jobazmoon-update-vX.Y.Z.zip` را بگیرید.
2. وارد `/admin` شوید (فقط **سوپرادمین**).
3. منوی **سیستم → به‌روزرسانی**.
4. ZIP را انتخاب کنید → **بررسی اولیه**.
5. اگر همهٔ تیک‌ها سبز بودند → **نصب به‌روزرسانی**.
6. تا پایان صبر کنید. در صورت خطا سیستم تلاش می‌کند Rollback کند.

اگر سایت موقتاً در حالت تعمیرات رفت، پس از اتمام نصب خودکار خارج می‌شود.

---

## ساخت Update Pack (توسعه‌دهنده)

```bash
# پس از commit و تگ
git tag v1.0.1
php artisan update:build 1.0.1 --from=1.0.0 --description="Bug fixes"

# یا با لیست صریح فایل‌ها
php artisan update:build 1.0.1 --file=app/Services/Foo.php --file=routes/web.php
```

خروجی پیش‌فرض:

`storage/app/updates/dist/jobazmoon-update-v1.0.1.zip`

ساختار ZIP:

```text
manifest.json
checksums.json
files/...
database/migrations/...   (در صورت وجود)
```

---

## دستورات Artisan

| دستور | کار |
|--------|-----|
| `php artisan update:status` | نسخه و Health |
| `php artisan update:validate path.zip` | فقط اعتبارسنجی |
| `php artisan update:install path.zip` | نصب |
| `php artisan update:history` | تاریخچه |
| `php artisan update:rollback {id?}` | Rollback |
| `php artisan update:build 1.0.1` | ساخت بسته |

همه از `UpdateManager` استفاده می‌کنند.

---

## امنیت

- فقط **super_admin** (مسیر `system-updates` مثل backups)
- جلوگیری از ZIP Slip / Path Traversal
- ممنوعیت overwrite برای `.env`, `storage/`, `vendor/`, `node_modules/`, `.git/`, `composer.lock`, …
- بررسی SHA-256
- محدودیت حجم و تعداد فایل
- قفل همزمانی (`update.lock`) با TTL قابل بازیابی
- CSRF روی فرم‌های وب + Sanctum برای API
- Audit log رویدادها

---

## Backup و Rollback

قبل از نصب:

1. بکاپ کامل از طریق `BackupService` موجود پروژه
2. بکاپ ZIP فقط از فایل‌های هدف تغییر/حذف (+ فهرست `new_files` برای حذف در Rollback)
3. استخراج dump پایگاه از بکاپ کامل در صورت مهاجرت

در Failure:

- بازگردانی فایل‌های تغییرکرده از بکاپ فایل
- حذف فایل‌های جدید اضافه‌شده توسط Update (`new_files`)
- تلاش برای بازگردانی DB از بکاپ کامل / SQL استخراج‌شده
- نسخه به `previous_version` برمی‌گردد
- Lock آزاد و Maintenance خاموش می‌شود
- Health Check پس از Rollback اجرا می‌شود

### Database Rollback — صریح

| روش | پشتیبانی |
|-----|----------|
| `php artisan migrate:rollback` برای migrationهای بسته | **خیر** (عمداً استفاده نمی‌شود) |
| Restore از بکاپ کامل `BackupService` | **بله** (مسیر اصلی) |
| Restore فایل `database.sqlite` (محیط تست/SQLite) | **بله** |
| MySQL بدون بکاپ کامل معتبر | **NOT AVAILABLE / PARTIAL** |

اگر `rollback_complete=false` باشد، Rollback دیتابیس کامل نیست و باید از `storage/backups` + `BACKUP-RESTORE.md` دستی بررسی شود.

### Compatibility Policy نسخه

- `1.0.0 → 1.0.1` مجاز (نسخه جدیدتر + `minimum_version`)
- `1.0.1 → 1.0.0` مسدود
- `1.0.1 → 1.0.1` مسدود
- پرش Major (`1.0.0 → 2.0.0`) فقط با `release_type=major` و رعایت `minimum_version`

---

## GitHub Workflow پیشنهادی

```text
develop → PR → tests → merge main
     → git tag v1.0.1
     → php artisan update:build 1.0.1
     → GitHub Release + attach ZIP
     → Admin uploads ZIP on production
```

Update Pack نباید شامل `.git` / `.env` / `node_modules` باشد. `vendor` فقط با `composer_required` (که نصب cPanel را متوقف می‌کند مگر SSH).

---

## cPanel

نصب اولیه: طبق `INSTALL.md` / cPanel installer.

به‌روزرسانی: فقط Admin UI — SSH لازم نیست مگر بسته به Composer نیاز داشته باشد (در آن صورت نصب متوقف و اعلام می‌شود).

---

## عیب‌یابی

| مشکل | کار |
|------|-----|
| قفل گیر کرده | حذف `storage/app/updates/update.lock` پس از اطمینان از نبودن نصب فعال (یا صبر تا TTL) |
| نسخه اشتباه | محتوای `storage/app/updates/CURRENT_VERSION` و `config/version.php` |
| Health fail | `/health` و لاگ `system_updates` |
| Rollback ناقص | استفاده از بکاپ کامل در `storage/backups` + `BACKUP-RESTORE.md` |

---

## Permission

مسیر API `/api/v1/admin/system-updates*` فقط برای **super_admin** است (هم‌تراز backups). کلید کاتالوگ `system_update` برای مستندسازی است و به اپراتور دسترسی نصب نمی‌دهد.
