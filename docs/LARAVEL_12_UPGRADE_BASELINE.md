# Laravel 12 Upgrade — Baseline (Phase 1)

Branch: `upgrade/laravel-12` (created from `main` with existing working-tree changes preserved)

## Environment

| Item | Value |
|------|--------|
| PHP | 8.3.30 |
| Laravel | 11.55.1 |
| Composer | 2.10.2 |
| Node | v22.22.0 |
| npm | 10.9.4 |

## Tests / build (pre-upgrade)

| Check | Status |
|-------|--------|
| Listed tests | ~613 |
| Last full suite (prior session) | 610 passed, 1 skipped |
| `public/build/manifest.json` | present |
| `public/build/sw.js` | present |

## Security audit (pre-upgrade)

`laravel/framework` 11.55.1 has advisories requiring patches only available on Laravel **12.60.0+ / 12.61.1+**:

- Temporary Signed URL Path Confusion → fixed in `>=12.61.1`
- CRLF injection in default email rule → fixed in `>=12.60.0`

## Notes

- Working tree already had cPanel/deployment docs and PSR-4 OpenApiSpec fix; not discarded.
- `config/filesystems.php` already sets `local` root to `storage/app/private` (Laravel 12 default).
- No `HasUuids` / `@context` usages found that need special migration.
