<?php

declare(strict_types=1);

namespace App\Services\Update;

final class UpdatePathGuard
{
    public function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        while (str_contains($path, '//')) {
            $path = str_replace('//', '/', $path);
        }

        return $path;
    }

    public function isSafeRelative(string $path): bool
    {
        $path = $this->normalize($path);
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
            return false;
        }
        if (preg_match('#(^|/)\.\.(/|$)#', $path)) {
            return false;
        }
        if (preg_match('#^[a-zA-Z]:#', $path)) {
            return false;
        }

        return true;
    }

    public function isProtected(string $path): bool
    {
        $path = $this->normalize($path);
        /** @var list<string> $protected */
        $protected = config('update.protected_paths', []);

        foreach ($protected as $rule) {
            $rule = $this->normalize((string) $rule);
            if ($path === $rule || str_starts_with($path, rtrim($rule, '/').'/')) {
                return true;
            }
            // .env.* family
            if ($rule === '.env' && (str_starts_with($path, '.env.') || $path === '.env')) {
                return true;
            }
        }

        return false;
    }

    public function isAllowedRoot(string $path): bool
    {
        $path = $this->normalize($path);
        /** @var list<string> $roots */
        $roots = config('update.allowed_roots', []);

        foreach ($roots as $root) {
            $root = $this->normalize((string) $root);
            if ($path === $root || str_starts_with($path, rtrim($root, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    public function assertWritableTarget(string $relative): void
    {
        $relative = $this->normalize($relative);
        if (! $this->isSafeRelative($relative)) {
            throw new \RuntimeException("مسیر ناامن در بسته به‌روزرسانی: {$relative}");
        }
        if ($this->isProtected($relative)) {
            throw new \RuntimeException("تلاش برای تغییر فایل محافظت‌شده: {$relative}");
        }
        if (! $this->isAllowedRoot($relative)) {
            throw new \RuntimeException("مسیر خارج از اجازه به‌روزرسانی: {$relative}");
        }

        $absolute = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $realBase = realpath(base_path());
        if ($realBase === false) {
            throw new \RuntimeException('مسیر پایه پروژه قابل تشخیص نیست.');
        }

        $parent = dirname($absolute);
        if (! is_dir($parent)) {
            // parent may not exist yet; validate against intended base
            $intended = $realBase.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! str_starts_with($this->normalizeFs($intended), $this->normalizeFs($realBase).DIRECTORY_SEPARATOR)
                && $this->normalizeFs($intended) !== $this->normalizeFs($realBase)) {
                throw new \RuntimeException("خروج از ریشه پروژه: {$relative}");
            }

            return;
        }

        $realParent = realpath($parent);
        if ($realParent === false || ! str_starts_with($this->normalizeFs($realParent), $this->normalizeFs($realBase))) {
            throw new \RuntimeException("خروج از ریشه پروژه: {$relative}");
        }
    }

    private function normalizeFs(string $path): string
    {
        return strtolower(rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR));
    }
}
