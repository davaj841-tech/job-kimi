<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\JobPost;
use App\Services\Seo\SeoAutoOptimizer;
use App\Support\Utf8Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SeoUtf8DescriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function auto_optimizer_truncates_with_ascii_ellipsis_and_valid_utf8(): void
    {
        Queue::fake();

        $description = str_repeat('پرداخت الکترونیک سداد آگهی استخدام کارشناس بک اند. ', 20);

        $job = JobPost::factory()->create([
            'status' => 'approved',
            'title' => 'استخدام کارشناس بک اند',
            'description' => $description,
        ]);

        // Invalid trailing byte must not be persisted into seo_meta.description.
        $job->setAttribute('description', $description.chr(0xDB));

        $changed = app(SeoAutoOptimizer::class)->optimize($job);

        $this->assertTrue($changed);
        $job->refresh();

        $metaDescription = (string) $job->seoMeta?->description;
        $this->assertNotSame('', $metaDescription);
        $this->assertTrue(mb_check_encoding($metaDescription, 'UTF-8'));
        $this->assertSame($metaDescription, Utf8Text::sanitize($metaDescription));
        $this->assertStringNotContainsString("\u{2026}", $metaDescription);
        $this->assertDoesNotMatchRegularExpression('/\xDB(?![\x80-\xBF])/', $metaDescription);
        $this->assertStringEndsWith('...', $metaDescription);
        $this->assertLessThanOrEqual(320, mb_strlen($metaDescription, 'UTF-8'));
    }
}
