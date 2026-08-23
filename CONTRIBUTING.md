# مشارکت در JobAzmoon

از مشارکت شما استقبال می‌کنیم. این راهنما نحوه راه‌اندازی محیط توسعه، استانداردهای کد، و فرآیند Pull Request را توضیح می‌دهد.

---

## ۱. پیش‌نیازها

| ابزار | نسخه حداقل |
|--------|------------|
| PHP | 8.2+ (توصیه: 8.3) |
| Composer | 2.x |
| Node.js | 18+ |
| npm | همراه Node |
| MySQL | 8.x (یا MariaDB معادل / SQLite برای لوکال) |
| Redis | توصیه برای queue، cache، session و Horizon |

پسوندهای PHP مورد نیاز: `intl`, `gd`, `mbstring`, `pdo`, `tokenizer`, `xml`, `curl`, `zip`, `bcmath`

---

## ۲. راه‌اندازی محیط توسعه

```bash
git clone https://github.com/davaj841-tech/job-kimi.git
cd job-kimi

composer install
cp .env.example .env
php artisan key:generate

# تنظیم DB_* و در صورت نیاز REDIS_* در .env
php artisan migrate --seed
php artisan storage:link

npm install
npm run build
```

### اجرای محیط توسعه (hot reload)

```bash
composer run dev
```

این دستور همزمان Laravel Serve، Queue Worker، لاگ (`pail`) و Vite را بالا می‌آورد.

### داده‌های دمو (اختیاری)

```bash
php artisan db:seed --class=DemoDataSeeder
```

---

## ۳. استراتژی Branch

جریان کاری پیش‌فرض:

```text
main  →  feature/xxx  →  Pull Request  →  main
```

قوانین:

1. همیشه از `main` به‌روز شاخه بسازید.
2. نام شاخه را توصیفی نگه دارید:
   - `feature/...` برای قابلیت جدید
   - `fix/...` برای رفع باگ
   - `chore/...` برای نگهداری / tooling
   - `docs/...` برای مستندات
3. یک PR برای یک موضوع مشخص؛ تغییرات بزرگ را به PRهای کوچک‌تر بشکنید.
4. بدون تأیید و عبور از CI، مستقیماً روی `main` push نکنید.

مثال:

```bash
git checkout main
git pull origin main
git checkout -b feature/exam-autosave-retry
# ... تغییرات ...
git push -u origin feature/exam-autosave-retry
```

سپس از GitHub یک Pull Request به `main` باز کنید (قالب: `.github/PULL_REQUEST_TEMPLATE.md`).

---

## ۴. استانداردهای کد

### PHP

- سبک کد: **PSR-12**
- قالب‌بندی خودکار با **Laravel Pint**

```bash
./vendor/bin/pint
./vendor/bin/pint --test
```

- تحلیل ایستا: **PHPStan level 6** (Larastan)

```bash
./vendor/bin/phpstan analyse --memory-limit=1G
# یا
composer phpstan
```

### Vue / TypeScript

- **ESLint** برای کیفیت کد
- **Prettier** برای قالب‌بندی

```bash
npm run lint
npm run lint:fix
npm run format
npm run format:check
npm run type-check
```

### 🚨 Commit Messages (اجباری)

