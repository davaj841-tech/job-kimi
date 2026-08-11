# Screenshots Guide

## How to Take Screenshots

1. Use Chrome DevTools Device Mode (or Playwright script below)
2. Resolution: **1280×720** (desktop) or **390×844** (mobile)
3. Font: Vazirmatn must be loaded
4. Use mock/seed data from `database/seeders` — never capture real PII

## Required Screenshots

| File | Page | Size |
|------|------|------|
| home.png | Homepage with banners and exams | 1280×720 |
| exam-list.png | Exam list with filters | 1280×720 |
| exam-start.png | Exam start / take page with timer | 1280×720 |
| exam-question.png | Question with KaTeX math | 1280×720 |
| exam-result.png | Results with Chart.js | 1280×720 |
| wallet.png | Wallet and transactions | 1280×720 |
| subscription.png | Subscription plans | 1280×720 |
| admin-dashboard.png | Admin panel | 1280×720 |
| mobile-pwa.png | Mobile PWA view | 390×844 |

## Automated capture

```bash
# Terminal 1 — app must be running with built assets or Vite
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 (uses system Chrome/Edge — Playwright CDN may be geo-blocked)
set PLAYWRIGHT_CHANNEL=chrome
npm run screenshots
```

Env vars (optional):

- `SCREENSHOT_BASE_URL` — default `http://127.0.0.1:8000`
- `SCREENSHOT_LOGIN` / `SCREENSHOT_PASSWORD` — user SPA login (default `admin` / `admin1234`)
- `SCREENSHOT_ADMIN_LOGIN` / `SCREENSHOT_ADMIN_PASSWORD` — admin SPA (same defaults)
- `PLAYWRIGHT_CHANNEL` — `chrome` (default) or `msedge` when Chromium download fails
