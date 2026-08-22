## خلاصه
<!-- چه چیزی و چرا تغییر کرده است؟ -->

## نوع تغییر
- [ ] `feature` — قابلیت جدید
- [ ] `fix` — رفع باگ
- [ ] `refactor` — بازنویسی بدون تغییر رفتار
- [ ] `docs` — مستندات
- [ ] سایر: <!-- مثلاً chore / test / security / perf -->

## تغییرات اصلی
-
-

## Related issues
<!-- مثال: Closes #123 — Relates to #45 -->
-

## Breaking changes
- [ ] خیر
- [ ] بله — توضیح دهید (مسیر API، قرارداد پاسخ، migration اجباری و …):

<!--
توضیح breaking:
-
-->

## چک‌لیست تست و lint
- [ ] `php artisan test` سبز است
- [ ] `npm run test:unit` سبز است
- [ ] `./vendor/bin/phpstan analyse --memory-limit=1G` بدون خطا
- [ ] `./vendor/bin/pint --test` بدون خطا
- [ ] در صورت تغییر فرانت: `npm run lint` (و در صورت نیاز `npm run type-check`) پاس شده
- [ ] در صورت تغییر API: `php artisan scribe:generate` اجرا و خروجی commit شده
- [ ] در صورت نیاز: بخش `[Unreleased]` در `CHANGELOG.md` به‌روز شده

## امنیت / پرداخت (در صورت مرتبط)
- [ ] توکن، کلید `.env` یا داده حساس commit نشده
- [ ] جریان پرداخت / ایدمپوتنسی بررسی شده
- [ ] CSP / احراز هویت / دسترسی نقش‌ها در نظر گرفته شده

## Screenshots
<!-- اگر UI تغییر کرده، قبل/بعد را اینجا بگذارید -->

| قبل | بعد |
|-----|-----|
|     |     |

## نکات بازبینی
<!-- موارد مهم برای reviewer -->
-
