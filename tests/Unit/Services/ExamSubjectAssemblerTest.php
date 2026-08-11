<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Question;
use App\Models\User;
use App\Services\Exam\ExamSubjectAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ExamSubjectAssemblerTest extends TestCase
{
    use RefreshDatabase;

    private ExamSubjectAssembler $assembler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assembler = app(ExamSubjectAssembler::class);
    }

    public function test_assembles_subjects_with_db_records(): void
    {
        $exam = Exam::factory()->create();

        ExamSubject::query()->updateOrCreate(
            ['slug' => 'math'],
            [
                'name' => 'ریاضی',
                'icon' => '🔢',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        Question::factory()->count(2)->create(['exam_id' => $exam->id, 'subject' => 'math']);

        $result = $this->assembler->assemble($exam, null);

        $this->assertCount(1, $result);
        $this->assertSame('math', $result[0]['slug']);
        $this->assertSame('ریاضی', $result[0]['name']);
        $this->assertSame('🔢', $result[0]['icon']);
        $this->assertSame(2, $result[0]['question_count']);
    }

    public function test_falls_back_to_raw_subject_for_unknown_slugs(): void
    {
        $exam = Exam::factory()->create();
        Question::factory()->create(['exam_id' => $exam->id, 'subject' => 'unknown-topic']);

        $result = $this->assembler->assemble($exam, User::factory()->create());

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['id']);
        $this->assertSame('unknown-topic', $result[0]['slug']);
        $this->assertSame('unknown-topic', $result[0]['name']);
        $this->assertSame(config('exam.subjects.default_icon'), $result[0]['icon']);
    }

    public function test_returns_empty_array_when_no_subjects(): void
    {
        $exam = Exam::factory()->create();
        Question::factory()->create(['exam_id' => $exam->id, 'subject' => null]);
        Question::factory()->create(['exam_id' => $exam->id, 'subject' => '']);

        $this->assertSame([], $this->assembler->assemble($exam, null));
    }

    public function test_sorts_by_sort_order(): void
    {
        $exam = Exam::factory()->create();

        ExamSubject::query()->create([
            'name' => 'دوم',
            'slug' => 'second',
            'icon' => '2',
            'sort_order' => 20,
            'is_active' => true,
        ]);
        ExamSubject::query()->create([
            'name' => 'اول',
            'slug' => 'first',
            'icon' => '1',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        Question::factory()->create(['exam_id' => $exam->id, 'subject' => 'second']);
        Question::factory()->create(['exam_id' => $exam->id, 'subject' => 'first']);
        Question::factory()->create(['exam_id' => $exam->id, 'subject' => 'orphan']);

        $result = $this->assembler->assemble($exam, null);

        $this->assertSame(['first', 'second', 'orphan'], array_column($result, 'slug'));
    }

    public function test_counts_questions_correctly(): void
    {
        $exam = Exam::factory()->create();
        ExamSubject::query()->updateOrCreate(
            ['slug' => 'general'],
            [
                'name' => 'عمومی',
                'icon' => '📘',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        Question::factory()->count(3)->create(['exam_id' => $exam->id, 'subject' => 'general']);
        Question::factory()->count(2)->create(['exam_id' => $exam->id, 'subject' => 'extra']);

        $result = $this->assembler->assemble($exam, null);
        $bySlug = collect($result)->keyBy('slug');

        $this->assertSame(3, $bySlug['general']['question_count']);
        $this->assertSame(2, $bySlug['extra']['question_count']);
    }

    public function test_uses_config_default_icon(): void
    {
        config(['exam.subjects.default_icon' => '🎯']);

        $exam = Exam::factory()->create();
        Question::factory()->create(['exam_id' => $exam->id, 'subject' => 'custom']);

        $result = $this->assembler->assemble($exam, null);

        $this->assertSame('🎯', $result[0]['icon']);
    }

    public function test_avoids_n_plus_one_for_subjects(): void
    {
        $exam = Exam::factory()->create();

        foreach (['a', 'b', 'c'] as $i => $slug) {
            ExamSubject::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'icon' => 'x',
                'sort_order' => $i,
                'is_active' => true,
            ]);
            Question::factory()->count(2)->create(['exam_id' => $exam->id, 'subject' => $slug]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assembler->assemble($exam, null);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // One aggregate count query + one whereIn subjects query (no per-subject loops).
        $this->assertLessThanOrEqual(3, count($queries));
        $this->assertCount(3, $this->assembler->assemble($exam, null));
    }
}
