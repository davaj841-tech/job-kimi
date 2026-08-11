<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\User;
use App\Repositories\ExamRepository;

final class ExamSubjectAssembler
{
    public function __construct(
        private readonly ExamRepository $examRepository,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function assemble(Exam $exam, ?User $user = null): array
    {
        $countsBySubject = $this->examRepository->questionCountsBySubject($exam);

        if ($countsBySubject->isEmpty()) {
            return [];
        }

        /** @var list<string> $subjectSlugs */
        $subjectSlugs = $countsBySubject->keys()->values()->all();

        $defaultIcon = (string) config('exam.subjects.default_icon', '📘');

        $known = ExamSubject::query()
            ->whereIn('slug', $subjectSlugs)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'icon', 'sort_order']);

        $assembled = [];

        foreach ($known as $subject) {
            $assembled[] = [
                'id' => $subject->id,
                'name' => $subject->name,
                'slug' => $subject->slug,
                'icon' => $subject->icon ?: $defaultIcon,
                'question_count' => (int) ($countsBySubject[$subject->slug] ?? 0),
            ];
        }

        $knownSlugs = $known->pluck('slug')->all();

        foreach ($subjectSlugs as $slug) {
            if (in_array($slug, $knownSlugs, true)) {
                continue;
            }

            $assembled[] = [
                'id' => null,
                'name' => $slug,
                'slug' => $slug,
                'icon' => $defaultIcon,
                'question_count' => (int) ($countsBySubject[$slug] ?? 0),
            ];
        }

        return $assembled;
    }
}
