# سیستم کیف پول JobAzmoon

## واحد پول

| لایه | واحد |
|------|------|
| Database | **ریال (IRR)** — integer |
| Frontend کاربر | **ریال** |
| Admin Panel | **ریال** |

Helper مرکزی: `App\Support\PaymentAmount`. از float برای محاسبات مالی استفاده نمی‌شود.

## معماری

```text
WalletService (تنها نقطه تغییر balance)
    ├── wallet_ledgers   ← منبع حقیقت (append-only, HMAC hash chain)
    ├── users.wallet_balance ← cache (غیر fillable، مستقیم قابل تغییر نیست)
    └── transactions     ← رکورد کسب‌وکار (deposit/purchase/withdrawal/refund)
```

### اصول

- هر تغییر balance از `WalletService::credit/debit/post` عبور می‌کند.
- `lockForUpdate` روی `users` + `DB::transaction`.
- Idempotency با `source_key` یکتا در `wallet_ledgers`.
- Ledger غیرقابل update/delete است.
- موجودی اولیه غیرصفر با `opening:{user_id}` در اولین mutation ثبت می‌شود.

## ایجاد Wallet

Wallet جداگانه ساخته نمی‌شود. هر `User` فیلد `wallet_balance` دارد (پیش‌فرض ۰). اولین عملیات مالی ledger را ایجاد می‌کند.

## جریان‌ها

### 1. شارژ (Deposit) — ZarinPal

```text
POST /api/v1/wallet/charge
→ Transaction (pending, gateway=zarinpal)
→ ZarinPal request → redirect
→ POST /api/v1/wallet/verify (server-side verify)
→ IdempotencyService::completeOnce
→ WalletService::deposit (source_key: payment:{tx_id})
→ credit ledger + cache balance
```

### 2. خرید با کیف پول

**اشتراک:** `SubscriptionService` → `WalletService::debit` (`subscription:{id}`)

**PDF:** `PDFProductService::purchaseWithWallet` → debit (`pdf:{tx_id}`) + `PdfPurchase`

### 3. Refund

`WalletService::refund()` — reversal idempotent؛ تراکنش اصلی delete نمی‌شود.

### 4. Admin

| عملیات | Endpoint | فیلد reason |
|--------|----------|-------------|
| Credit | `POST /api/v1/admin/wallets/{id}/charge` | description (اختیاری) |
| Debit | `POST /api/v1/admin/wallets/{id}/deduct` | **reason (اجباری)** |
| Freeze | `POST /api/v1/admin/wallets/{id}/freeze` | **reason (اجباری)** |
| Unfreeze | `POST /api/v1/admin/wallets/{id}/unfreeze` | **reason (اجباری)** |
| Refund | `POST /api/v1/admin/transactions/{id}/refund` | **reason (اجباری)** |
| Ledger | `GET /api/v1/admin/wallets/{id}/ledger` | — |

Audit: `wallet.admin_charged`, `wallet.admin_deducted`, `wallet.refund`, `wallet.freeze`, `wallet.unfreeze`.

## Wallet Freeze

فیلد `users.wallet_frozen_at` (nullable). وقتی set باشد شارژ/خرید/برداشت کاربر مسدود است؛ مشاهده balance و تراکنش‌ها مجاز است. Admin operations با `bypass_wallet_freeze` مجازند.

## فیلتر تراکنش کاربر

`GET /api/v1/wallet?type=deposit|purchase|withdrawal|refund|bonus|adjustment&page=1&per_page=15`

## Reconciliation

```bash
php artisan wallet:reconcile              # system-wide + per-user scan
php artisan wallet:reconcile --user=123   # single user
```

زمان‌بندی: روزانه ۰۳:۱۵ (`routes/console.php`).

Admin stats: `GET /api/v1/admin/wallets/stats` → `reconciled`, `ledger_total`.

## انواع Ledger

`deposit`, `withdrawal`, `purchase`, `refund`, `admin_credit`, `admin_debit`, `opening`

## امنیت

- Authorization: Sanctum + ownership روی wallet endpoints
- Mass assignment: `wallet_balance` غیر fillable + guard در `User::booted`
- Double spend: row lock + idempotent source_key
- Callback replay: verify سمت سرور + idempotency (مستندات پرداخت: `docs/PAYMENT_ZARINPAL.md`)

## تست‌ها

```bash
php artisan test --filter=Wallet
```

پوشش: ledger integrity, concurrency, idempotency, PDF wallet purchase, reconcile command, admin audit.

## Production Safety

- **هرگز** `migrate:fresh`, `db:wipe`, TRUNCATE روی production
- `WalletTransactionSeeder` در production اجرا نمی‌شود (ledger drift)
- اصلاح balance فقط از طریق WalletService (refund/adjustment)

## شکاف‌های شناخته‌شده (غیر بحرانی)

- (تکمیل شد) Admin refund API/UI
- (تکمیل شد) Wallet freeze/unfreeze
- (تکمیل شد) فیلتر نوع تراکنش در پنل کاربر
- API عمومی ledger history برای کاربر (فقط transactions)