همه commit ها **باید** از [Conventional Commits](https://www.conventionalcommits.org/) پیروی کنند.

قبل از اولین commit، حتماً hook را نصب کنید:

```bash
./scripts/install-hooks.sh
git config commit.template .gitmessage
```

اگر hook نصب باشد، commit با پیام بد **reject** می‌شود.

فرمت:

```text
type(scope): description
```

انواع رایج:

| type | کاربرد |
|------|--------|
| `feat` | قابلیت جدید |
| `fix` | رفع باگ |
| `docs` | مستندات |
| `style` | قالب‌بندی بدون تغییر منطق |
| `refactor` | بازنویسی بدون تغییر رفتار |
| `test` | افزودن یا اصلاح تست |
| `chore` | نگهداری، وابستگی، CI |
| `perf` | بهبود عملکرد |
| `security` | اصلاح امنیتی |
| `ci` / `build` / `revert` | CI، بیلد، برگرداندن commit |

مثال‌ها:

```bash
./scripts/commit.sh
# یا
git commit -m "feat(exam): add autosave retry on network failure"
git commit -m "fix(wallet): prevent double debit on verify timeout"
git commit -m "docs(api): regenerate Scribe after payment endpoints"
git commit -m "chore(ci): raise phpstan memory limit to 1G"
```

❌ ممنوع: `14050601`، `ok`، `committed`، `delete file`

- پیام را به **انگلیسی** و در زمان حال امری بنویسید (`add` نه `added`).
- در صورت نیاز، جزئیات بیشتر را در بدنه commit بنویسید.

### قوانین API

- Breaking change روی `/api/v1/*` بدون هماهنگی و نسخه جدید ممنوع است.
- منطق کسب‌وکار را بدون نیاز واقعی تغییر ندهید؛ ترجیح با refactor امن و تست‌پوشیده است.

---

## ۵. چک‌لیست قبل از PR

قبل از درخواست بازبینی، همه موارد زیر باید سبز باشند:

```bash
# تست‌های Laravel
php artisan test

# تست‌های واحد فرانت‌اند
npm run test:unit

# تحلیل ایستا
./vendor/bin/phpstan analyse --memory-limit=1G

# سبک PHP
./vendor/bin/pint --test

# اگر مسیرها / کنترلرها / مستندات API تغییر کرده:
php artisan scribe:generate
```

چک‌لیست سریع:

- [ ] `php artisan test` بدون شکست
- [ ] `npm run test:unit` بدون شکست
- [ ] `phpstan analyse` بدون خطا
- [ ] `pint --test` بدون خطا
- [ ] در صورت تغییر API: `scribe:generate` اجرا و خروجی commit شده
- [ ] `npm run lint` و در صورت نیاز `npm run type-check` پاس شده
- [ ] توضیحات PR واضح است و اسکوپ تغییر مشخص شده
- [ ] CHANGELOG در صورت نیاز به‌روز شده (`[Unreleased]`)

---

## ۶. گزارش باگ

برای باگ‌های غیر امنیتی از [GitHub Issues](https://github.com/davaj841-tech/job-kimi/issues) استفاده کنید و قالب زیر را پر کنید:

```markdown
## توضیح
خلاصه کوتاه از مشکل.

## مراحل بازتولید
1. برو به `...`
2. کلیک روی `...`
3. مشاهده خطا / رفتار نادرست

## رفتار مورد انتظار (Expected)
چه اتفاقی باید می‌افتاد؟

## رفتار واقعی (Actual)
چه اتفاقی افتاد؟

## محیط
- OS:
- Browser / App (PWA):
- Backend version / commit:
- PHP / Node (در صورت مرتبط):

## شواهد (اختیاری)
- اسکرین‌شات
- لاگ مرتبط (بدون secret)
- Response / Request نمونه (بدون توکن و داده حساس)
```

عنوان Issue را کوتاه و مشخص بنویسید، مثلاً: `Wallet debit fails after Zarinpal verify timeout`.

---

## ۷. گزارش امنیتی

آسیب‌پذیری‌های امنیتی را **به‌صورت عمومی Issue نکنید**.

به‌جای آن، گزارش را به‌صورت خصوصی به ایمیل زیر بفرستید:

**`security@jobazmoon.ir`**

لطفاً در ایمیل این موارد را بیاورید:

- نوع آسیب‌پذیری (مثلاً XSS، IDOR، SSRF، نشت secret)
- شدت تقریبی و اثر روی کاربران / داده
- مراحل بازتولید یا PoC حداقلی
- نسخه / محیط مورد آزمایش
- پیشنهاد رفع (اختیاری)

ما معمولاً دریافت را تأیید می‌کنیم و پس از بررسی و انتشار وصله، در صورت تمایل شما را در اعتبارسنجی ذکر می‌کنیم. تا زمان انتشار fix، جزئیات را عمومی نکنید.

---

## سوالات بیشتر

- نمای کلی پروژه: [`README.md`](README.md)
- تغییرات نسخه‌ها: [`CHANGELOG.md`](CHANGELOG.md)
- قالب PR: [`.github/PULL_REQUEST_TEMPLATE.md`](.github/PULL_REQUEST_TEMPLATE.md)

از وقتی که برای بهتر شدن JobAzmoon می‌گذارید ممنونیم.
