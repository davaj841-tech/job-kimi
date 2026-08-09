<?php

namespace App\Enums\Aggregation;

enum CrawlerRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در صف',
            self::Running => 'در حال اجرا',
            self::Completed => 'موفق',
            self::Failed => 'ناموفق',
            self::Partial => 'ناقص',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Partial], true);
    }
}
