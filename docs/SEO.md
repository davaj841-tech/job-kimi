# سیستم SEO جاب‌آزمون

## معماری

سیستم SEO به‌صورت ماژولار و Polymorphic طراحی شده و به هر محتوایی قابل اتصال است.

### جداول

| جدول | هدف |
|------|-----|
| `seo_meta` | Title, Description, OG, Twitter Card |
| `seo_keywords` | Focus Keyword + Related Keywords |
| `seo_analyses` | امتیاز SEO (0-100) + نتایج بررسی |
| `seo_suggestions` | پیشنهادات بهبود |
| `seo_links` | لینک‌های داخلی/خارجی + بررسی خرابی |
| `seo_redirects` | ریدایرکت‌های 301/302/410 |
| `seo_faqs` | سوالات متداول (برای Schema FAQPage) |
| `seo_audits` | نتایج آدیت‌های دوره‌ای |
| `seo_settings` | تنظیمات اضافه SEO |

### مدل‌های قابل SEO

- `Exam` — آزمون
- `JobPost` — آگهی شغلی
- `BlogPost` — مطلب وبلاگ
- `GeneratedContent` — مقاله تولیدی
- `CmsPage` — صفحات ایستا
- `PdfProduct` — فایل PDF
- `ExamCategory` — دسته آزمون
- `Question` — سوال

همه با Trait `HasSeo` به سیستم متصل هستند.

## سرویس‌ها

| سرویس | مسئولیت |
|--------|---------|
| `SeoManager` | هماهنگ‌کننده مرکزی |
| `SeoAnalyzer` | تحلیل و امتیازدهی |
| `MetaGenerator` | تولید Meta Tags |
| `SchemaGenerator` | JSON-LD Schema.org |
| `SitemapService` | Sitemap XML (با Cache) |
| `CanonicalService` | آدرس Canonical |
| `RedirectService` | مدیریت Redirectها |
| `BrokenLinkService` | بررسی لینک‌های خراب |
| `DuplicateContentService` | شناسایی محتوای تکراری |
| `CannibalizationService` | شناسایی Keyword Cannibalization |

## تنظیمات

فایل: `config/seo.php`

```php
'score_weights' => [...],      // وزن هر بررسی
'duplicate_threshold' => 70,    // آستانه شباهت %
'cannibalization_threshold' => 2,
'keyword_density' => ['min' => 0.5, 'max' => 3.0],
'content_length' => ['min' => 300, 'ideal' => 800],
'sitemap.cache_ttl' => 3600,
```

## Sitemap

| آدرس | محتوا |
|------|-------|
| `/sitemap.xml` | Sitemap Index |
| `/sitemaps/pages.xml` | صفحات CMS |
| `/sitemaps/jobs.xml` | آگهی‌ها |
| `/sitemaps/exams.xml` | آزمون‌ها |
| `/sitemaps/articles.xml` | مقالات |
| `/sitemaps/blog.xml` | وبلاگ |
| `/sitemaps/files.xml` | فایل‌ها |

Sitemap با Cache ذخیره می‌شود و با تغییر محتوا پاک‌سازی لازم است.

## Schema JSON-LD

انواع تولیدشده:
- `WebSite`
- `JobPosting`
- `Article`
- `Quiz`
- `WebPage`
- `FAQPage`
- `BreadcrumbList`
- `Organization`

## Scheduler

| Job | زمان‌بندی |
|-----|-----------|
| `CheckBrokenLinksJob` | روزانه ساعت 03:00 |
| `RunSeoAuditJob` | هفتگی (دوشنبه 04:00) |

## Filament

بخش SEO شامل:
- SEO Dashboard Widget (میانگین امتیاز + توزیع + Cannibalization)
- تحلیل‌های SEO (لیست امتیازات)
- کلمات کلیدی
- ریدایرکت‌ها (CRUD کامل)

## نحوه استفاده

### تحلیل یک محتوا
```php
$manager = app(SeoManager::class);
$analysis = $manager->analyze($exam);
```

### گرفتن Meta
```php
$meta = $manager->getMeta($exam);
```

### گرفتن Schema
```php
$schemas = $manager->getSchema($exam);
```

### ایجاد Redirect
```php
app(RedirectService::class)->create('/old', '/new', 301);
```

## Performance

- SEO Analyzer فقط با Job/Scheduler اجرا می‌شود (نه در هر Request).
- Sitemap با Cache ذخیره می‌شود.
- Broken Link Check به‌صورت Queue اجرا می‌شود.
- از N+1 Query با `with()` جلوگیری شده.
