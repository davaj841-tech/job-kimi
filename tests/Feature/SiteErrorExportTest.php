<?php

namespace Tests\Feature;

use App\Models\SiteError;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteErrorExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_site_errors_excel(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        SiteError::query()->create([
            'level' => 'error',
            'message' => 'Example failure',
            'message_fa' => 'خطای نمونه',
            'exception_class' => 'RuntimeException',
            'occurrences' => 2,
            'last_seen_at' => now(),
        ]);

        $this->get('/api/v1/admin/site-errors/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
