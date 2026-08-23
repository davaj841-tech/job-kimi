#!/usr/bin/env bash
# Interactive release helper for JobAzmoon (semver + CHANGELOG + tag).
# Usage: ./scripts/release.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

red() { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }
info() { printf '\033[36m%s\033[0m\n' "$*"; }

die() {
  red "Error: $*"
  exit 1
}

command -v git >/dev/null || die "git is required"
command -v php >/dev/null || die "php is required"
command -v npm >/dev/null || die "npm is required"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "not a git repository"

BRANCH="$(git branch --show-current)"
[[ "$BRANCH" == "main" || "$BRANCH" == "master" ]] || die "must be on main (current: $BRANCH)"

if [[ -n "$(git status --porcelain)" ]]; then
  die "working directory is not clean; commit or stash first"
fi

info "Running php artisan test..."
php artisan test || die "php artisan test failed"

info "Running npm run test:unit..."
npm run test:unit || die "npm run test:unit failed"

info "Running PHPStan..."
./vendor/bin/phpstan analyse --memory-limit=1G || die "phpstan failed"

info "Running Pint..."
./vendor/bin/pint --test || die "pint --test failed"

echo ""
read -r -p "New version (e.g. 1.5.0, without v): " VERSION
VERSION="${VERSION#v}"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][A-Za-z0-9.]+)?$ ]] || die "invalid semver: $VERSION"

TAG="v${VERSION}"
if git rev-parse "$TAG" >/dev/null 2>&1; then
  die "tag $TAG already exists"
fi

# Jalali date helper (approximate via PHP if available + morilog, else Gregorian ISO + note)
JALALI="$(php -r "
require 'vendor/autoload.php';
if (class_exists('Morilog\\\\Jalali\\\\Jalalian')) {
  echo Morilog\\\\Jalali\\\\Jalalian::now()->format('Y-m-d');
} else {
  echo date('Y-m-d');
}
" 2>/dev/null || date '+%Y-%m-%d')"

CHANGELOG="CHANGELOG.md"
[[ -f "$CHANGELOG" ]] || die "CHANGELOG.md missing"

if ! grep -q '^## \[Unreleased\]' "$CHANGELOG"; then
  die "CHANGELOG.md has no ## [Unreleased] section"
fi

if grep -q "^## \[${VERSION}\]" "$CHANGELOG"; then
  die "CHANGELOG already has section [${VERSION}]"
fi

TMP="$(mktemp)"
awk -v ver="$VERSION" -v jdate="$JALALI" '
  BEGIN { done=0 }
  /^## \[Unreleased\]/ && !done {
    print "## [Unreleased]"
    print ""
    print "### Added"
    print ""
    print "### Changed"
    print ""
    print "### Fixed"
    print ""
    print "### Security"
    print ""
    print "## [" ver "] - " jdate
    done=1
    next
  }
  { print }
' "$CHANGELOG" > "$TMP"
mv "$TMP" "$CHANGELOG"

# Update compare links at bottom if present
if grep -q '^\[Unreleased\]:' "$CHANGELOG"; then
  # Leave link maintenance to maintainers; ensure Unreleased points to new tag
  :
fi

git add CHANGELOG.md
git commit -m "chore(release): bump version to ${VERSION}"
git tag -a "$TAG" -m "Release ${TAG}"

echo ""
info "Created commit + tag ${TAG}"
read -r -p "Push main and ${TAG} to origin? [y/N] " PUSH
case "$PUSH" in
  y|Y|yes|YES)
    git push origin HEAD
    git push origin "$TAG"
    green "Pushed. GitHub Actions Release workflow should create the Release."
    ;;
  *)
    info "Skipped push. Run manually:"
    echo "  git push origin HEAD && git push origin ${TAG}"
    ;;
esac

green "Done: ${TAG}"
