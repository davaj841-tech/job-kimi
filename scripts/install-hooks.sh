#!/usr/bin/env bash
# Install JobAzmoon git hooks (Conventional Commits enforcement).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "❌ Not a git repository." >&2
  exit 1
fi

mkdir -p .githooks

# Prefer chmod when available (Git Bash / macOS / Linux)
chmod +x .githooks/commit-msg .githooks/pre-commit 2>/dev/null || true
chmod +x scripts/*.sh 2>/dev/null || true

# Ensure executable bit in git index on Windows too
git update-index --chmod=+x .githooks/commit-msg 2>/dev/null || true
git update-index --chmod=+x .githooks/pre-commit 2>/dev/null || true

git config core.hooksPath .githooks

echo "✅ Git hooks installed. Conventional commits enforced."
echo "   core.hooksPath=$(git config core.hooksPath)"
