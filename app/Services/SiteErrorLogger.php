<?php

namespace App\Services;

use App\Models\SiteError;
use Illuminate\Support\Str;
use Throwable;

class SiteErrorLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function report(Throwable $e, array $context = []): void
    {
        try {
            if ($this->shouldIgnore($e)) {
                return;
            }

            $message = Str::limit($e->getMessage() ?: class_basename($e), 900);
            $file = Str::limit((string) $e->getFile(), 480);
            $line = (int) $e->getLine();
            $class = class_basename($e);

            $existing = SiteError::query()
                ->whereNull('resolved_at')
                ->where('exception_class', $class)
                ->where('message', $message)
                ->where('file', $file)
                ->where('line', $line)
                ->first();

            if ($existing) {
                $existing->update([
                    'occurrences' => $existing->occurrences + 1,
                    'last_seen_at' => now(),
                    'url' => $context['url'] ?? $existing->url,
                    'method' => $context['method'] ?? $existing->method,
                    'user_id' => $context['user_id'] ?? $existing->user_id,
                ]);

                return;
            }

            SiteError::query()->create([
                'level' => 'error',
                'message' => $message,
                'message_fa' => $this->translate($e, $message),
                'exception_class' => $class,
                'file' => $file,
                'line' => $line,
                'url' => isset($context['url']) ? Str::limit((string) $context['url'], 900) : null,
                'method' => $context['method'] ?? null,
                'user_id' => $context['user_id'] ?? null,
                'trace' => Str::limit($e->getTraceAsString(), 5000),
                'context' => $context ?: null,
                'occurrences' => 1,
                'last_seen_at' => now(),
            ]);
        } catch (Throwable) {
            // never break the app because of logging
        }
    }

    protected function shouldIgnore(Throwable $e): bool
    {
        $hay = $e->getMessage().' '.$e->getFile();

        return str_contains($hay, 'telescope_entries')
            || str_contains($hay, 'vendor'.DIRECTORY_SEPARATOR.'psy'.DIRECTORY_SEPARATOR.'psysh')
            || str_contains($hay, 'ParseErrorException')
            || str_contains($hay, 'The "--columns" option does not exist')
            || (stripos($e->getMessage(), 'Maximum execution time') !== false
                && str_contains($e->getFile(), 'ClassLoader.php'));
    }

    protected function translate(Throwable $e, string $message): string
    {
        $map = [
            'SQLSTATE' => 'خطای پایگاه داده رخ داده است.',
            'no such table' => 'جدول مورد نظر در پایگاه داده یافت نشد.',
            'no such column' => 'ستون مورد نظر در پایگاه داده یافت نشد.',
            'no such function' => 'تابع پایگاه داده در این موتور پشتیبانی نمی‌شود.',
            'Connection refused' => 'اتصال به سرویس برقرار نشد.',
            'Unauthenticated' => 'کاربر احراز هویت نشده است.',
            'Unauthorized' => 'دسترسی غیرمجاز است.',
            'Too Many Attempts' => 'تعداد تلاش‌ها بیش از حد مجاز است.',
            'CSRF' => 'توکن امنیتی منقضی شده است. صفحه را تازه کنید.',
            'file_get_contents' => 'خواندن فایل با خطا مواجه شد.',
            'Permission denied' => 'دسترسی به فایل یا مسیر مجاز نیست.',
            'Class "' => 'کلاس مورد نظر یافت نشد.',
            'Call to undefined' => 'فراخوانی متد یا تابع تعریف‌نشده.',
            'syntax error' => 'خطای نحوی در کد یا کوئری وجود دارد.',
            'Maximum execution time' => 'زمان اجرای درخواست به پایان رسید.',
            'Allowed memory size' => 'حافظه مجاز سرور تمام شد.',
            'cURL error' => 'خطا در ارتباط شبکه‌ای با سرویس خارجی.',
            'SSL' => 'خطای گواهی یا ارتباط امن SSL.',
        ];

        foreach ($map as $needle => $fa) {
            if (stripos($message, $needle) !== false || stripos(class_basename($e), $needle) !== false) {
                return $fa.' ('.$message.')';
            }
        }

        return 'خطای سیستمی: '.$message;
    }
}
