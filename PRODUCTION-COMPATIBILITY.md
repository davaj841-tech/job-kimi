# PRODUCTION-COMPATIBILITY (Shared Hosting / cPanel)

این سند فقط برای سازگاری با هاست اشتراکی/cPanel طراحی شده است (PHP 8.2/8.3، MySQL/MariaDB، بدون دسترسی تضمینی به Redis، بدون `pcntl`/`posix`، بدون Horizon).

## 0) فرض‌ها (برای اینکه همه چیز پایدار بماند)
- Document Root فقط باید پوشه `public/` باشد.
- Redis را *اجباری* فرض نمی‌کنیم؛ یعنی برای queue/cache/session باید با `database` کار کنیم.
- Horizon فقط اگر خودتان لازم داشتید اجرا می‌شود؛ در معماری پیش‌فرضِ Shared Hosting اجرا نمی‌شود.

## 1) Composer dependencies
1. فایل `composer.lock` باید روی سرور موجود باشد (الزام شماره 1). در این پروژه وجود دارد.
2. برای نصب production:
   - `composer install --no-dev --optimize-autoloader --prefer-dist`
3. بررسی بسته‌ها:
   - `laravel/horizon` و `laravel/pulse` برای مانیتورینگ هستند (به معنی موردنیاز بودن در runtime نیستند).
   - برای Shared Hosting/cPanel معماری queue بر پایه `database` است، بنابراین Horizon را اجرا نکنید.

## 2) PHP extensions (حداقل‌های معمول برای این پروژه روی cPanel)
بر اساس چک‌های موجود در `cpanel-installer/install.php`:
- `pdo`, `pdo_mysql`
- `openssl`
- `mbstring`
- `xml`
- `ctype`
- `json`
- `fileinfo`
- `gd`
- `zip`
- `dom`
- `session_start()` فعال باشد (session در کد و نصب استفاده می‌شود)

## 3) Horizon و معماری fallback (بدون pcntl/posix)
### وضعیت
- `laravel/horizon` ممکن است برای اجرای workerهای پیشرفته به `pcntl/posix` وابسته باشد (یا هنگام اجرا با محدودیت هاست سازگار نباشد).
### fallback پیشنهادی (همخوان با نیاز شما)
- Horizon را اجرا نکنید (نه `php artisan horizon` و نه `/horizon` در cPanel).
- صف را با database worker اجرا کنید.

### معماری queue برای Shared Hosting
به جای worker طولانی (که ممکن است با محدودیت time/execution در هاست اشتراکی مشکل بخورد)، از اجرای کوتاه و دوره‌ای استفاده کنید:
- cron هر 1 دقیقه:
  - `cd /path/to/app && php artisan queue:work --once --queue=crawlers,default --tries=3 --timeout=150`

> نکته: اگر `--queue` شما در محیطی متفاوت از این مقادیر است، همان queueهایی را بگذارید که در جدول `jobs`/تنظیمات شما تولید می‌شود.

