# Performance

Indexes, caching, queues, and frontend budgets for JobAzmoon.

---

## Database indexes

Indexes added in `2026_08_09_230000_add_performance_indexes.php` (MySQL uses `ALGORITHM=INPLACE, LOCK=NONE`).

### Schema mapping

| Prompt name | Actual table / column |
|---|---|
| `exam_results` | `exam_attempts` |
| `jobs` (postings) | `job_posts` |
| `tracking_code` | `reference_id` (already indexed — not re-added) |
| `questions.content` | `question_text` |
| `questions.sort_order` | not present — skipped |
| `exams.slug` unique | already unique — skipped |
| `users.mobile` | already unique — skipped |
| `transactions.idempotency_key` | already unique — skipped |

### Indexes and queries they support

#### `exams`
| Index | Optimizes |
|---|---|
| `idx_exams_filter` (`status`, `category_id`, `created_at`) | Public catalog (`ExamRepository::getPublished`) |
| `idx_exams_creator` (`created_by`, `status`) | Admin/creator listing |

#### `questions`
| Index | Optimizes |
|---|---|
| `idx_questions_exam_subject` (`exam_id`, `subject`) | Subject grouping / filters |
| `idx_questions_content_ft` FULLTEXT(`question_text`) | MySQL-only FTS (skipped on SQLite) |

#### `transactions`
| Index | Optimizes |
|---|---|
| `idx_transactions_user_status` (`user_id`, `status`, `created_at`) | Wallet history |
| `idx_transactions_type_status` (`type`, `status`) | Admin finance filters |

#### `exam_attempts` (results)
| Index | Optimizes |
|---|---|
| `idx_results_user_exam_score` (`user_id`, `exam_id`, `score`) | Best score lookups |
| `idx_results_leaderboard` (`exam_id`, `score`, `created_at`) | Leaderboard |
| `idx_results_user_history` (`user_id`, `created_at`) | Attempt history |

#### `users`
| Index | Optimizes |
|---|---|
| `idx_users_role` (`role`, `created_at`) | Admin user lists by role |

#### `job_posts`
| Index | Optimizes |
|---|---|
| `idx_jobs_search` FULLTEXT(`title`, `description`) | MySQL-only job search |
| `idx_jobs_source_date` (`job_source_id`, `published_at`) | Aggregator per source |
| `idx_jobs_status_date` (`status`, `published_at`) | Approved/featured feeds |

### EXPLAIN examples (MySQL)

```sql
EXPLAIN SELECT * FROM exams
WHERE status = 'published' AND category_id = 1
ORDER BY created_at DESC
LIMIT 15;
-- Expect: key = idx_exams_filter

EXPLAIN SELECT subject, COUNT(*) FROM questions
WHERE exam_id = 10 AND subject IS NOT NULL
GROUP BY subject;
-- Expect: key = idx_questions_exam_subject

EXPLAIN SELECT * FROM transactions
WHERE user_id = 5 AND status = 'success'
ORDER BY created_at DESC
LIMIT 20;
-- Expect: key = idx_transactions_user_status

EXPLAIN SELECT * FROM exam_attempts
WHERE exam_id = 10 AND status = 'completed'
ORDER BY score DESC, created_at ASC
LIMIT 50;
-- Expect: key = idx_results_leaderboard (or prefix)

EXPLAIN SELECT * FROM job_posts
WHERE status = 'approved'
ORDER BY published_at DESC
LIMIT 20;
-- Expect: key = idx_jobs_status_date
```

### Rollback

```bash
php artisan migrate:rollback --step=1
```

Drops only the named indexes from this migration.

---

## Application caching

| Area | Approach |
|------|----------|
| Production caches | `config:cache`, `route:cache`, `view:cache`, `event:cache`, `optimize` (via `deploy.sh`) |
| Redis | `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis` |
| Feature flags | `FeatureFlagService` — 1h cache + invalidate on admin change |
| Search suggestions | `Cache::remember` (~1h) in `SearchSuggestionController` |
| Eager loading | Repositories/controllers use `with(...)` to avoid N+1 on exams, jobs, tickets, audit |

Avoid `php artisan cache:clear` in a loop on production; prefer targeted `Cache::forget` / flag invalidation.

---

## Queues & Horizon

| Queue | Workload |
|-------|----------|
| `default` | Mail, backups, general jobs |
| `crawlers` | Job aggregation / crawl (long timeout) |

```bash
php artisan horizon
# or: php artisan queue:work --queue=crawlers,default --timeout=150
```

After deploy: `queue:restart` + `horizon:terminate` (systemd/supervisor respawns). Watch Failed Jobs in `/horizon` — see [MONITORING_CHECKLIST.md](./MONITORING_CHECKLIST.md).

---

## Telescope & slow queries

When `TELESCOPE_ENABLED=true`:

- Path: `/telescope` → Queries
- QueryWatcher `slow` threshold: **100ms** (`config/telescope.php`)
- Disable or restrict in high-traffic production if storage grows; keep for staging always-on

Pair with Sentry performance traces (`SENTRY_*` / `VITE_SENTRY_DSN`) for end-user latency.

---

## Frontend / PWA

| Item | Notes |
|------|-------|
| Build | `npm run build` (Vite 6 + `vite-plugin-pwa`) |
| Assets | Hashed under `public/build`; SW + workbox + `manifest.webmanifest` |
| Offline | `public/offline.html` + precache |
| Icons | `npm run icons:generate` → `public/icons/*` |
| Lighthouse | Run on HTTPS staging/prod; target PWA score 100 |

Prefer route-level code splitting already provided by Vue Router lazy imports; keep admin SPA chunk separate (`resources/js/admin`).

---

## HTTP / edge

| Layer | Recommendation |
|-------|----------------|
| Cloudflare | Cache static `/build/*`; bypass `/api/*`, `/horizon`, `/telescope` |
| Rate limiting | Keep CF rules aligned with API auth traffic (see monitoring checklist) |
| CSP | `SecurityHeaders` middleware; reports → `storage/logs/csp-violations.log` |
| Trust Proxies | Correct client IP for rate limits / audit (Cloudflare CIDRs only) |

---

## Health & capacity signals

```bash
curl -fsS "$APP_URL/health"
# checks: database, redis, storage, queue
```

Investigate if:

- `/health` reports non-ok redis/queue
- Telescope shows sustained >100ms queries on hot paths (exam list, wallet history, job feed)
- Horizon pending depth grows without active workers
- Sentry transaction p95 climbs after a release

---

## Checklist after schema or hot-path changes

1. Add/adjust composite indexes in a new migration (prefer `INPLACE` on MySQL)
2. Update this doc’s tables + EXPLAIN examples
3. Run `EXPLAIN` on staging with production-like data volume
4. Confirm no N+1 via Telescope Requests/Queries
5. Re-run unit/feature tests + a Lighthouse pass on critical views
