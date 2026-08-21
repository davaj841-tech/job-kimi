# Frontend Security Notes

## Token storage (future hardening)

The Vue SPA stores Sanctum bearer tokens in `localStorage` (`token`, `user`) and the admin panel uses `admin_token` / `admin_user`. This matches the current architecture and is **not** migrated in this phase.

**Risk:** Any XSS vulnerability can exfiltrate tokens from `localStorage`.

**Future options (out of scope now):**

- HttpOnly secure cookies with SameSite=Lax/Strict
- Short-lived access tokens + refresh rotation
- Strict CSP (partially configured via `SecurityHeaders` middleware)

## Current safeguards verified

- Tokens, passwords, and OTP codes are not logged in frontend code paths reviewed in this task.
- API error messages surface backend `message` fields only; no secret fields are appended client-side.
- Exam offline cache stores question IDs and answer keys only — no credentials.
- Production build strips dev `console.log` debug statements; PDF viewer errors are shown in UI instead of logging to console.

## Recommendations

1. Keep CSP headers enabled in production (`app/Http/Middleware/SecurityHeaders.php`).
2. Audit third-party scripts (Turnstile, Sentry) for minimal permissions.
3. Consider migrating to cookie-based Sanctum SPA auth in a dedicated hardening sprint.
