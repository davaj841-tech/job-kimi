# JobAzmoon Security Audit

Date: 2026-08-19  
Scope: full application (Laravel 11 API, Vue 3 SPA, Filament, payment callbacks, aggregation crawler)  
Method: static review of routes, controllers, middleware, models, storage, payments, frontend rendering, config, and secrets handling. Findings were remediated in this pass. Existing security controls were not removed.

**Overall result: PASS (after remediation)**

---

## Coverage checklist

| Area | Result |
|---|---|
| Authentication (OTP, password, captcha, lockout) | Pass — already hardened; left in place |
| Authorization (roles, operator permissions) | Fixed — fail-closed paths; dual exam/question APIs now use `operator.perm`; operators cannot mutate admins |
| Policies / Gates | No Eloquent policies (role middleware used). Telescope/Horizon gates restricted to admin |
| Middleware | Pass — Sanctum + `user.active` + role + operator.perm retained |
| API IDOR | Fixed — tickets/results/invoices require owner or matching operator permission; 404 not 403 |
| Admin | Fixed — operator cannot reach settings/backups/analytics/unmapped routes |
| File upload | Fixed — SVG blocked; APK MIME checked; PDF confined to `pdfs/` |
| Storage | Pass — product PDFs and invoices on local disk; resume photos confined under public avatars/resumes |
| SQL injection | Pass — Eloquent bindings; `DB::raw` only for aggregates without user input |
| XSS | Fixed — HTML sanitizer on public CMS/job/blog/PDF HTML; search highlight already escaped |
| CSRF | Pass — SPA uses Bearer tokens; CSRF exceptions only `csp-report` |
| SSRF | Pass — aggregation and AI crawler both use `SafeHttpFetcher` |
| Mass assignment | Fixed — profile ignores privileged fields; Filament wallet field not writable |
| Rate limiting | Extended — contact, coupon, payment callbacks (OTP/login already existed) |
| Secrets | Fixed — settings API masks secrets; `.env` not committed; no new secrets in repo |
| Logs | Pass — OTP not logged; payment keys not logged |
| CORS | Fixed — production defaults to `APP_URL`; `CORS_ALLOWED_ORIGINS` documented |
| Sanctum | Pass — token expiry, logout revoke, inactive users revoked |
| Webhooks / payment callbacks | Fixed — authority not taken from route `{id}`; gateway verify retained; callback throttle added |

---

## Findings and remediation

### HIGH — Operator permission fail-open

**Problem:** `OperatorPermissions::permissionForPath()` returned `null` for unmapped admin routes, and middleware treated `null` as allow. Operators could reach analytics (PII), crawler admin, exam-subjects CRUD, aggregation schedule, etc.

**Fix:** Unknown paths now map to `__admin__` (deny). Added explicit maps (`exam-subjects`, `crawler-runs`, `aggregation/*`, `job-classifications`, …). Dashboard helper routes stay open to staff.

### HIGH — Stored XSS via public HTML

**Problem:** Job descriptions, blog/CMS pages, generated articles, and PDF descriptions were returned raw and rendered with `v-html`. Aggregated job HTML or a compromised operator account could inject script.

**Fix:** `App\Support\HtmlSanitizer` strips scripts/handlers/`javascript:` URLs and allows a safe tag subset. Applied on public API resources.

### HIGH — SVG upload stored XSS

**Problem:** Settings logo upload allowed `svg`, which browsers execute if served as `image/svg+xml` from the public disk.

**Fix:** SVG removed from allowed types (`jpg,jpeg,png,webp,ico` only).

### HIGH — Unauthenticated exam update surface

**Problem:** `PUT /api/v1/exams/{id}` was on the user Sanctum group without `role:admin,operator`. Controller had an ownership check, but regular users could still hit the mutation endpoint.

**Fix:** `role:admin,operator` middleware added. Ownership check kept.

### MEDIUM — CORS wildcard

**Problem:** Framework default `allowed_origins: *`. Safe with Bearer tokens and `supports_credentials: false`, but unsafe if cookie auth is enabled later.

**Fix:** `config/cors.php` published. Production with empty `CORS_ALLOWED_ORIGINS` uses `APP_URL`.

### MEDIUM — Contact spam / no captcha

**Problem:** Public `/contact` had only the global API throttle.

**Fix:** `auth.captcha` + `throttle:contact` (5/min). Vue contact form sends captcha fields.

### MEDIUM — Coupon enumeration

**Fix:** `throttle:coupon` (10/min).

### CRITICAL — Operator could mutate administrators (Filament + API)

**Problem:** Operators with the `users` permission could edit/delete existing admins (role, status, password) via `AdminUserController`. Filament `UserResource` had no `canViewAny` gate, so any panel operator could also promote users and change subscriptions.

**Fix:** Filament `UserResource` is admin-only (`canViewAny` / create / edit / delete). API mutations of `role=admin` accounts are refused for non-admins. Operators cannot set role, status, password, verification, or subscription fields. Role changes are admin-only. Operators may only create `jobseeker`/`employer` accounts.

### HIGH — Dual exam/question APIs skipped operator permissions

