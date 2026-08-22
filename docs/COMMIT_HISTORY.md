# تاریخچه Commit و پیام‌های قدیمی

بعضی commitهای قدیمی روی `main` پیام‌هایی مثل `14050531` یا `ok` دارند. این‌ها در تاریخچهٔ عمومی می‌مانند مگر اینکه history بازنویسی شود.

## توصیه (ایمن برای شاخهٔ مشترک)

1. **بازنویسی نکنید** تاریخچهٔ `main` را اگر دیگران از آن pull کرده‌اند (`git rebase` / `filter-repo` روی `main` باعث force-push و دردسر همگام‌سازی می‌شود).
2. از این به بعد فقط **Conventional Commits** استفاده کنید:
   - `git config commit.template .gitmessage`
   - یا `./scripts/commit.sh`
3. در PRها، **Squash and merge** را ترجیح دهید تا روی `main` یک پیام تمیز بماند.
4. برای انتشار، از `./scripts/release.sh` و تگ‌های `v*.*.*` استفاده کنید؛ Releases از CHANGELOG پر می‌شوند و وابستگی کمتری به پیام‌های قدیمی دارند.

## اگر حتماً می‌خواهید history را پاک کنید (فقط مخزن خصوصی / هماهنگی کامل تیم)

```bash
# خطرناک — نیاز به force-push و هماهنگی همهٔ همکاران
git filter-repo --message-callback '
  # یا استفاده از git rebase -i روی بازهٔ مشخص
'
git push --force-with-lease origin main
```

برای JobAzmoon عمومی، مسیر پیشنهادی همان **عدم بازنویسی + نظم از این به بعد** است.
