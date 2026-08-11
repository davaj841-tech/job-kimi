# GitHub: Branch protection & merge policy

## Applied in-repo

- PR template: [`.github/PULL_REQUEST_TEMPLATE.md`](../.github/PULL_REQUEST_TEMPLATE.md)

## Apply on GitHub (one-time, needs admin + `gh auth login`)

```bash
# Install GitHub CLI, then:
gh auth login
bash scripts/setup-github-protection.sh
```

What the script sets:

| Setting | Value |
|---------|--------|
| Squash merge | Enabled (preferred) |
| Merge commit / Rebase | Disabled |
| Delete branch on merge | Enabled |
| Required approving reviews | **1** |
| Required status checks | `PHP / Laravel`, `Vite Build` (CI green) |
| Linear history | Required |
| Force push / delete branch | Blocked on `main` |

### Manual UI (if `gh` unavailable)

1. **Settings → General → Pull Requests**
   - Allow squash merging ✅
   - Allow merge commits ❌
   - Allow rebase merging ❌
   - Automatically delete head branches ✅

2. **Settings → Branches → Add rule** for `main`
   - Require a pull request before merging ✅
   - Required approvals: **1**
   - Require status checks to pass ✅ → select `PHP / Laravel` and `Vite Build`
   - Require branches to be up to date ✅
   - Require conversation resolution ✅
   - Do not allow bypassing (admins) ✅ if available on your plan
