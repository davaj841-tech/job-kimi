<?php

namespace App\Enums\Aggregation;

enum JobSourceQualityStatus: string
{
    case Active = 'active';
    case Limited = 'limited';
    case TemporarilyUnavailable = 'temporarily_unavailable';
    case ManualOnly = 'manual_only';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Limited => 'محدود',
            self::TemporarilyUnavailable => 'موقتاً در دسترس نیست',
            self::ManualOnly => 'فقط دستی',
        };
    }

    /**
     * Sources that should not be auto-dispatched by the scheduler.
     */
    public function allowsAutomaticCrawl(): bool
    {
        return match ($this) {
            self::Active, self::Limited => true,
            self::TemporarilyUnavailable, self::ManualOnly => false,
        };
    }
}
