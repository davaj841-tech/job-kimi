#!/usr/bin/env bash
# JobAzmoon pre-deploy gate — run on the build machine before packaging for cPanel.
# Usage: bash scripts/pre-deploy-check.sh
# Exit 0 = all required checks PASS; non-zero = FAIL (do not ship).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PASS=0
FAIL=0
WARN=0

pass() { echo "PASS  $1"; PASS=$((PASS + 1)); }
fail() { echo "FAIL  $1"; FAIL=$((FAIL + 1)); }
warn() { echo "WARN  $1"; WARN=$((WARN + 1)); }

echo "=== JobAzmoon pre-deploy check ==="
echo "Root: $ROOT"
echo

# --- PHP / Laravel ---
if command -v php >/dev/null 2>&1; then
  PHP_VER="$(php -r 'echo PHP_VERSION;')"
  if php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
    pass "PHP >= 8.2 ($PHP_VER)"
  else
    fail "PHP >= 8.2 required (found $PHP_VER)"
  fi
else
  fail "PHP binary missing"
fi

for ext in openssl pdo mbstring tokenizer xml curl fileinfo zip; do
  if php -m 2>/dev/null | grep -qi "^${ext}$"; then
    pass "PHP extension: $ext"
  else
    fail "PHP extension missing: $ext"
  fi
done

if [[ -f artisan ]]; then
  pass "artisan present"
else
  fail "artisan missing"
fi

if [[ -f bootstrap/app.php ]]; then
  pass "bootstrap/app.php present"
else
  fail "bootstrap/app.php missing"
fi

if [[ -f routes/install.php ]]; then
  pass "routes/install.php present (cPanel boot)"
else
  fail "routes/install.php missing"
fi

# --- Env examples ---
if [[ -f .env.example ]]; then
  pass ".env.example present"
  if grep -qE '^APP_KEY=' .env.example; then pass ".env.example has APP_KEY"; else fail ".env.example missing APP_KEY"; fi
  if grep -qE '^APP_URL=' .env.example; then pass ".env.example has APP_URL"; else fail ".env.example missing APP_URL"; fi
  if grep -qE '^DB_CONNECTION=' .env.example; then pass ".env.example has DB_*"; else fail ".env.example missing DB_CONNECTION"; fi
  if grep -qE '^QUEUE_CONNECTION=' .env.example; then pass ".env.example has QUEUE_CONNECTION"; else fail ".env.example missing QUEUE_CONNECTION"; fi
  if grep -qE '^CACHE_STORE=' .env.example; then pass ".env.example has CACHE_STORE"; else fail ".env.example missing CACHE_STORE"; fi
  if grep -qE '^SESSION_DRIVER=' .env.example; then pass ".env.example has SESSION_DRIVER"; else fail ".env.example missing SESSION_DRIVER"; fi
  if grep -qiE '^[[:space:]]*MAIL_HOST=.*smtp\.example\.com' .env.example; then
    fail ".env.example still contains smtp.example.com"
  else
    pass ".env.example has no smtp.example.com"
  fi
else
  fail ".env.example missing"
fi

if [[ -f .env.production.example ]]; then
  if grep -qiE '^[[:space:]]*MAIL_HOST=.*smtp\.example\.com' .env.production.example; then
    fail ".env.production.example still contains smtp.example.com"
  else
    pass ".env.production.example has no smtp.example.com"
  fi
  if grep -qE '^MAIL_SCHEME=' .env.production.example; then
    pass ".env.production.example has MAIL_SCHEME (Laravel 12)"
  else
    warn ".env.production.example missing MAIL_SCHEME"
  fi
else
  warn ".env.production.example missing"
fi

# --- Permissions / storage ---
for d in storage bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs; do
  if [[ -d "$d" ]] || mkdir -p "$d" 2>/dev/null; then
    if [[ -w "$d" ]]; then
      pass "writable: $d"
    else
      fail "not writable: $d"
    fi
  else
    fail "cannot create: $d"
  fi
done

# --- Composer ---
if command -v composer >/dev/null 2>&1; then
  if composer validate --no-check-publish --quiet; then
    pass "composer.json valid"
  else
    fail "composer.json invalid"
  fi
  if [[ -f vendor/autoload.php ]]; then
    pass "vendor/autoload.php present"
  else
    fail "vendor/autoload.php missing — run composer install"
  fi
  if grep -Rni --include='composer.json' '"ext-pcntl"\|"ext-posix"' composer.json >/dev/null 2>&1; then
    warn "composer.json mentions ext-pcntl/posix — package build must use --ignore-platform-req"
  else
    pass "no hard ext-pcntl/posix in root composer.json require"
  fi
else
  warn "composer CLI missing (OK if vendor already vendored for package)"
fi

# --- Artisan commands ---
if [[ -f vendor/autoload.php ]]; then
  if php artisan list --raw 2>/dev/null | grep -q '^mail:test'; then
    pass "artisan mail:test registered"
  else
    fail "artisan mail:test not listed"
  fi
  if php artisan list --raw 2>/dev/null | grep -q '^sms:'; then
    pass "artisan sms:* commands present"
  else
    warn "no sms:* artisan commands listed"
  fi
else
  warn "skip artisan list (no vendor)"
fi

# --- Frontend build ---
if [[ -f public/build/manifest.json ]]; then
  if php -r '$j=json_decode(@file_get_contents("public/build/manifest.json"), true); exit(is_array($j)&&$j!==[]?0:1);'; then
    pass "public/build/manifest.json valid non-empty"
  else
    fail "public/build/manifest.json empty or invalid JSON"
  fi
else
  fail "public/build/manifest.json missing — run npm run build"
fi

# --- Mail config surface ---
if [[ -f config/mail.php ]] && grep -q "scheme" config/mail.php; then
  pass "config/mail.php uses MAIL_SCHEME (Laravel 12)"
else
  fail "config/mail.php missing scheme support"
fi

# --- Installer ---
if [[ -f cpanel-installer/install.php && -f cpanel-installer/lib/InstallEngine.php ]]; then
  pass "cPanel installer sources present"
  if php -l cpanel-installer/install.php >/dev/null 2>&1; then
    pass "install.php PHP syntax"
  else
    fail "install.php PHP syntax error"
  fi
  if php -l cpanel-installer/lib/InstallEngine.php >/dev/null 2>&1; then
    pass "InstallEngine.php PHP syntax"
  else
    fail "InstallEngine.php PHP syntax error"
  fi
else
  fail "cPanel installer sources missing"
fi

# --- Optional DB ping (only if .env exists and DB reachable) ---
if [[ -f .env && -f vendor/autoload.php ]]; then
  if php artisan db:show --json >/dev/null 2>&1; then
    pass "database connection (artisan db:show)"
  else
    warn "database not reachable from this environment (OK for package-only build)"
  fi
else
  warn "skip database check (no .env or vendor)"
fi

echo
echo "=== Summary: PASS=$PASS  FAIL=$FAIL  WARN=$WARN ==="
if [[ "$FAIL" -gt 0 ]]; then
  echo "RESULT: FAIL — do not create production ZIP until failures are fixed."
  exit 1
fi
echo "RESULT: PASS — safe to run: php scripts/build-cpanel-package.php"
exit 0
