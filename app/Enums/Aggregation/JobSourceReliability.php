<?php

namespace App\Enums\Aggregation;

enum JobSourceReliability: string
{
    case Official = 'official';
    case HighlyTrusted = 'highly_trusted';
    case Trusted = 'trusted';
    case Unverified = 'unverified';

    public function label(): string
    {
        return match ($this) {
            self::Official => 'رسمی',
            self::HighlyTrusted => 'بسیار معتبر',
            self::Trusted => 'معتبر',
            self::Unverified => 'تأییدنشده',
        };
    }

    /** Eligible for eventual auto-publication (admin policy). */
    public function allowsAutoPublish(): bool
    {
        return match ($this) {
            self::Official, self::HighlyTrusted => true,
            self::Trusted, self::Unverified => false,
        };
    }

    public function sortWeight(): int
    {
        return match ($this) {
            self::Official => 1,
            self::HighlyTrusted => 2,
            self::Trusted => 3,
            self::Unverified => 4,
        };
    }
}
