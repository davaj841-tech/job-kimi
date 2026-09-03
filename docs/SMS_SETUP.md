# راه‌اندازی SMS با ملی پیامک (Melipayamak)

این راهنما اتصال JobAzmoon به سرویس **ملی پیامک** را توضیح می‌دهد. مقادیر Secret را هرگز در Git یا مستندات قرار ندهید.

## 1. ساخت حساب ملی پیامک

در [payamak-panel.com](https://payamak-panel.com) ثبت‌نام کنید و حساب خود را فعال کنید.

## 2. دریافت Username و Password

از پنل ملی پیامک، **نام کاربری** و **رمز عبور API** (یا رمز وب‌سرویس) را دریافت کنید.

## 3. دریافت شماره فرستنده (From)

یک **خط ارسال** (عددی یا خدماتی) فعال کنید. برای OTP معمولاً **خط خدماتی + Pattern** لازم است.

## 4. ساخت Pattern برای OTP

1. در پنل، Pattern با متن مشابه `کد ورود شما: {code}` بسازید.
2. پس از تأیید، **bodyId** (شناسه پترن) را یادداشت کنید.
3. متغیر `{code}` در `.env` با `MELIPAYAMAK_PATTERN_TEXT` تنظیم می‌شود (مثلاً `{code}` یا فقط مقدار OTP).

## 5. تنظیم `.env`

```env
SMS_ENABLED=true
SMS_PROVIDER=melipayamak

MELIPAYAMAK_USERNAME=your_username
MELIPAYAMAK_PASSWORD=your_password
MELIPAYAMAK_FROM=5000xxxx
MELIPAYAMAK_API_URL=https://rest.payamak-panel.com/api/SendSMS
MELIPAYAMAK_PATTERN_BODY_ID=12345
MELIPAYAMAK_PATTERN_TEXT="{code}"

# Production
SMS_ALLOW_LOG_FALLBACK=false
```

## 6. پاک‌سازی کش تنظیمات

```bash
php artisan config:clear
php artisan cache:clear
```

## 7. بررسی Configuration (بدون ارسال SMS)

```bash
php artisan sms:health
```

این دستور وضعیت فعال بودن SMS، پیکربندی Provider و دسترسی API (GetCredit) را بررسی می‌کند.

## 8. تست ارسال واقعی

```bash
php artisan sms:test 0912xxxxxxx
```

این دستور **SMS واقعی** ارسال می‌کند. Secretها در خروجی نمایش داده نمی‌شوند.

## 9. فعال‌سازی OTP

OTP به‌صورت پیش‌فرض از Pattern (در صورت تنظیم `MELIPAYAMAK_PATTERN_BODY_ID`) استفاده می‌کند. در پنل ادمین:

- SMS Enabled
- OTP SMS Enabled

را فعال کنید.

## معماری

```text
Application → SmsManager → SmsGatewayInterface → MelipayamakProvider → Melipayamak REST API
```

OTP هم‌زمان (sync) ارسال می‌شود؛ پیام‌های تراکنشی (مثل یادآوری اشتراک) از صف `SendSmsJob` استفاده می‌کنند.

## اطلاعات لازم از شما

برای اتصال Production این مقادیر را در `.env` قرار دهید:

| متغیر | توضیح |
|--------|--------|
| `MELIPAYAMAK_USERNAME` | نام کاربری پنل |
| `MELIPAYAMAK_PASSWORD` | رمز API |
| `MELIPAYAMAK_FROM` | شماره خط (برای SMS عادی) |
| `MELIPAYAMAK_PATTERN_BODY_ID` | شناسه Pattern تأییدشده OTP |

## عیب‌یابی خطای «ارسال کد تأیید با مشکل مواجه شد»

1. `php artisan sms:health` — باید Credentials / From یا Pattern سبز باشند.
2. `php artisan sms:test 09xxxxxxxxx --otp` — خروجی HTTP + StrRetStatus را ببینید.
3. خطاهای رایج ملی پیامک:
   - `InvalidUser` → یوزرنیم/رمز وب‌سرویس اشتباه است.
   - `InvalidBodyId` → پترن OTP تأیید نشده یا اشتباه است؛ یا `sms_from` را برای fallback تنظیم کنید.
   - `missing_credentials` / `********` در تنظیمات → رمز واقعی را دوباره ذخیره کنید (ماسک را ذخیره نکنید).
4. OTP همگام است (صف `SendSmsJob` فقط برای SMSهای غیر-OTP). Worker برای OTP لازم نیست.
5. Production: `SMS_ALLOW_LOG_FALLBACK=false` و اعتبارنامه واقعی در `.env` یا پنل ادمین.

## امنیت

- OTP در لاگ ذخیره نمی‌شود.
- رمز عبور در Exception یا Log چاپ نمی‌شود.
- Rate limit روی ارسال و Verify OTP فعال است.
- با `SMS_ENABLED=false` هیچ SMS ارسال نمی‌شود و برنامه Crash نمی‌کند.
