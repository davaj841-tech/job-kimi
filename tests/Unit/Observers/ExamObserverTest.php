<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExamObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_generates_slug_on_create(): void
    {
        $exam = Exam::factory()->create([
            'title' => 'Observer Auto Slug',
            'slug' => null,
        ]);

        $this->assertNotNull($exam->slug);
        $this->assertNotSame('', $exam->slug);
        $this->assertStringContainsString('observer-auto-slug', (string) $exam->slug);
    }

    public function test_keeps_unique_provided_slug(): void
    {
        $exam = Exam::factory()->create([
            'title' => 'Title',
            'slug' => 'my-fixed-slug',
        ]);

        $this->assertSame('my-fixed-slug', $exam->slug);
    }

    public function test_adjusts_duplicate_provided_slug(): void
    {
        Exam::factory()->create(['slug' => 'dup-slug']);

        $exam = Exam::factory()->create([
            'title' => 'Another',
            'slug' => 'dup-slug',
        ]);

        $this->assertNotSame('dup-slug', $exam->slug);
        $this->assertStringStartsWith('dup-slug-', (string) $exam->slug);
    }

    public function test_fills_category_and_creator_when_missing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $exam = Exam::query()->create([
            'title' => 'بانک تمرین بدون کلید خارجی',
            'status' => 'draft',
            'is_free' => true,
            'price' => 0,
            'duration_minutes' => 60,
            'total_questions' => 0,
            'passing_score' => 50,
        ]);

        $this->assertNotNull($exam->category_id);
        $this->assertSame($admin->id, (int) $exam->created_by);
        $this->assertTrue(ExamCategory::query()->whereKey($exam->category_id)->exists());
    }
}
