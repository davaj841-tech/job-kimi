#!/usr/bin/env bash
# Interactive Conventional Commits helper for JobAzmoon.
# Usage: ./scripts/commit.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo ""
echo "📝 JobAzmoon Commit Helper"
echo "=========================="
echo ""
echo "⚠️  NEVER use: git commit -m '14050601' or 'ok'"
echo "✅  ALWAYS use this script or proper conventional commit format"
echo ""

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Error: not a git repository." >&2
  exit 1
fi

if git diff --cached --quiet; then
  echo "Nothing staged. Stage files first: git add -p"
  exit 1
fi

TYPES=(feat fix docs style refactor test chore perf security ci build revert)
SCOPES=(exam payment wallet auth api admin crawler seo test ci docs pwa installer repo)

echo ""
echo "=== JobAzmoon Conventional Commit ==="
echo ""

echo "Select type:"
PS3="Type number: "
select TYPE in "${TYPES[@]}"; do
  if [[ -n "${TYPE:-}" ]]; then
    break
  fi
  echo "Invalid selection."
done

echo ""
echo "Select scope:"
PS3="Scope number: "
select SCOPE in "${SCOPES[@]}"; do
  if [[ -n "${SCOPE:-}" ]]; then
    break
  fi
  echo "Invalid selection."
done

echo ""
read -r -p "Description (imperative English, e.g. add autosave retry): " DESC
DESC="$(echo "$DESC" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"

if [[ -z "$DESC" ]]; then
  echo "Error: description is required." >&2
  exit 1
fi

# Lowercase first letter if user capitalized
FIRST="$(printf '%s' "$DESC" | cut -c1 | tr '[:upper:]' '[:lower:]')"
REST="$(printf '%s' "$DESC" | cut -c2-)"
DESC="${FIRST}${REST}"

# Strip trailing period
DESC="${DESC%.}"

MSG="${TYPE}(${SCOPE}): ${DESC}"

echo ""
echo "Commit message:"
echo "  $MSG"
echo ""
read -r -p "Proceed? [y/N] " OK
case "$OK" in
  y|Y|yes|YES) ;;
  *) echo "Aborted."; exit 0 ;;
esac

git commit -m "$MSG"
echo "Committed: $MSG"
