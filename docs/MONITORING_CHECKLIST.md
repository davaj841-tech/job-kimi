# Post-deploy monitoring checklist

Run within **15–30 minutes** after `./deploy.sh production` (and again after the first traffic spike).

| Tool | What to check | Where / how | Pass criteria |
|------|---------------|-------------|---------------|
| **Sentry** | No new unresolved errors | [sentry.io](https://sentry.io) → JobAzmoon project → Issues (filter: last 1h, env=`production`) | No new regressions; known noise muted |
| **Horizon** | Queues healthy | `https://jobazmoon.ir/horizon` (auth required) | Pending not stuck; Failed Jobs = 0 (or only known retries); workers Active |
| **Telescope** | No slow queries | `https://jobazmoon.ir/telescope` → Queries (if `TELESCOPE_ENABLED=true`) | No unexpected queries over threshold (default watcher ~100ms); N+1 spikes absent |
| **Cloudflare** | Rate limiting normal | CF Dashboard → Security → Events / Rate limiting | No sudden block storm; legitimate `/api/*` not mass-challenged |
| **CSP Reports** | Violations log | On VPS: `tail -n 50 storage/logs/csp-violations.log` | No flood of new directives; spot-check `blocked-uri` / `violated-directive` |
| **Zarinpal** | Callback failures | [panel.zarinpal.com](https://panel.zarinpal.com) + app `transactions` / failed payments | No unpaid-but-charged; duplicate verifies stay idempotent |

## Quick SSH helpers

```bash
cd /var/www/jobazmoon   # adjust

# Health
curl -fsS "${APP_URL:-https://jobazmoon.ir}/health" | jq .

# Horizon failed jobs (Redis / DB depending on config)
php artisan horizon:status

# Recent CSP violations
tail -n 100 storage/logs/csp-violations.log
# or last hour (if timestamps present):
grep "$(date -u +%Y-%m-%d)" storage/logs/csp-violations.log | tail -n 50

# Laravel errors (backup signal if Sentry lags)
tail -n 100 storage/logs/laravel.log | grep -iE 'ERROR|CRITICAL|payment|zarinpal' || true
```

## App wiring (already in repo)

| Concern | Implementation |
|---------|----------------|
| Sentry (backend) | `sentry/sentry-laravel` → `config/sentry.php` (`SENTRY_LARAVEL_DSN`) |
| Sentry (frontend) | `@sentry/vue` in `resources/js/app.ts` (`VITE_SENTRY_DSN`) |
| Horizon | `laravel/horizon` → `/horizon` |
| Telescope | `laravel/telescope` → `/telescope` (`TELESCOPE_ENABLED`) |
| CSP reports | `POST /csp-report` → channel `csp` → `storage/logs/csp-violations.log` |
| Cloudflare | Trust Proxies + Turnstile; rate limits configured in CF dashboard |
| Zarinpal | Payment gateways + idempotent verify (`IdempotencyTest`) |

## Cadence

| When | Focus |
|------|--------|
| T+0–30m after deploy | Full table above |
| Daily (ops) | Sentry unresolved, Horizon failed, Zarinpal anomalies |
| Weekly | Telescope slow queries (if enabled), CSP log trend, CF WAF false positives |

## Status (local agent)

Dashboards and production logs are **not reachable** from this workstation (no VPS SSH / CF / Sentry session). Checklist is ready to run on the server and in vendor UIs after deploy.
