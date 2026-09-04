<?php

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\Question;
use App\Services\Question\QuestionAssignService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds a random question set for classification-based exams,
 * preferring frequently served (پر تکرار) questions per subject quotas.
 */
class RandomExamAssembler
{
    /**
     * @return Collection<int, Question>
     */
    public function assemble(Exam $exam, ?string $subjectFilter = null): Collection
    {
        $config = is_array($exam->random_config) ? $exam->random_config : [];
        /** @var array<string, mixed> $config */
        $subjectsRaw = $config['subjects'] ?? [];
        $subjects = is_array($subjectsRaw) ? $subjectsRaw : [];
        if ($subjects === []) {
            return collect();
        }

        $preferFrequent = (bool) ($config['prefer_frequent'] ?? true);
        $classificationId = (int) $exam->job_classification_id;
        $duplicateFingerprints = is_array($config['duplicate_fingerprints'] ?? null)
            ? array_values(array_filter($config['duplicate_fingerprints']))
            : [];

        $picked = collect();

        if ($duplicateFingerprints !== []) {
            $assigner = app(QuestionAssignService::class);
            foreach ($assigner->questionsForFingerprints($duplicateFingerprints, $classificationId, (int) $exam->id) as $question) {
                $picked->push($question);
            }
        }

        foreach ($subjects as $slug => $count) {
            $slug = (string) $slug;
            $count = (int) $count;
            if ($count <= 0) {
                continue;
            }
            if ($subjectFilter && $subjectFilter !== $slug) {
                continue;
            }

            $query = Question::query()
                ->where('subject', $slug)
                ->whereHas('exam', function ($q) use ($classificationId, $exam) {
                    $q->where('status', 'published')
                        ->where(function ($w) use ($classificationId, $exam) {
                            $w->where('job_classification_id', $classificationId)
                                ->orWhere('id', $exam->id);
                        });
                });

            if ($preferFrequent) {
                $query->orderByDesc('times_served')->orderByDesc('id');
            } else {
                $query->inRandomOrder();
            }

            // Over-fetch then shuffle weighted toward frequent
            $pool = $query->limit(max($count * 4, $count))->get();
            if ($pool->isEmpty()) {
                continue;
            }

            if ($preferFrequent && $pool->count() > $count) {
                $top = $pool->take((int) ceil($count * 1.5));
                $rest = $pool->slice($top->count())->values();
                $selected = $top->shuffle()->take($count);
                if ($selected->count() < $count) {
                    $selected = $selected->concat($rest->shuffle()->take($count - $selected->count()));
                }
            } else {
                $selected = $pool->shuffle()->take($count);
            }

            $picked = $picked->concat($selected);
        }

        $picked = $picked->unique('id')->values();

        if ($picked->isNotEmpty()) {
            Question::query()
                ->whereIn('id', $picked->pluck('id')->all())
                ->update(['times_served' => DB::raw('times_served + 1')]);
        }

        return $picked->shuffle()->values();
    }
}
