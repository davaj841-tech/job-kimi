<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    public function seedDefaults(): void
    {
        $items = [
            ['code' => 'first_exam', 'title' => 'اولین قدم', 'description' => 'اولین آزمون را کامل کردید', 'icon' => '🎯'],
            ['code' => 'perfect_score', 'title' => 'نمره کامل', 'description' => 'نمره کامل در یک آزمون', 'icon' => '🏆'],
            ['code' => 'ten_exams', 'title' => 'آزمون‌دهنده حرفه‌ای', 'description' => '۱۰ آزمون کامل شده', 'icon' => '📘'],
            ['code' => 'streak_30', 'title' => 'پرتکرار', 'description' => 'فعالیت مداوم ۳۰ روزه', 'icon' => '🔥'],
            ['code' => 'top_10', 'title' => 'برترین‌ها', 'description' => 'حضور در ۱۰٪ برتر', 'icon' => '⭐'],
        ];

        foreach ($items as $item) {
            Achievement::query()->updateOrCreate(['code' => $item['code']], $item);
        }
    }

    public function evaluateAfterExam(User $user, ExamAttempt $attempt): void
    {
        $this->seedDefaults();

        $completed = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        if ($completed === 1) {
            $this->award($user, 'first_exam');
        }
        if ($completed >= 10) {
            $this->award($user, 'ten_exams');
        }

        $exam = $attempt->exam;
        if ($exam && (float) $attempt->score >= (float) ($exam->total_marks ?: 0) && (float) $exam->total_marks > 0) {
            $this->award($user, 'perfect_score');
        }

        $days = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('finished_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(finished_at) as d'))
            ->distinct()
            ->count();
        if ($days >= 30) {
            $this->award($user, 'streak_30');
        }
    }

    public function award(User $user, string $code): void
    {
        $achievement = Achievement::query()->where('code', $code)->first();
        if (! $achievement) {
            return;
        }

        $exists = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->where('achievement_id', $achievement->id)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('user_achievements')->insert([
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
            'earned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->notify(new GenericDatabaseNotification(
            'achievement',
            'نشان جدید',
            'نشان «'.$achievement->title.'» را کسب کردید!',
            '/profile'
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        $this->seedDefaults();

        return Achievement::query()
            ->leftJoin('user_achievements', function ($join) use ($user) {
                $join->on('achievements.id', '=', 'user_achievements.achievement_id')
                    ->where('user_achievements.user_id', '=', $user->id);
            })
            ->select('achievements.*', 'user_achievements.earned_at')
            ->get()
            ->map(fn ($a) => [
                'code' => $a->code,
                'title' => $a->title,
                'description' => $a->description,
                'icon' => $a->icon,
                'earned' => ! empty($a->earned_at),
                'earned_at' => $a->earned_at,
            ])
            ->all();
    }
}
