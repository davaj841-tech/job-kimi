<?php

namespace App\Support;

use App\Models\User;

final class OperatorPermissions
{
    /** @return array<string, string> */
    public static function catalog(): array
    {
        return [
            'users' => 'کاربران',
            'tickets' => 'تیکت‌ها',
            'exams' => 'آزمون‌ها',
            'questions' => 'سوالات',
            'blog' => 'بلاگ',
            'generated_contents' => 'تولید محتوا',
            'pdf' => 'فروشگاه فایل',
            'banners' => 'بنرها',
            'pages' => 'صفحات',
            'ai' => 'هوش مصنوعی',
            'job_posts' => 'آگهی‌ها',
            'aggregation' => 'تجمیع آگهی',
            'subscriptions' => 'اشتراک‌ها',
            'transactions' => 'تراکنش‌ها',
            'coupons' => 'کد تخفیف',
            'wallets' => 'کیف پول‌ها',
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    /** @return list<string> */
    public static function defaults(): array
    {
        return ['exams', 'questions', 'blog', 'job_posts', 'tickets'];
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    public static function normalize(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return self::defaults();
        }

        $allowed = self::keys();

        return array_values(array_unique(array_filter(
            array_map('strval', $value),
            fn (string $key) => in_array($key, $allowed, true)
        )));
    }

    public static function allows(?User $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'operator') {
            return false;
        }

        return in_array($permission, self::normalize($user->operator_permissions), true);
    }

    public static function permissionForPath(string $path): ?string
    {
        $path = ltrim($path, '/');
        $path = preg_replace('#^api/v1/#', '', $path) ?? $path;
        $path = preg_replace('#^admin/#', '', $path) ?? $path;

        $adminOnly = ['settings', 'backups', 'audit-logs', 'site-errors'];
        foreach ($adminOnly as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return '__admin__';
            }
        }

        $map = [
            'users' => 'users',
            'tickets' => 'tickets',
            'contact-messages' => 'tickets',
            'exams' => 'exams',
            'questions' => 'questions',
            'blog-posts' => 'blog',
            'generated-contents' => 'generated_contents',
            'pdf-products' => 'pdf',
            'banners' => 'banners',
            'pages' => 'pages',
            'ai' => 'ai',
            'job-posts' => 'job_posts',
            'job-sources' => 'aggregation',
            'aggregation-settings' => 'aggregation',
            'crawl-monitoring' => 'aggregation',
            'aggregated-jobs' => 'aggregation',
            'subscriptions' => 'subscriptions',
            'transactions' => 'transactions',
            'coupons' => 'coupons',
            'wallets' => 'wallets',
        ];

        foreach ($map as $prefix => $permission) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $permission;
            }
        }

        return null;
    }
}
