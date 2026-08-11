<?php

namespace App\Enums\Content;

enum ContentStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Scheduled => 'زمان‌بندی‌شده',
            self::Published => 'منتشرشده',
            self::Failed => 'ناموفق',
            self::Skipped => 'ردشده/تکراری',
        };
    }
}
