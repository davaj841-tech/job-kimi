<?php

declare(strict_types=1);

namespace Tests\Unit\Install;

use InstallEngine;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../../cpanel-installer/lib/InstallEngine.php';

final class InstallEngineTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jobazmoon-install-test-'.bin2hex(random_bytes(4));
        mkdir($this->tmpRoot, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpRoot);
        parent::tearDown();
    }

    public function test_validate_database_input_rejects_invalid_name(): void
    {
        $engine = $this->engine();

        $errors = $engine->validateDatabaseInput([
            'host' => '127.0.0.1',
            'port' => '3306',
            'name' => 'bad-name!',
            'user' => 'root',
            'pass' => '',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('نام پایگاه', $errors[0]);
    }

    public function test_validate_site_input_requires_matching_password(): void
    {
        $engine = $this->engine();

        $errors = $engine->validateSiteInput([
            'site_name' => 'JobAzmoon',
            'url' => 'https://example.com',
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'mobile' => '09123456789',
            'password' => 'secret12',
            'password_confirmation' => 'different',
        ]);

        $this->assertContains('رمز و تکرار رمز یکسان نیست.', $errors);
    }

    public function test_is_locked_when_installed_marker_exists(): void
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        $job = dirname($public).DIRECTORY_SEPARATOR.'job';
        mkdir($public, 0755, true);
        mkdir($job.DIRECTORY_SEPARATOR.'storage', 0755, true);
        file_put_contents($job.DIRECTORY_SEPARATOR.'.env', "APP_KEY=base64:test\n");
        file_put_contents($job.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed', '{}');

        $engine = new InstallEngine($public, dirname($public), $job, $public.'/package/jobazmoon-core.zip');

        $this->assertTrue($engine->isLocked());
    }

    public function test_requirements_includes_php_version_check(): void
    {
        $engine = $this->engine();
        $reqs = $engine->requirements();
        $labels = array_column($reqs, 'label');

        $this->assertContains('PHP >= '.InstallEngine::MIN_PHP, $labels);
    }

    private function engine(): InstallEngine
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        mkdir($public, 0755, true);
        mkdir($public.DIRECTORY_SEPARATOR.'package', 0755, true);

        return new InstallEngine(
            $public,
            $this->tmpRoot,
            $this->tmpRoot.DIRECTORY_SEPARATOR.'job',
            $public.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'jobazmoon-core.zip',
        );
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
