<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Exam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HasUniqueSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_persian_title_uses_fallback_prefix(): void
    {
        config(['exam.slug.fallback_prefix' => 'exam']);

        $exam = new Exam(['title' => '']);
        $slug = $exam->generateSlug('');

        $this->assertStringStartsWith('exam-', $slug);
    }

    public function test_blank_title_generates_fallback_slug(): void
    {
        $exam = Exam::factory()->create(['title' => '!!!', 'slug' => null]);

        $this->assertNotSame('', (string) $exam->slug);
        $this->assertStringStartsWith('exam-', (string) $exam->slug);
    }

    public function test_resolves_slug_collision(): void
    {
        Exam::factory()->create(['slug' => 'taken-slug']);

        $exam = new Exam;
        $unique = $exam->resolveSlugCollision('taken-slug');

        $this->assertNotSame('taken-slug', $unique);
        $this->assertStringStartsWith('taken-slug-', $unique);
    }

    public function test_preserves_existing_slug_argument(): void
    {
        $exam = new Exam;
        $slug = $exam->generateSlug('ignored', 'custom-slug');

        $this->assertSame('custom-slug', $slug);
    }
}
