<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $exam_id
 * @property string|null $subject
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property numeric-string|float|null $score
 * @property int|null $total_correct
 * @property int|null $total_wrong
 * @property string|null $status
 * @property array<int|string, mixed>|null $answers
 * @property bool $is_retry_wrong
 * @property int|null $parent_attempt_id
 * @property string|null $retry_mode
 * @property-read User|null $user
 * @property-read Exam|null $exam
 * @property int|null $rank
 * @property list<array<string, mixed>>|null $result_questions
 * @property-read int|null $attempts
 * @property-read numeric-string|float|int|null $total_score
 * @property-read int|null $activity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExamAttempt extends Model
{
    /** @use HasFactory<\Database\Factories\ExamAttemptFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_id',
        'subject',
        'started_at',
        'finished_at',
        'score',
        'total_correct',
        'total_wrong',
        'status',
        'answers',
        'is_retry_wrong',
        'parent_attempt_id',
        'retry_mode',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'answers' => 'array',
            'score' => 'decimal:2',
            'total_correct' => 'integer',
            'total_wrong' => 'integer',
            'is_retry_wrong' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * درصد و نتیجه بر اساس تعداد درست نسبت به کل سوالات، نه نمره خام تقسیم بر total_marks.
     *
     * @return array{
     *     total_correct: int,
     *     total_wrong: int,
     *     total_unanswered: int,
     *     total_questions: int,
     *     percentage: float,
     *     passed: bool|null
     * }
     */
    public function resultSummary(): array
    {
        $this->loadMissing('exam');

        $correct = (int) $this->total_correct;
        $wrong = (int) $this->total_wrong;
        $answerCount = is_array($this->answers) ? count($this->answers) : 0;
        $totalFromExam = (int) ($this->exam?->total_questions ?: 0);
        $isRetry = (bool) $this->is_retry_wrong;
        $total = $isRetry
            ? max($answerCount, $correct + $wrong, 1)
            : max($totalFromExam, $correct + $wrong, $answerCount, 1);

        $percentage = round(($correct / $total) * 100, 2);

        if ($correct === 0 && $wrong === 0) {
            $totalMarks = (float) ($this->exam?->total_marks ?: 0);
            $score = (float) $this->score;
            if ($totalMarks > 1 && $score >= 0) {
                $fromScore = round(($score / $totalMarks) * 100, 2);
                if ($fromScore >= 0 && $fromScore <= 100) {
                    $percentage = $fromScore;
                }
            } elseif ($score >= 0 && $score <= 100) {
                $percentage = round($score, 2);
            }
        }

        $exam = $this->exam;
        $passing = (float) (($exam !== null ? $exam->passing_score : null) ?? 0);
        $passed = null;
        if ($passing > 0) {
            $passed = $passing <= 100
                ? $percentage >= $passing
                : (float) $this->score >= $passing;
        }

        return [
            'total_correct' => $correct,
            'total_wrong' => $wrong,
            'total_unanswered' => max(0, $total - $correct - $wrong),
            'total_questions' => $total,
            'percentage' => $percentage,
            'passed' => $passed,
        ];
    }

    /** @return array<string, mixed> */
    public function toHistoryItem(): array
    {
        $stats = $this->resultSummary();

        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'exam_title' => $this->exam?->title,
            'exam_slug' => $this->exam?->slug,
            'subject' => $this->subject,
            'score' => $this->score,
            'total_correct' => $stats['total_correct'],
            'total_wrong' => $stats['total_wrong'],
            'total_unanswered' => $stats['total_unanswered'],
            'total_questions' => $stats['total_questions'],
            'total_marks' => $this->exam?->total_marks,
            'percentage' => $stats['percentage'],
            'passed' => $stats['passed'],
            'created_at' => $this->created_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'status' => $this->status,
        ];
    }
}
