#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "🔍 Checking repository health..."
echo ""

# Check if hooks are installed
if [ "$(git config core.hooksPath 2>/dev/null || true)" = ".githooks" ]; then
  echo "✅ Git hooks installed"
else
  echo "❌ Git hooks NOT installed. Run: ./scripts/install-hooks.sh"
fi

# Check if tags exist locally
TAG_COUNT="$(git tag 2>/dev/null | wc -l | tr -d ' ')"
if [ "${TAG_COUNT:-0}" -gt 0 ]; then
  echo "✅ Found ${TAG_COUNT} tags locally"
else
  echo "❌ No tags found. Run: ./scripts/tag-historical.sh --push"
fi

# Check if tags are pushed
REMOTE_TAGS="$(git ls-remote --tags origin 2>/dev/null | wc -l | tr -d ' ' || echo 0)"
if [ "${REMOTE_TAGS:-0}" -gt 0 ]; then
  echo "✅ Tags pushed to remote (${REMOTE_TAGS} refs)"
else
  echo "❌ Tags NOT pushed to remote. Run: ./scripts/tag-historical.sh --push"
fi

echo ""
echo "🧪 Running CI checks..."
php artisan test || echo "⚠️  Tests failed"
./vendor/bin/phpstan analyse --memory-limit=1G || echo "⚠️  PHPStan failed"
./vendor/bin/pint --test || echo "⚠️  Pint failed"

echo ""
echo "🏁 Health check complete."
