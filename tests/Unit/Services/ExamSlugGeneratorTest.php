<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Exam;
use App\Services\Exam\ExamSlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExamSlugGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private ExamSlugGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(ExamSlugGenerator::class);
    }

    public function test_generates_slug_from_title(): void
    {
        $slug = $this->generator->generate('Sample Exam Title');

        $this->assertStringStartsWith('sample-exam-title-', $slug);
        $this->assertMatchesRegularExpression('/^sample-exam-title-[a-zA-Z0-9]{5}$/', $slug);
    }

    public function test_appends_random_suffix_for_duplicate(): void
    {
        Exam::factory()->create(['slug' => 'taken-slug']);

        $unique = $this->generator->ensureUnique('taken-slug');

        $this->assertNotSame('taken-slug', $unique);
        $this->assertStringStartsWith('taken-slug-', $unique);
        $this->assertFalse(Exam::query()->where('slug', $unique)->exists());
    }

    public function test_fallback_prefix_when_title_is_empty(): void
    {
        config(['exam.slug.fallback_prefix' => 'exam']);

        $slug = $this->generator->generate('');

        $this->assertStringStartsWith('exam-', $slug);
    }

    public function test_preserves_existing_slug_when_provided(): void
    {
        $slug = $this->generator->generate('Ignored Title', 'my-custom-slug');

        $this->assertSame('my-custom-slug', $slug);
    }
}
