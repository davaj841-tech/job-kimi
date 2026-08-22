<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    /**
     * Paths that must stay in sync with `php artisan scribe:generate`.
     *
     * @var list<string>
     */
    private const SCRIBE_PATHS = [
        'storage/app/private/scribe',
        'resources/views/scribe',
        'public/vendor/scribe',
    ];

    public function test_scribe_documentation_is_generated_and_reachable(): void
    {
        $exitCode = Artisan::call('scribe:generate');

        $this->assertSame(
            0,
            $exitCode,
            "scribe:generate failed:\n".Artisan::output()
        );

        $openapiPath = storage_path('app/private/scribe/openapi.yaml');
        $this->assertFileExists(
            $openapiPath,
            'Expected OpenAPI spec at storage/app/private/scribe/openapi.yaml'
        );
        $this->assertGreaterThan(
            0,
            filesize($openapiPath),
            'openapi.yaml must not be empty'
        );

        $this->get('/api/documentation')
            ->assertStatus(200);

        $this->get('/api/documentation.openapi')
            ->assertStatus(200);

        if ($this->runningInCi()) {
            $this->assertScribeDocsMatchGitIndex();
        }
    }

    private function runningInCi(): bool
    {
        return filter_var(getenv('CI') ?: env('CI'), FILTER_VALIDATE_BOOLEAN)
            || filter_var(getenv('GITHUB_ACTIONS') ?: '', FILTER_VALIDATE_BOOLEAN);
    }

    private function assertScribeDocsMatchGitIndex(): void
    {
        $result = Process::path(base_path())->run([
            'git',
            'diff',
            '--exit-code',
            '--',
            ...self::SCRIBE_PATHS,
        ]);

        $this->assertTrue(
            $result->successful(),
            "مستندات API با مخزن هم‌خوان نیست. لطفاً `php artisan scribe:generate` را اجرا و خروجی را commit کنید.\n"
            .$result->errorOutput()
            .$result->output()
        );
    }
}
