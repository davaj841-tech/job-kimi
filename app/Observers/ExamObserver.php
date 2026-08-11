<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Exam;

final class ExamObserver
{
    public function creating(Exam $exam): void
    {
        if (filled($exam->slug)) {
            $exam->slug = $exam->resolveSlugCollision((string) $exam->slug);

            return;
        }

        $exam->slug = $exam->generateSlug((string) ($exam->title ?? ''));
    }
}
