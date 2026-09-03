# راه‌اندازی پرداخت زرین‌پال (ZarinPal)

## واحد پول

| لایه | واحد |
|------|------|
| Database | **ریال (IRR)** |
| ZarinPal API | **ریال (IRR)** |
| Frontend کاربر | **ریال** |
| Admin Panel | **ریال** |

تبدیل ریال/تومان در پروژه انجام نمی‌شود. Helper مرکزی: `App\Support\PaymentAmount`.

## تنظیم `.env`

```env
PAYMENT_GATEWAY=zarinpal
PAYMENT_FAKE=false
ZARINPAL_MERCHANT_ID=your-merchant-id
ZARINPAL_SANDBOX=true
ZARINPAL_CURRENCY=IRR
ZARINPAL_TIMEOUT=15
MIN_WALLET_CHARGE=10000
MAX_WALLET_CHARGE=50000000
```

Production: `ZARINPAL_SANDBOX=false` و `PAYMENT_FAKE=false`.

## جریان پرداخت

```text
User → Transaction (pending) → ZarinPal request → Redirect
→ SPA /payment/* → API verify → Gateway verify (server-side)
→ IdempotencyService::completeOnce → Fulfillment
```

**قانون:** پرداخت فقط بعد از Verify موفق سمت سرور موفق است — Callback به‌تنهایی کافی نیست.

## Callback

زرین‌پال کاربر را به SPA برمی‌گرداند:
- `/payment/wallet`
- `/payment/subscription`
- `/payment/pdf?pdf_id=`

`PaymentResultView.vue` سپس API verify را با `Authority`, `Status`, `ik` فراخوانی می‌کند.

## تست

```bash
php artisan test --filter=Payment
```

Sandbox واقعی (دستی):
1. `ZARINPAL_SANDBOX=true` و Merchant Sandbox
2. شارژ کیف پول از UI
3. پرداخت در Sandbox زرین‌پال
4. بازگشت و Verify

## امنیت

- مبلغ از DB خوانده می‌شود، نه از Callback
- `idempotency_key` در URL callback (`?ik=`)
- Duplicate callback → `already_processed: true`
- Authority در `transactions.reference_id` ذخیره می‌شود

## دستورات

```bash
php artisan payments:expire-pending   # هر ۱۵ دقیقه (cron)
```
