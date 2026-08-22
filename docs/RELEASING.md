# راهنمای انتشار (Semantic Versioning)

این سند دستورات تگ‌گذاری، قالب Release Note و نحوه کار GitHub Action انتشار را توضیح می‌دهد.

## ۱. تگ‌گذاری نسخه (روی `main`)

قبل از تگ، مطمئن شوید `main` به‌روز است و بخش مربوط به نسخه در `CHANGELOG.md` آماده است (مثلاً `[1.4.0]`).

```bash
# اطمینان از بودن روی main و به‌روز بودن
git checkout main
git pull origin main

# مشاهده آخرین تگ‌ها (اختیاری)
git tag -l "v*" --sort=-v:refname | head -n 10

# ساخت تگ annotated برای v1.4.0
git tag -a v1.4.0 -m "انتشار JobAzmoon v1.4.0 — PWA کامل با حالت آفلاین"

# ارسال تگ به GitHub (trigger ورک‌فلو Release)
git push origin v1.4.0
```

### نکات

- از تگ **annotated** (`-a`) استفاده کنید، نه تگ سبک.
- پیش‌نمایش / RC: `v1.5.0-rc.1` (ورک‌فلو آن را prerelease علامت می‌زند).
- اگر تگ را اشتباه زدید و هنوز Release ساخته نشده:

```bash
git tag -d v1.4.0
git push origin :refs/tags/v1.4.0
```

سپس دوباره تگ صحیح بسازید.

### نسخه‌های بعدی (مثال)

```bash
git tag -a v1.4.1 -m "انتشار JobAzmoon v1.4.1 — رفع باگ‌های پایدارسازی"
git push origin v1.4.1

git tag -a v1.5.0 -m "انتشار JobAzmoon v1.5.0"
git push origin v1.5.0
```

---

## ۲. قالب Release Note

کپی آماده برای GitHub Releases (دستی یا پس از پر شدن خودکار از `CHANGELOG.md`):

فایل مرجع: [`.github/RELEASE_TEMPLATE.md`](../.github/RELEASE_TEMPLATE.md)

---

## ۳. GitHub Action خودکار

با push شدن تگ مطابق `v*.*.*` ورک‌فلو [`.github/workflows/release.yml`](../.github/workflows/release.yml):

1. تست‌های backend و frontend را اجرا می‌کند  
2. بخش همان نسخه را از `CHANGELOG.md` استخراج می‌کند  
3. با قالب فارسی Release می‌سازد  

نیازی به ساخت دستی Release نیست مگر بخواهید متن را ویرایش کنید.
