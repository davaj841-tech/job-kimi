# JobAzmoon Payment / ZarinPal Audit

Date: 2026-08-19  
Scope: wallet charge, subscription purchase, PDF purchase, PaymentAction, InitiatePayment, gateways, callbacks, verify, transactions.

**Overall: PASS after this pass**

## Requirements

| # | Requirement | Result |
|---|---|---|
| 1 | Amount not trusted from frontend | Pass — callback ignores `amount`; wallet charge is clamped then stored |
| 2 | Final amount from database | Pass — verify uses `(int) $transaction->amount` |
| 3 | Callback not forgeable | Pass — unknown authority 404; real gateway verify required |
| 4 | Real gateway verify | Pass — ZarinPal `/pg/v4/payment/verify.json` with stored amount |
| 5 | Transaction id / authority stored | Pass — `id` as order_id, `reference_id` = authority |
| 6 | Duplicate callback safe | Pass — `completeOnce` + already_processed |
| 7 | Verify idempotent | Pass |
| 8 | Success credited once | Pass — row lock + wallet/subscription guards |
| 9 | Failed status | Pass — `failed` |
| 10 | Cancel status | Pass — ZarinPal `NOK` → `cancelled` (not retryable) |
| 11 | Timeout | Pass — `payments:expire-pending` every 15 minutes |
| 12 | Callback race | Pass — lock before credit; HTTP verify outside lock |
| 13 | Wallet not double-charged | Pass |
| 14 | Subscription not double-activated | Pass |
| 15 | DB transactions | Pass — initiate create, `completeOnce`, wallet/subscription locks |
| 16 | Audit trail | Pass — `payment.initiated/verified/failed/cancelled/expired_batch` + `payment.success` |
| 17 | Secrets from .env | Pass — merchant/API keys via `config/services.php` |
| 18 | Sandbox vs production | Pass — `ZARINPAL_SANDBOX` selects sandbox vs payment host |
| 19 | Logs do not leak secrets | Pass — HTTP status/code only, no body/merchant/card |
| 20 | Fake gateway for PHPUnit | Pass — `FakePaymentGateway` when `PAYMENT_FAKE=true` |

## Flow (wallet)

1. **Create** `POST /api/v1/wallet/charge` — amount validated against min/max, pending row, gateway `request()`, store authority.
2. **Redirect** — client opens `payment_url`.
3. **Callback** `POST /api/v1/wallet/verify` — Authority + Status; amount query ignored.
4. **Verify** — gateway `verify(authority, dbAmount)`.
5. **Success** — `completeOnce` credits wallet once.

## Residual

- Wallet top-up amount is user-chosen by design; it is stored server-side before redirect and never re-read from the callback.
- Mellat/Shaparak still need `SaleReferenceId` on the callback or verify fails closed.
