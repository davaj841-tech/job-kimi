# چک‌لیست تست موبایل و تم شب/روز — JobAzmoon

این سند برای تست دستی پس از هر تغییر UI موبایل یا Dark Mode استفاده شود.

## دستگاه‌ها

- [ ] iPhone SE (۳۷۵px) — بدون اسکرول افقی
- [ ] iPhone 14 Pro Max (۴۳۰px)
- [ ] Android Chrome (۳۶۰–۴۱۲px)
- [ ] تبلت (۷۶۸px)

## صفحات عمومی

- [ ] صفحه اصلی: Hero خوانا، بنر موبایل مخفی، CTA تمام‌عرض
- [ ] آزمون‌ها: فیلتر/جستجو ثابت، کارت‌ها تک‌ستونه
- [ ] جزئیات آزمون: دکمه «شروع آزمون» sticky پایین (موبایل)
- [ ] آگهی‌ها: نوار جستجو sticky
- [ ] فروشگاه: «همه» و جستجو sticky
- [ ] فوتر موبایل: آکاردئون لینک‌ها

## پنل کاربری

- [ ] Bottom Nav: ۵ آیتم، safe-area، touch ۴۴×۴۴
- [ ] داشبورد: KPI دو ستونه در موبایل
- [ ] آزمون آنلاین: گزینه‌ها min-height ۴۸px، تایمر خوانا
- [ ] کیف پول: input شارژ با کیبورد عددی
- [ ] پروفایل: آیکون بستن منو (X) هنگام باز بودن sidebar
- [ ] رزومه: قالب بسته در موبایل، پیش‌نمایش A4

## تم شب/روز

- [ ] اولین بازدید: رعایت system preference
- [ ] toggle در header کار می‌کند
- [ ] preference در `localStorage` (`ja_theme`) ذخیره می‌شود
- [ ] transition نرم (~۳۰۰ms)
- [ ] کنtrast متن/پس‌زمینه در حالت تاریک (WCAG AA)
- [ ] date picker در هر دو حالت خوانا

## PWA و عملکرد

- [ ] Service Worker پس از deploy فایل جدید را می‌گیرد
- [ ] Hard refresh / Clear site data در صورت کش قدیمی
- [ ] تصاویر lazy load
- [ ] نصب PWA روی iOS/Android

## استقرار

پس از هر تغییر frontend:

```bash
npm run build
```

برای cPanel:

```bash
php scripts/build-cpanel-package.php
```

فایل‌های ضروری روی سرور:
- `public/build/` (کامل)
- `resources/views/spa.blade.php`
