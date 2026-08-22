<?php

declare(strict_types=1);

namespace Tests\Unit\Install;

use InstallEngine;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use ZipArchive;

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

    public function test_requirements_includes_php_version_check(): void
    {
        $engine = $this->engine();
        $reqs = $engine->requirements();
        $labels = array_column($reqs, 'label');

        $this->assertContains('PHP >= '.InstallEngine::MIN_PHP, $labels);

        $phpItem = null;
        foreach ($reqs as $item) {
            if ($item['label'] === 'PHP >= '.InstallEngine::MIN_PHP) {
                $phpItem = $item;
                break;
            }
        }
        $this->assertNotNull($phpItem);
        $this->assertSame(version_compare(PHP_VERSION, InstallEngine::MIN_PHP, '>='), $phpItem['ok']);
        $this->assertFalse($phpItem['warn']);
    }

    public function test_requirements_reports_missing_package(): void
    {
        $engine = $this->engine();
        $reqs = $engine->requirements();

        $packageItem = null;
        foreach ($reqs as $item) {
            if (str_contains($item['label'], InstallEngine::PACKAGE_FILE)) {
                $packageItem = $item;
                break;
            }
        }

        $this->assertNotNull($packageItem);
        $this->assertFalse($packageItem['ok']);
        $this->assertNotEmpty($engine->requiredFailures($reqs));
    }

    public function test_requirements_includes_disk_check(): void
    {
        $engine = $this->engine();
        $reqs = $engine->requirements();
        $labels = array_column($reqs, 'label');

        $this->assertContains('فضای دیسک کافی', $labels);
    }

    public function test_validate_zip_entries_rejects_path_traversal(): void
    {
        $zipPath = $this->tmpRoot.DIRECTORY_SEPARATOR.'evil.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('../evil.php', '<?php echo "pwn";');
        $zip->addFromString('app/safe.txt', 'ok');
        $zip->close();

        $engine = $this->engine();
        $opened = new ZipArchive;
        $this->assertTrue($opened->open($zipPath));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/path traversal|خطرناک/u');
        try {
            $engine->validateZipEntries($opened);
        } finally {
            $opened->close();
        }
    }

    public function test_extract_zip_safely_rejects_traversal_via_public_validate(): void
    {
        $zipPath = $this->tmpRoot.DIRECTORY_SEPARATOR.'traverse.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('foo/../../outside.txt', 'nope');
        $zip->close();

        $engine = $this->engine();
        $opened = new ZipArchive;
        $this->assertTrue($opened->open($zipPath));

        try {
            $engine->validateZipEntries($opened);
            $this->fail('Expected RuntimeException for traversal entry');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('خطرناک', $e->getMessage());
        } finally {
            $opened->close();
        }
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

    public function test_validate_database_input_rejects_bad_port_and_empty_host(): void
    {
        $engine = $this->engine();

        $errors = $engine->validateDatabaseInput([
            'host' => '',
            'port' => '99999',
            'name' => 'ok_db',
            'user' => 'u',
            'pass' => 'secret',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertTrue(
            count(array_filter($errors, static fn (string $e): bool => str_contains($e, 'هاست') || str_contains($e, 'پورت'))) >= 1
        );
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

    public function test_validate_site_input_rejects_invalid_mobile_and_short_password(): void
    {
        $engine = $this->engine();

        $errors = $engine->validateSiteInput([
            'site_name' => 'JobAzmoon',
            'url' => 'https://example.com',
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'mobile' => '12345',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $this->assertTrue(count(array_filter($errors, static fn (string $e): bool => str_contains($e, 'موبایل'))) >= 1);
        $this->assertTrue(count(array_filter($errors, static fn (string $e): bool => str_contains($e, 'رمز'))) >= 1);
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
        $this->assertSame('locked', $engine->installationStatus());
    }

    public function test_is_locked_false_without_both_files(): void
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        $job = $this->tmpRoot.DIRECTORY_SEPARATOR.'job';
        mkdir($public, 0755, true);
        mkdir($job.DIRECTORY_SEPARATOR.'storage', 0755, true);
        file_put_contents($job.DIRECTORY_SEPARATOR.'.env', "APP_KEY=base64:test\n");

        $engine = new InstallEngine($public, $this->tmpRoot, $job, $public.'/package/x.zip');

        $this->assertFalse($engine->isLocked());
    }

    public function test_installation_status_incomplete(): void
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        $job = $this->tmpRoot.DIRECTORY_SEPARATOR.'job';
        mkdir($public, 0755, true);
        mkdir($job, 0755, true);
        file_put_contents($job.DIRECTORY_SEPARATOR.'artisan', "#!/usr/bin/env php\n");

        $engine = new InstallEngine($public, $this->tmpRoot, $job, $public.'/package/x.zip');

        $this->assertSame('incomplete', $engine->installationStatus());
        $this->assertFalse($engine->isLocked());
    }

    public function test_installation_status_corrupted(): void
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        $job = $this->tmpRoot.DIRECTORY_SEPARATOR.'job';
        mkdir($public, 0755, true);
        mkdir($job.DIRECTORY_SEPARATOR.'storage', 0755, true);
        file_put_contents($job.DIRECTORY_SEPARATOR.'artisan', "#!/usr/bin/env php\n");
        file_put_contents($job.DIRECTORY_SEPARATOR.'.env', "APP_KEY=base64:test\n");
        file_put_contents($job.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed', '{}');
        // Missing vendor/autoload and bootstrap/app.php → corrupted while locked files exist

        $engine = new InstallEngine($public, $this->tmpRoot, $job, $public.'/package/x.zip');

        $this->assertSame('corrupted', $engine->installationStatus());
    }

    public function test_write_env_file_quotes_and_deduplicates_keys(): void
    {
        $engine = $this->engine();
        $envPath = $this->tmpRoot.DIRECTORY_SEPARATOR.'test.env';
        file_put_contents($envPath, "APP_NAME=Old\nAPP_NAME=Dup\nDB_HOST=127.0.0.1\n");

        $method = new ReflectionMethod(InstallEngine::class, 'writeEnvFile');
        $method->setAccessible(true);
        $method->invoke($engine, $envPath, [
            'APP_NAME' => 'My Site',
            'DB_PASSWORD' => 'p@ss word#1',
            'NEW_KEY' => 'plain',
        ]);

        $content = (string) file_get_contents($envPath);
        $this->assertSame(1, preg_match_all('/^APP_NAME=/m', $content));
        $this->assertMatchesRegularExpression('/^APP_NAME="My Site"$/m', $content);
        $this->assertMatchesRegularExpression('/^DB_PASSWORD="p@ss word#1"$/m', $content);
        $this->assertMatchesRegularExpression('/^NEW_KEY=plain$/m', $content);
    }

    public function test_sanitize_public_error_redacts_password(): void
    {
        $engine = $this->engine();
        $remember = new ReflectionMethod(InstallEngine::class, 'rememberSecrets');
        $remember->setAccessible(true);
        $remember->invoke($engine, ['pass' => 'SuperSecretPass99'], ['password' => 'AdminPass99']);

        $sanitize = new ReflectionMethod(InstallEngine::class, 'sanitizePublicError');
        $sanitize->setAccessible(true);

        $out = $sanitize->invoke($engine, 'SQLSTATE[HY000] Access denied for user with password SuperSecretPass99');
        $this->assertStringNotContainsString('SuperSecretPass99', $out);
        $this->assertStringNotContainsString('AdminPass99', $out);

        $out2 = $sanitize->invoke($engine, 'failed with AdminPass99 in payload');
        $this->assertStringNotContainsString('AdminPass99', $out2);
        $this->assertStringContainsString('[REDACTED]', $out2);
    }

    public function test_constants_are_defined(): void
    {
        $this->assertGreaterThan(0, InstallEngine::MAX_PACKAGE_BYTES);
        $this->assertGreaterThan(0, InstallEngine::MAX_ZIP_ENTRIES);
        $this->assertGreaterThan(0, InstallEngine::MAX_UNCOMPRESSED_BYTES);
        $this->assertGreaterThan(1.0, InstallEngine::MAX_COMPRESSION_RATIO);
        $this->assertNotSame('', InstallEngine::INSTALLER_VERSION);
    }

    public function test_verify_install_includes_level_field(): void
    {
        $engine = $this->engine();
        $checks = $engine->verifyInstall();
        $this->assertNotEmpty($checks);
        foreach ($checks as $c) {
            $this->assertArrayHasKey('label', $c);
            $this->assertArrayHasKey('ok', $c);
            $this->assertArrayHasKey('detail', $c);
            $this->assertArrayHasKey('level', $c);
            $this->assertContains($c['level'], ['pass', 'fail', 'warning']);
            if ($c['level'] === 'fail') {
                $this->assertFalse($c['ok']);
            } else {
                $this->assertTrue($c['ok']);
            }
        }
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
