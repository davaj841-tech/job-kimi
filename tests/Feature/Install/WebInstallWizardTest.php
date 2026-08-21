<?php

declare(strict_types=1);

namespace Tests\Feature\Install;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebInstallWizardTest extends TestCase
{
    use RefreshDatabase;

    /** Keep storage/installed absent so install routes register. */
    protected bool $ensureInstalledMarker = false;

    private ?string $installedMarkerBackup = null;

    private bool $hadInstalledMarker = false;

    protected function setUp(): void
    {
        // tests/Feature/Install → project root is three levels up
        $base = dirname(__DIR__, 3);
        $installed = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed';
        if (is_file($installed)) {
            $this->hadInstalledMarker = true;
            $this->installedMarkerBackup = (string) file_get_contents($installed);
            @unlink($installed);
        }

        $routeCache = $base.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'routes-v7.php';
        if (is_file($routeCache)) {
            @unlink($routeCache);
        }

        parent::setUp();

        // Ensure lock stays absent so install routes register on reboot.
        if (is_file(storage_path('installed'))) {
            @unlink(storage_path('installed'));
        }
        $this->refreshApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (! $this->hadInstalledMarker) {
            return;
        }

        $path = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed';
        @file_put_contents($path, $this->installedMarkerBackup ?? '');
    }

    public function test_install_welcome_is_accessible_when_not_installed(): void
    {
        $this->withSession(['install_step' => 1])
            ->get('/install')
            ->assertOk()
            ->assertSee('پیش‌نیاز');
    }

    public function test_database_test_returns_sanitized_error_without_credentials(): void
    {
        $this->withSession(['install_step' => 2])
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->postJson('/install/database/test', [
                'db_host' => '127.0.0.1',
                'db_port' => '3306',
                'db_database' => 'nonexistent_db_xyz',
                'db_username' => 'invalid_user_xyz',
                'db_password' => 'wrong',
                'db_prefix' => '',
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonMissing(['message' => 'wrong']);
    }
}
