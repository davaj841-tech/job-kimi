# JobAzmoon (جاب‌آزمون)

پلتفرم فارسی و RTL برای آمادگی آزمون‌های استخدامی، شامل آزمون آنلاین، آگهی استخدام، فروشگاه PDF، اشتراک، کیف پول و پنل ادمین.

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11-red)](https://laravel.com)
[![Vue](https://img.shields.io/badge/Vue-3-green)](https://vuejs.org)
[![PWA](https://img.shields.io/badge/PWA-enabled-blue)](#)
[![API](https://img.shields.io/badge/API-v1-informational)](#api)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen)](#)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## معرفی

JobAzmoon یک SPA/PWA است با:

| سطح | تکنولوژی |
|------|-----------|
| Frontend کاربر | Vue 3 + Vite + Pinia + Vue Router + TailwindCSS + PWA |
| Admin SPA | Vue 3 جداگانه در مسیر `/admin` |
| Backend | Laravel 11 + Sanctum |
| Admin PHP (اختیاری) | Filament در مسیر `/filament` (نه `/admin`) |

### امکانات کلیدی

- 📝 آزمون آنلاین با autosave، نمره‌دهی و لیدربورد
- 💼 آگهی استخدام + تجمیع منابع رسمی (صف `crawlers`)
- 📄 فروشگاه PDF، اشتراک (Subscription)، کیف پول و درگاه زرین‌پال
- 🤖 رزومه‌ساز با AI Suggest، تیکت، کوپن، وبلاگ، بنر و صفحات ثابت

### اسکرین‌شات

> Placeholder — اسکرین‌شات‌های واقعی را در `docs/screenshots/` قرار دهید.

| صفحه اصلی | آزمون | پنل ادمین |
|-----------|--------|-----------|
| `docs/screenshots/home.png` | `docs/screenshots/exam.png` | `docs/screenshots/admin.png` |

---

## نیازمندی‌ها

- PHP 8.2+ (توصیه: 8.3)
- Composer 2
- Node.js 18+ و npm
- MySQL 8 / MariaDB یا SQLite (لوکال)
- Redis (توصیه برای production: queue / cache / session)
- پسوندهای PHP: intl, gd, mbstring, pdo, tokenizer, xml, curl, zip, bcmath

---

## نصب (لوکال)

```bash
# ۱. کلون
git clone https://github.com/davaj841-tech/job-kimi.git
cd job-kimi

# ۲. وابستگی‌های PHP
composer install

# ۳. محیط
cp .env.example .env
php artisan key:generate

# ۴. دیتابیس (تنظیم DB_* در .env)
php artisan migrate --seed

# ۵. لینک storage (برای آپلود فایل و Spatie MediaLibrary)
php artisan storage:link

# ۶. وابستگی‌های JS
npm install
npm run build
```

### توسعه (hot reload)

```bash
composer run dev
```

این دستور همزمان اجرا می‌کند: Laravel Serve + Queue Worker + Logs + Vite

### کرون (Scheduler)

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### Queue Worker

```bash
php artisan queue:work --queue=crawlers,default --timeout=150
```

### Horizon (با Redis)

```bash
php artisan horizon
```

---

## ساختار پروژه

```
job-kimi/
├── .github/
│   └── workflows/
│       └── ci.yml              # GitHub Actions: Test + Lint + Build
├── app/
│   ├── Actions/                # Action Classes (Payment, Wallet, Exam)
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/            # API Controllers
│   ├── Models/
│   ├── Filament/               # Filament Resources
│   └── ...
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── docs/
│   └── screenshots/            # Placeholder برای اسکرین‌شات
├── lang/                       # فایل‌های ترجمه فارسی
├── public/
├── resources/
│   ├── js/                     # Vue SPA کاربر + PWA
│   │   ├── admin/              # Vue SPA پنل ادمین
│   │   ├── components/
│   │   ├── composables/
│   │   ├── router/
│   │   ├── stores/
│   │   └── views/
│   └── views/
├── routes/
│   ├── api.php                 # Loader
│   ├── api/                    # Split routes
│   │   ├── auth.php
│   │   ├── public.php
│   │   ├── user.php
│   │   ├── exam.php
│   │   ├── payment.php
│   │   └── admin.php
│   ├── web.php
│   └── console.php
├── storage/
├── tests/
│   ├── Feature/
│   └── Unit/
├── deploy.sh
├── composer.json
├── package.json
├── phpstan.neon
├── phpstan-baseline.neon
├── tailwind.config.js
├── vite.config.js
└── README.md
```

---

## تکنولوژی‌ها

| Backend | Frontend |
|---------|----------|
| Laravel 11 | Vue 3.5 + Composition API |
| Sanctum (Auth) | Vite 6 |
| Filament 3 (Admin PHP) | Pinia 2 |
| Horizon (Queue) | Vue Router 4 |
| Spatie Permission & MediaLibrary | TailwindCSS 3 |
| DomPDF | Chart.js |
| Maatwebsite Excel | KaTeX |
| Jalali (Shamsi) | vite-plugin-pwa |
| Predis (Redis) | @vueuse/core |

---

## API

پایه: `/api/v1` (تنظیم‌شده در `bootstrap/app.php`)

### مستندات

- UI: [`/api/documentation`](/api/documentation)
- Spec JSON: [`/api/documentation.json`](/api/documentation.json)

### نمونه درخواست‌ها

ورود با OTP:

```http
POST /api/v1/auth/otp/send
Content-Type: application/json

{
  "mobile": "09123456789"
}
```

لیست آزمون‌ها:

```http
GET /api/v1/exams
```

شروع آزمون (نیاز به login):

```http
POST /api/v1/exams/123/start
Authorization: Bearer {token}
```

> ⚠️ مسیرهای callback زرین‌پال (`/wallet/verify`, `/subscription/verify`) public هستند و نیاز به token ندارند.

مسیرهای route در `routes/api/*.php` تقسیم شده‌اند.

---

## پنل‌های ادمین

| مسیر | نوع | کاربرد |
|------|-----|--------|
| `/admin` | Vue SPA | پنل اصلی عملیاتی (کاربران، آزمون، تجمیع، تنظیمات) |
| `/filament` | Redirect → `/admin` | هدایت به پنل Vue (ادمین عملیاتی) |

متغیر محیطی: `FILAMENT_PATH=filament`

---

## متغیرهای محیطی کلیدی

| متغیر | توضیح | مثال |
|-------|-------|------|
| `APP_NAME` | نام اپلیکیشن | JobAzmoon |
| `APP_URL` | آدرس سایت | https://jobazmoon.ir |
| `DB_DATABASE` | نام دیتابیس | jobazmoon |
| `QUEUE_CONNECTION` | صف (prod: redis) | redis |
| `CACHE_STORE` | کش (prod: redis) | redis |
| `SESSION_DRIVER` | سشن (prod: redis) | redis |
| `REDIS_CLIENT` | درایور Redis | predis |
| `ZARINPAL_MERCHANT_ID` | مرچنت زرین‌پال | xxxxxxxx-xxxx-xxxx |
| `ZARINPAL_SANDBOX` | حالت تست | true / false |
| `KAVENEGAR_API_KEY` | API کاوه‌نگار | xxxxxxxx |
| `OPENAI_API_KEY` | کلید OpenAI | sk-... |
| `TURNSTILE_SITE_KEY` | Cloudflare Turnstile | 0x... |
| `TURNSTILE_SECRET_KEY` | Secret Turnstile | 0x... |
| `FILAMENT_PATH` | مسیر Filament | filament |

> 💡 برای production حتماً `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis` تنظیم شود.

---

## توسعه و کیفیت کد

```bash
# اجرای تست‌ها
php artisan test

# تست فیلترشده
php artisan test --filter=JobAggregator

# Code Style (Laravel Pint)
./vendor/bin/pint

# یا فقط چک بدون تغییر
./vendor/bin/pint --test

# Static Analysis (PHPStan Level 6)
./vendor/bin/phpstan analyse --no-progress

# همه با هم (dev)
composer run dev
```

---

## Deploy

اسکریپت نمونه: `deploy.sh`

```bash
./deploy.sh
```

- Health Check: `/health`
- PWA Manifest: توسط Vite تولید می‌شود
- پس از هر تغییر فرانت: `npm run build`

---

## امنیت

- 🔒 Secretها فقط در `.env` — هرگز commit نشوند
- 🛡️ Turnstile روی مسیرهای auth فعال است
- 🌐 Crawler فقط دامنه‌های تأییدشده را fetch می‌کند (SSRF Guard)
- 🔐 Sanctum برای احراز هویت API
- 👮 Spatie Permission برای کنترل دسترسی (admin/operator/user)

---

## مجوز

این پروژه تحت [MIT License](LICENSE) منتشر شده است.

---

## مشارکت

1. از `main` یک branch بسازید: `feature/description` یا `fix/description`
2. تست‌ها را سبز نگه دارید: `php artisan test`
3. Code Style را رعایت کنید: `./vendor/bin/pint`
4. Static Analysis را پاس کنید: `./vendor/bin/phpstan analyse`
5. API عمومی `/api/v1/*` را بدون نیاز، breaking change ندهید
6. Pull Request بسازید

گزارش باگ: [Issues](https://github.com/davaj841-tech/job-kimi/issues)
