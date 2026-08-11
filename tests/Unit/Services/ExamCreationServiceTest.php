<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ExamCategory;
use App\Models\User;
use App\Services\Exam\ExamCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExamCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExamCreationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ExamCreationService::class);
    }

    public function test_sets_defaults_from_config(): void
    {
        config([
            'exam.defaults.status' => 'draft',
            'exam.defaults.price' => 1200,
            'exam.defaults.total_questions' => 0,
            'exam.defaults.has_negative_marking' => true,
            'exam.scoring.default_negative_mark_ratio' => 0.25,
        ]);

        $user = User::factory()->create();
        $data = $this->service->prepareData([
            'title' => 'New Exam',
            'duration_minutes' => 30,
            'passing_score' => 50,
            'total_marks' => 100,
            'is_free' => true,
            'subscription_required' => 'any',
        ], $user);

        $this->assertSame($user->id, $data['created_by']);
        $this->assertSame('draft', $data['status']);
        $this->assertSame(1200, $data['price']);
        $this->assertSame(0, $data['total_questions']);
        $this->assertTrue($data['has_negative_marking']);
        $this->assertSame(0.25, $data['negative_mark_ratio']);
    }

    public function test_leaves_slug_blank_for_observer(): void
    {
        $user = User::factory()->create();
        $data = $this->service->prepareData([
            'title' => 'Generated Slug Exam',
            'slug' => '',
            'duration_minutes' => 30,
            'passing_score' => 50,
            'total_marks' => 100,
            'is_free' => true,
            'subscription_required' => 'any',
        ], $user);

        $this->assertArrayNotHasKey('slug', $data);
    }

    public function test_uses_provided_slug_when_present(): void
    {
        $user = User::factory()->create();
        $data = $this->service->prepareData([
            'title' => 'Title',
            'slug' => 'keep-this-slug',
            'duration_minutes' => 30,
            'passing_score' => 50,
            'total_marks' => 100,
            'is_free' => true,
            'subscription_required' => 'any',
        ], $user);

        $this->assertSame('keep-this-slug', $data['slug']);
    }

    public function test_sets_default_category_when_empty(): void
    {
        $user = User::factory()->create();
        $data = $this->service->prepareData([
            'title' => 'With Category',
            'duration_minutes' => 30,
            'passing_score' => 50,
            'total_marks' => 100,
            'is_free' => true,
            'subscription_required' => 'any',
        ], $user);

        $this->assertNotEmpty($data['category_id']);
        $this->assertTrue(
            ExamCategory::query()->whereKey($data['category_id'])->where('slug', 'general')->exists()
        );
    }
}
