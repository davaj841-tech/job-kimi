<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
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

        $bladePath = resource_path('views/scribe/index.blade.php');
        $this->assertFileExists($bladePath, 'Expected Blade docs at resources/views/scribe/index.blade.php');
        $this->assertGreaterThan(
            1000,
            filesize($bladePath),
            'Scribe Blade docs must not be empty'
        );

        // NOTE: Do not `git diff` regenerated docs against the index.
        // Scribe embeds OS-specific temp upload paths and non-stable enum/bool
        // examples even with faker_seed, so byte-identical sync is not reliable
        // across Windows (local) and Linux (CI).

        $this->get('/api/documentation')
            ->assertStatus(200);

        $this->get('/api/documentation.openapi')
            ->assertStatus(200);
    }
}
