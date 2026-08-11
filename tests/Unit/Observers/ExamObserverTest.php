<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Models\Exam;
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
}