## 4) Redis اختیاری است (مگر واقعاً ضروری)
برای اینکه بدون Redis هم کار کند:
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`

در این حالت Redis حتی اگر نصب باشد هم مورد استفاده معمول نیست.

## 5) Cache / Session با database (بدون Redis)
پروژه به صورت پیش‌فرض می‌تواند:
- Cache را روی `database` بگذارد
- Session را روی `database` بگذارد

بنابراین Redis لازم نیست. صرفاً مطمئن شوید migrationهای مرتبط با cache/session با `php artisan migrate --force` اجرا شده‌اند.

## 6) Queue با database driver
لازم است:
- migrationهای `jobs`, `failed_jobs`, `job_batches` اجرا شده باشند.
- محیط به `QUEUE_CONNECTION=database` تنظیم شود.

برای اجرای worker از cron (fallback بالا) استفاده کنید.

## 7) Scheduler با cPanel cron
Laravel Scheduler با این الگو کار می‌کند:
- `* * * * * cd /path/to/app && php artisan schedule:run`

بهتر است خروجی را خاموش کنید:
- `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`

## 8) Document root فقط public/
در cPanel:
- Document Root (یا “Select PHP Version / File Manager root”) را روی `public/` بگذارید.
- این کار باعث می‌شود `.env`, `vendor`, و پوشه‌های `app` از وب قابل دسترسی نباشند.

## 9) عدم دسترسی وب به .env / vendor / app
با Document Root فقط `public/` این شرط برقرار می‌شود.
همچنین در این پروژه:
- `.env` در ریشه پروژه است و در گیت هم ignore شده است.
- `public/.htaccess` مسیرها را به `public/index.php` هدایت می‌کند.

## 10) Storage link
لازم است لینک درست `storage` به `public/storage` برقرار شود:
- `php artisan storage:link --force`

اگر در cPanel symlink ممنوع بود:
- یا از پنل هاست “Map/Copy” معادل symlink استفاده کنید
- یا فایل‌ها را کپی کنید (مطابق امکانات هاست).

## 11) Vite production build
`public/build` در گیت نیست (ignore شده). پس باید قبل از آپلود نهایی تولید شود:
- روی سیستم خودتان:
  - `npm ci`
  - `npm run build`
- سپس پوشه `public/build` را روی سرور آپلود کنید.

## 12) APP_DEBUG در production خاموش باشد
در `.env`:
- `APP_ENV=production`
- `APP_DEBUG=false`

## 13) Production logging امن
پیشنهاد برای کاهش ریسک افشای اطلاعات:
- `LOG_LEVEL=error`
- `LOG_CHANNEL=stack` (یا `single`)
- از داشتن `APP_DEBUG=true` در production جلوگیری کنید

> همچنین در پروژه خطاها به صورت امن با `SiteErrorLogger` در دیتابیس ثبت می‌شوند (trace هم truncation دارد)، اما بهتر است در production جزئیات debug را خاموش نگه دارید.

## 14) Health check route
برای پایش، این مسیرها:
- `GET /health` (وب)  
- `GET /up` (health در bootstrap)  
- `GET /api/v1/health` (API health)

`/api/v1/health` به‌صورت کدنویسی‌شده از ریدایرکت به `/install` معاف شده تا uptime checks در Shared Hosting fail نشود.

## 15) Deployment checklist (Shared Hosting / cPanel)
این ترتیب پیشنهادی:
1. آپلود کد به سرور (بدون `.env` و بدون `vendor` برای وب).
2. PHP extensions را فعال کنید (بخش 2).
3. `composer.lock` وجود دارد.
4. `composer install --no-dev --optimize-autoloader` روی سرور.
5. `npm ci && npm run build` (یا آپلود `public/build`).
6. `php artisan key:generate` (بار اول).
7. `php artisan migrate --force`
8. `php artisan db:seed --force` (اگر seed در برنامه شما لازم است).
9. `php artisan storage:link --force`
10. `php artisan config:cache && php artisan route:cache && php artisan view:cache` (اگر روی هاست مجاز است).
11. تنظیمات `.env`:
    - `APP_DEBUG=false`
    - `QUEUE_CONNECTION=database`
    - `CACHE_STORE=database`
    - `SESSION_DRIVER=database`
12. مجوزها:
    - `storage/` و `bootstrap/cache/` قابل نوشتن باشند.
13. Cronها:
    - schedule:run هر دقیقه
    - queue:work --once هر دقیقه (fallback بدون horizon/redis)

## 16) Installer هماهنگ با معماری نهایی
- `INSTALL.md` و نصب cPanel طوری تنظیم شده‌اند که:
  - horizon به عنوان “ضروری برای شروع کار” در نظر گرفته نمی‌شود.
  - queue و cache/session با database هماهنگ هستند.
  - `GET /api/v1/health` در مرحله نصب هم ریدایرکت نمی‌شود.

