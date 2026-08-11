#!/usr/bin/env bash
# Configure GitHub repo: squash-merge + branch protection on main.
# Requires: gh auth login (admin on davaj841-tech/job-kimi)
set -euo pipefail

REPO="${GITHUB_REPOSITORY:-davaj841-tech/job-kimi}"
BRANCH="${DEFAULT_BRANCH:-main}"

echo "Repo: $REPO  Branch: $BRANCH"

# Prefer squash merges for a clean history
gh repo edit "$REPO" \
  --enable-squash-merge \
  --enable-auto-merge \
  --delete-branch-on-merge \
  --allow-update-branch \
  --disable-merge-commit \
  --disable-rebase-merge

# Branch protection: 1 approving review + green CI
# Required checks must match workflow job names in .github/workflows/ci.yml
gh api \
  --method PUT \
  -H "Accept: application/vnd.github+json" \
  "/repos/${REPO}/branches/${BRANCH}/protection" \
  --input - <<'EOF'
{
  "required_status_checks": {
    "strict": true,
    "contexts": [
      "PHP / Laravel",
      "Vite Build"
    ]
  },
  "enforce_admins": true,
  "required_pull_request_reviews": {
    "dismiss_stale_reviews": true,
    "require_code_owner_reviews": false,
    "required_approving_review_count": 1
  },
  "restrictions": null,
  "required_linear_history": true,
  "allow_force_pushes": false,
  "allow_deletions": false,
  "block_creations": false,
  "required_conversation_resolution": true
}
EOF

echo "Done."
echo "Verify: https://github.com/${REPO}/settings/branches"
echo "Merge settings: https://github.com/${REPO}/settings"
