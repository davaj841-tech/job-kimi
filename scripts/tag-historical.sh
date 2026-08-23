#!/usr/bin/env bash
# Create historical annotated tags for CHANGELOG versions (v1.0.0 … v1.4.0).
# Safe to re-run: skips tags that already exist.
# Usage: ./scripts/tag-historical.sh [--push]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PUSH=0
if [[ "${1:-}" == "--push" ]]; then
  PUSH=1
fi

declare -a TAGS=(
  "v1.0.0|Release v1.0.0 - Initial launch"
  "v1.1.0|Release v1.1.0 - PDF store & wallet"
  "v1.2.0|Release v1.2.0 - Resume builder with AI"
  "v1.3.0|Release v1.3.0 - Feature flags & Filament"
  "v1.4.0|Release v1.4.0 - PWA complete"
)

TARGET_COMMIT="$(git rev-parse HEAD)"

for entry in "${TAGS[@]}"; do
  TAG="${entry%%|*}"
  MSG="${entry#*|}"
  if git rev-parse "$TAG" >/dev/null 2>&1; then
    echo "skip (exists): $TAG"
    continue
  fi
  git tag -a "$TAG" -m "$MSG" "$TARGET_COMMIT"
  echo "created: $TAG"
done

if [[ "$PUSH" -eq 1 ]]; then
  git push origin --tags
  echo "pushed tags to origin"
else
  echo ""
  echo "Local tags ready. Push with:"
  echo "  git push origin --tags"
  echo "or: ./scripts/tag-historical.sh --push"
fi
