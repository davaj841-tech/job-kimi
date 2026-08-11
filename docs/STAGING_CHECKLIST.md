# Staging verification checklist

Target: `https://staging.jobazmoon.ir`

## Status (2026-08-10 — local agent)

| Step | Result |
|------|--------|
| `./deploy.sh staging` | **Blocked here** — DNS for `staging.jobazmoon.ir` does not exist; script is Linux-server only |
| `curl …/health` | **Blocked** — NXDOMAIN |
| CSP header | **Blocked** on staging; covered by `tests/Feature/Security/ContentSecurityPolicyTest` |
| Trust Proxies | Covered by `tests/Feature/Security/TrustProxiesTest` |
| Payment double-callback | Covered by `tests/Feature/Payment/IdempotencyTest` |
| PWA / Lighthouse | Run on a live HTTPS host (see below) |

Production `jobazmoon.ir` resolves (`88.135.68.17`) but `/health` timed out from this network.

---

## Run on the staging server

```bash
cd /var/www/jobazmoon   # or your path
./deploy.sh staging

curl -fsS https://staging.jobazmoon.ir/health | jq .
# expect: {"status":"healthy", ...}

curl -sI https://staging.jobazmoon.ir | grep -i content-security
# expect: Content-Security-Policy: ...

# Trust Proxies: from a non-Cloudflare IP, spoof X-Forwarded-For;
# trustedIp() must NOT honor the spoofed client when proxy is untrusted.
# Automated: php artisan test --filter=TrustProxiesTest

# Zarinpal sandbox: charge wallet once, hit verify callback twice with same Authority + ik
# Automated: php artisan test --filter=IdempotencyTest

# PWA: Chrome DevTools → Lighthouse → Progressive Web App (needs HTTPS + installed SW)
# Manifest: /manifest.webmanifest  Icons: /icons/icon-192.png /icons/icon-512.png
# Offline: /offline.html
```

## DNS prerequisite

Create an A/AAAA (or CNAME) for `staging.jobazmoon.ir` pointing at the staging VPS before the curls above will work.