**Problem:** `POST/PUT/DELETE /api/v1/exams` and `/api/v1/questions` used `role:admin,operator` without `operator.perm`. An operator without `questions` could still mutate questions.

**Fix:** `operator.perm` added to those staff routes. Path mapping already covers `exams` and `questions`.

### HIGH — Staff shortcut on tickets, results, invoices

**Problem:** Any operator (not only those with `tickets` / `exams` / `transactions`) could read another user’s ticket (including mobile), exam result/answer sheet, or invoice.

**Fix:** Owner **or** `OperatorPermissions::allows()` for the matching permission. Unauthorized still returns 404.

### HIGH — AI crawler SSRF

**Problem:** `AIService::fetchPageContent()` called `Http::get($url)` on caller-supplied URLs.

**Fix:** Uses `SafeHttpFetcher` (allowlist + private/reserved IP block). Off-allowlist and loopback URLs return empty content.

### HIGH — Resume photo path traversal into PDF

**Problem:** `Resume::photoAbsolutePath()` accepted any `file_exists($photo)` string (e.g. `base_path('.env')`) and DomPDF used `chroot = base_path()` with remote enabled.

**Fix:** Photos must be relative `avatars/` or `resumes/` paths on the public disk. DomPDF `chroot` is `storage_path()` and `isRemoteEnabled` is false.

### HIGH — PDF purchase race / double grant

**Problem:** `completeZarinPalPurchase` had no `completeOnce` lock; `pdf_purchases` only had a non-unique index.

**Fix:** Completes via `IdempotencyService::completeOnce()` with `lockForUpdate` on the purchase row. Unique `(user_id, pdf_product_id)`.

### MEDIUM — Public invoice PDFs

**Problem:** Invoices were written to the public disk and the public URL stored on the transaction.

**Fix:** Stored on the `local` disk. API resources expose the authenticated download URL only.

### MEDIUM — Job submit catalog IDs

**Problem:** Public `POST /job-posts/submit` accepted `exam_ids` / `pdf_ids` / `status` / `is_featured`.

**Fix:** Those fields are stripped; description is sanitized with `HtmlSanitizer` at save time.

### MEDIUM — Payment callback `id` collision

**Problem:** `extractAuthority()` used `$request->input('id')`, which includes the route `{id}` for PDF verify.

**Fix:** Authority is read from payment query/body keys only; `id` is accepted from the query string when it differs from the route id.

### MEDIUM — Wallet charge unbounded

**Fix:** Max amount via `max_wallet_charge` (default 50,000,000 Rials).

### MEDIUM — Settings secrets in JSON

**Fix:** Passwords/API keys returned as `********`; masked values are not written back.

### MEDIUM — PDF path confinement

**Fix:** Download paths must stay under the `pdfs/` disk directory; `..` rejected.

### MEDIUM — APK upload extension-only

**Fix:** MIME allow-list in addition to `.apk` extension.

### MEDIUM — Telescope / Horizon operator access

**Fix:** Gates limited to `role === admin`.

### MEDIUM — Password change left other tokens alive

**Fix:** Other Sanctum tokens revoked after password update; current token kept.

### LOW — IDOR existence leak

**Fix:** Ticket/invoice unauthorized access now 404 instead of 403.

### LOW — Banner `javascript:` links

**Fix:** Link must start with `http(s)://` or `/`.

### LOW — Page-view analytics flood

**Fix:** `throttle:30,1` on `/page-views`.

---

## Controls reviewed and left unchanged

- OTP HMAC storage, expiry, reuse invalidation, rate limit, lockout
- Math captcha + Turnstile
- Account status middleware on Sanctum groups
- Payment verify still calls gateway `verify()` with stored amount (no client amount trust)
- Idempotency on wallet deposit
- Aggregation `SafeHttpFetcher` host/IP allowlist
- Security headers (CSP, HSTS in production, `X-Frame-Options: DENY`)
- `otp_code` hidden on User
- SMS log fallback disabled in production

---

## Residual risk (accepted)

1. **Staff XSS:** Admins/operators can still publish HTML (sanitized). KaTeX exam stems in admin views still use `v-html` for math; only staff author questions.
2. **Payment callbacks are public** by design. Forgery without a valid gateway verification still fails.
3. **No Laravel Policies** — authorization is middleware + inline checks. Sufficient if new routes keep using those layers.
4. **Filament** remains a second admin UI for operators (exams, jobs, …). User management and settings are admin-only. Wallet field stays display-only.
5. Set `CORS_ALLOWED_ORIGINS` explicitly in production.
6. **CSP** still includes `'unsafe-inline'` for legacy Vue/admin scripts.
7. Gateway-specific verify gaps (e.g. IDPay amount check) remain defense-in-depth; ZarinPal path verifies stored amount.

---

## Tests added

- `tests/Feature/Security/SecurityAuditTest.php`
- `tests/Unit/Support/HtmlSanitizerTest.php`
- Operator permission cases extended in `tests/Feature/OperatorPermissionTest.php`
- Contact captcha coverage in `tests/Feature/ContactMessageTest.php`
