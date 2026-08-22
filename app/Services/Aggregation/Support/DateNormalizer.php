<?php

namespace App\Services\Aggregation\Support;

use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

class DateNormalizer
{
    /**
     * Parse Gregorian or common Jalali date strings into a datetime string.
     * Returns null when unparseable — never invents dates.
     */
    public function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->toDateTimeString();
        }

        $raw = PersianText::toEnglishDigits(PersianText::normalize((string) $value));
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        $raw = str_replace(['-', '.', '،'], '/', $raw);
        $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;

        // Jalali: 1403/01/15 or 1403/1/5 or 1403/01/15 12:30
        if (preg_match('/^(13|14)\d{2}\/\d{1,2}\/\d{1,2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/u', $raw)) {
            try {
                $parts = explode(' ', $raw, 2);
                $datePart = $parts[0];
                [$jy, $jm, $jd] = array_map('intval', explode('/', $datePart));
                $j = new Jalalian($jy, $jm, $jd);
                $carbon = $j->toCarbon()->startOfDay();
                if (isset($parts[1]) && preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $parts[1], $tm)) {
                    $carbon->setTime((int) $tm[1], (int) $tm[2], isset($tm[3]) ? (int) $tm[3] : 0);
                }

                return $carbon->toDateTimeString();
            } catch (\Throwable) {
                // fall through to gregorian attempts
            }
        }

        // ISO / common gregorian
        foreach ([
            'Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s\Z', 'Y-m-d',
            'd/m/Y', 'Y/m/d', 'm/d/Y',
        ] as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $raw);
                if ($dt instanceof Carbon) {
                    // Date-only formats must not inherit the current clock time.
                    if (in_array($format, ['Y-m-d', 'd/m/Y', 'Y/m/d', 'm/d/Y'], true)) {
                        $dt = $dt->startOfDay();
                    }

                    return $dt->toDateTimeString();
                }
            } catch (\Throwable) {
            }
        }

        try {
            // Avoid Carbon::parse on ambiguous pure numbers
            if (preg_match('/^\d{9,}$/', $raw)) {
                return null;
            }

            // Pure date Y-m-d already handled; for date-looking strings force startOfDay when no time present.
            $parsed = Carbon::parse($raw);
            if (preg_match('/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $raw)) {
                $parsed = $parsed->startOfDay();
            }

            return $parsed->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
