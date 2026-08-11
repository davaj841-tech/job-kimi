<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Models\Exam;
use Illuminate\Support\Str;

final class ExamSlugGenerator
{
    public function generate(string $title, ?string $existingSlug = null): string
    {
        if (is_string($existingSlug) && ! blank($existingSlug)) {
            return $existingSlug;
        }

        $base = Str::slug($title);

        if ($base === '') {
            $base = (string) config('exam.slug.fallback_prefix', 'exam');
        }

        $suffixLength = max(1, (int) config('exam.slug.random_suffix_length', 5));
        $candidate = $base.'-'.Str::random($suffixLength);

        return $this->ensureUnique($candidate);
    }

    public function ensureUnique(string $slug): string
    {
        $unique = $slug;
        $attempts = 0;

        while (Exam::query()->where('slug', $unique)->exists()) {
            $attempts++;
            $suffixLength = max(1, (int) config('exam.slug.random_suffix_length', 5));
            $unique = $slug.'-'.Str::random($suffixLength);

            if ($attempts >= 10) {
                $unique = $slug.'-'.Str::lower(Str::random(8));
                break;
            }
        }

        return $unique;
    }
}
