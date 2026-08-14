<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\User;
use RuntimeException;

final class ExamObserver
{
    public function creating(Exam $exam): void
    {
        if (blank($exam->category_id)) {
            $exam->category_id = ExamCategory::query()->value('id')
                ?? ExamCategory::query()->create([
                    'name' => 'عمومی',
                    'slug' => 'general',
                ])->id;
        }

        if (blank($exam->created_by)) {
            $exam->created_by = auth()->id()
                ?? User::query()->where('role', 'admin')->orderBy('id')->value('id')
                ?? User::query()->orderBy('id')->value('id');
        }

        if (blank($exam->created_by)) {
            throw new RuntimeException('برای ایجاد آزمون باید حداقل یک کاربر در سیستم وجود داشته باشد.');
        }

        if (filled($exam->slug)) {
            $exam->slug = $exam->resolveSlugCollision((string) $exam->slug);

            return;
        }

        $exam->slug = $exam->generateSlug((string) ($exam->title ?? ''));
    }
}
