<?php

declare(strict_types=1);

namespace Tests\Unit\Install;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
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
        $minPhp = $engine->resolvedMinPhp();

        $this->assertContains('PHP >= '.$minPhp, $labels);

        $phpItem = null;
        foreach ($reqs as $item) {
            if ($item['label'] === 'PHP >= '.$minPhp) {
                $phpItem = $item;
                break;
            }
        }
        $this->assertNotNull($phpItem);
        $this->assertSame(version_compare(PHP_VERSION, $minPhp, '>='), $phpItem['ok']);
        $this->assertFalse($phpItem['warn']);
    }

    public function test_resolved_min_php_reads_composer_constraint(): void
    {
        $engine = $this->engine();
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $engine->resolvedMinPhp());
        $this->assertTrue(version_compare($engine->resolvedMinPhp(), '8.0.0', '>='));
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

    public function test_pcntl_and_posix_are_warnings_not_required_failures(): void
    {
        $engine = $this->engine();
        $reqs = $engine->requirements();

        $pcntl = null;
        $posix = null;
        foreach ($reqs as $item) {
            if (str_starts_with($item['label'], 'pcntl')) {
                $pcntl = $item;
            }
            if (str_starts_with($item['label'], 'posix')) {
                $posix = $item;
            }
        }

        $this->assertNotNull($pcntl);
        $this->assertNotNull($posix);
        $this->assertTrue($pcntl['warn']);
        $this->assertTrue($posix['warn']);
        $this->assertStringContainsString('Horizon', $pcntl['label']);
        $this->assertStringContainsString('Horizon', $posix['label']);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $pcntl['fix']);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $posix['fix']);

        $failures = $engine->requiredFailures(array_map(static function (array $item): array {
            if (str_starts_with($item['label'], 'pcntl') || str_starts_with($item['label'], 'posix')) {
                $item['ok'] = false;
            }

            return $item;
        }, $reqs));

        foreach ($failures as $failure) {
            $this->assertFalse(str_starts_with($failure['label'], 'pcntl'));
            $this->assertFalse(str_starts_with($failure['label'], 'posix'));
        }
    }

    public function test_recommended_crons_use_absolute_artisan_and_queue_flags(): void
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        $job = $this->tmpRoot.DIRECTORY_SEPARATOR.'job';
        mkdir($public, 0755, true);
        mkdir($job, 0755, true);
        file_put_contents($job.DIRECTORY_SEPARATOR.'artisan', "#!/usr/bin/env php\n");

        $engine = new InstallEngine($public, $this->tmpRoot, $job, $public.'/package/x.zip');
        $crons = $engine->recommendedCrons();

        $this->assertCount(2, $crons);
        $scheduler = $crons[0]['command'];
        $queue = $crons[1]['command'];

        $artisan = $engine->absoluteArtisanPath();
        $this->assertStringNotContainsString('/path/to/artisan', $scheduler);
        $this->assertStringNotContainsString('/path/to/artisan', $queue);
        $this->assertStringContainsString($artisan, $scheduler);
        $this->assertStringContainsString($artisan, $queue);
        $this->assertTrue(str_starts_with($artisan, $job) || realpath($artisan) === realpath($job.DIRECTORY_SEPARATOR.'artisan'));

        $this->assertStringContainsString('schedule:run', $scheduler);
        $this->assertStringContainsString('queue:work database', $queue);
        $this->assertStringContainsString('--stop-when-empty', $queue);
        $this->assertStringContainsString('--max-time=50', $queue);
        $this->assertStringContainsString('--tries=3', $queue);
        $this->assertMatchesRegularExpression('/^\* \* \* \* \* /', $scheduler);
        $this->assertMatchesRegularExpression('/^\* \* \* \* \* /', $queue);

        $php = $engine->detectPhpCliBinary();
        $this->assertNotSame('', $php);
        $this->assertStringContainsString($php, $scheduler);
        $this->assertStringContainsString($php, $queue);

        $flock = $engine->detectFlockBinary();
        if ($flock !== null) {
            $this->assertStringContainsString($flock.' -n ', $queue);
            $this->assertStringContainsString($engine->queueLockPath(), $queue);
        } else {
            $this->assertStringNotContainsString('flock ', $queue);
            $warnings = $engine->cronRecommendationWarnings();
            $this->assertTrue(
                count(array_filter($warnings, static fn (string $w): bool => str_contains($w, 'flock'))) >= 1
            );
        }
    }

    public function test_cron_warnings_when_artisan_missing(): void
    {
        $engine = $this->engine();
        $warnings = $engine->cronRecommendationWarnings();
        $this->assertTrue(
            count(array_filter($warnings, static fn (string $w): bool => str_contains($w, 'artisan'))) >= 1
        );
        $this->assertStringContainsString(
            $engine->jobDir.DIRECTORY_SEPARATOR.'artisan',
            implode("\n", $warnings)
        );
    }

    public function test_queue_lock_path_is_unique_per_job_dir(): void
    {
        $a = $this->engine();
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html2';
        $job = $this->tmpRoot.DIRECTORY_SEPARATOR.'job-b';
        mkdir($public, 0755, true);
        mkdir($job, 0755, true);
        $b = new InstallEngine($public, $this->tmpRoot, $job, $public.'/package/x.zip');

        $this->assertNotSame($a->queueLockPath(), $b->queueLockPath());
        $this->assertStringStartsWith('/tmp/job-kimi-queue-', $a->queueLockPath());
    }

    public function test_write_htaccess_blocks_sensitive_files(): void
    {
        $engine = $this->engine();
        $method = new ReflectionMethod(InstallEngine::class, 'writeHtaccess');
        $method->setAccessible(true);
        $method->invoke($engine, $engine->publicHtml);

        $ht = (string) file_get_contents($engine->publicHtml.DIRECTORY_SEPARATOR.'.htaccess');
        $this->assertStringContainsString('Options -MultiViews -Indexes', $ht);
        $this->assertMatchesRegularExpression('/FilesMatch.*\.env/s', $ht);
        $this->assertStringContainsString('artisan', $ht);
        $this->assertStringContainsString('composer\\.(json|lock)', $ht);
        $this->assertStringContainsString('package(-lock)?\\.json', $ht);
        $this->assertStringContainsString('Require all denied', $ht);
    }

    public function test_deny_install_files_via_htaccess_is_idempotent(): void
    {
        $engine = $this->engine();
        $denyPhp = new ReflectionMethod(InstallEngine::class, 'denyInstallPhpViaHtaccess');
        $denyEngine = new ReflectionMethod(InstallEngine::class, 'denyInstallEngineViaHtaccess');
        $denyPhp->setAccessible(true);
        $denyEngine->setAccessible(true);

        $denyPhp->invoke($engine);
        $denyPhp->invoke($engine);
        $denyEngine->invoke($engine);
        $denyEngine->invoke($engine);

        $ht = (string) file_get_contents($engine->publicHtml.DIRECTORY_SEPARATOR.'.htaccess');
        $this->assertSame(1, substr_count($ht, 'Files "install.php"'));
        $this->assertSame(1, substr_count($ht, 'Files "InstallEngine.php"'));
    }

    public function test_cleanup_helpers_are_idempotent_for_missing_paths(): void
    {
        $engine = $this->engine();
        $rrmdir = new ReflectionMethod(InstallEngine::class, 'rrmdir');
        $rrmdir->setAccessible(true);
        $rrmdir->invoke($engine, $this->tmpRoot.DIRECTORY_SEPARATOR.'does-not-exist');

        $this->assertTrue(true);
    }

    public function test_env_defaults_include_queue_connection_database_in_source(): void
    {
        $src = (string) file_get_contents(__DIR__.'/../../../cpanel-installer/lib/InstallEngine.php');
        $this->assertStringContainsString("'QUEUE_CONNECTION' => 'database'", $src);
        $this->assertStringContainsString("'CACHE_STORE' => 'database'", $src);
        $this->assertStringContainsString("'SESSION_DRIVER' => 'database'", $src);
        $this->assertStringContainsString("'SESSION_SECURE_COOKIE' => 'true'", $src);
        $this->assertStringContainsString("'TELESCOPE_ENABLED' => 'false'", $src);
        $this->assertStringContainsString("'SMS_ALLOW_LOG_FALLBACK' => 'false'", $src);
    }

    public function test_verify_install_checks_queue_tables_and_connection(): void
    {
        $engine = $this->engine();
        $checks = $engine->verifyInstall();
        $labels = array_column($checks, 'label');

        $this->assertContains('QUEUE_CONNECTION=database', $labels);
        $this->assertContains('جدول jobs (database queue)', $labels);
        $this->assertContains('جدول failed_jobs', $labels);
        $this->assertContains('Laravel Scheduler (schedule:list)', $labels);
        $this->assertContains('محافظت فایل‌های حساس در .htaccess', $labels);
    }

    public function test_cleanup_removes_package_and_empty_lib_only(): void
    {
        $engine = $this->engine();
        $public = $engine->publicHtml;
        $lib = $public.DIRECTORY_SEPARATOR.'lib';
        $package = $public.DIRECTORY_SEPARATOR.'package';
        mkdir($lib, 0755, true);
        file_put_contents($lib.DIRECTORY_SEPARATOR.'InstallEngine.php', '<?php');
        file_put_contents($package.DIRECTORY_SEPARATOR.'jobazmoon-core.zip', 'x');
        file_put_contents($lib.DIRECTORY_SEPARATOR.'keep-me.php', '<?php');

        $rrmdir = new ReflectionMethod(InstallEngine::class, 'rrmdir');
        $rrmdir->setAccessible(true);
        $rrmdir->invoke($engine, $package);

        $this->assertDirectoryDoesNotExist($package);

        @unlink($lib.DIRECTORY_SEPARATOR.'InstallEngine.php');
        $this->assertFileDoesNotExist($lib.DIRECTORY_SEPARATOR.'InstallEngine.php');
        $this->assertFileExists($lib.DIRECTORY_SEPARATOR.'keep-me.php');
        $this->assertDirectoryExists($lib);

        unlink($lib.DIRECTORY_SEPARATOR.'keep-me.php');
        $empty = count(array_diff((array) scandir($lib), ['.', '..'])) === 0;
        $this->assertTrue($empty);
        rmdir($lib);
        $this->assertDirectoryDoesNotExist($lib);
    }

    public function test_job_path_uses_installation_root_not_hardcoded_absolute(): void
    {
        $engine = $this->engine();
        $expected = $this->tmpRoot.DIRECTORY_SEPARATOR.'job'
            .DIRECTORY_SEPARATOR.'storage'
            .DIRECTORY_SEPARATOR.'framework'
            .DIRECTORY_SEPARATOR.'views';

        $this->assertSame($expected, $engine->jobPath('storage/framework/views'));
        $this->assertSame($expected, $engine->jobPath('\\storage\\framework\\views'));
    }

    public function test_ensure_framework_directories_creates_missing_cache_paths_before_boot(): void
    {
        $job = $this->tmpRoot.DIRECTORY_SEPARATOR.'job';
        mkdir($job, 0755, true);
        // Simulate incomplete ZIP extract: only top-level storage, no framework children.
        mkdir($job.DIRECTORY_SEPARATOR.'storage', 0755, true);

        $engine = $this->engine();
        $this->assertDirectoryDoesNotExist($engine->jobPath('storage/framework/views'));
        $this->assertDirectoryDoesNotExist($engine->jobPath('storage/framework/sessions'));
        $this->assertDirectoryDoesNotExist($engine->jobPath('storage/framework/cache'));
        $this->assertDirectoryDoesNotExist($engine->jobPath('bootstrap/cache'));

        $engine->ensureFrameworkDirectories();

        foreach ($engine->requiredWritableRelativePaths() as $rel) {
            $path = $engine->jobPath($rel);
            $this->assertDirectoryExists($path, 'Missing required path: '.$rel);
            $this->assertTrue(is_writable($path), 'Not writable: '.$rel);
        }

        // Idempotent
        $engine->ensureFrameworkDirectories();
        $this->assertDirectoryExists($engine->jobPath('storage/framework/views'));
    }

    public function test_permission_report_includes_nested_framework_paths(): void
    {
        mkdir($this->tmpRoot.DIRECTORY_SEPARATOR.'job', 0755, true);
        $engine = $this->engine();
        $report = $engine->permissionReport();
        $labels = array_column($report['items'], 'label');

        $this->assertContains('storage/framework/cache', $labels);
        $this->assertContains('storage/framework/sessions', $labels);
        $this->assertContains('storage/framework/views', $labels);
        $this->assertContains('storage/logs', $labels);
        $this->assertContains('bootstrap/cache', $labels);
        $this->assertTrue($report['ok']);
    }

    /**
     * Regression: Laravel throws "Please provide a valid cache path." when view compiled dir is missing/empty.
     */
    public function test_blade_compiler_rejects_empty_cache_path_but_accepts_ensured_views_dir(): void
    {
        if (! class_exists(BladeCompiler::class)) {
            $this->markTestSkipped('Illuminate BladeCompiler not available.');
        }

        $fs = new Filesystem;

        try {
            new BladeCompiler($fs, '');
            $this->fail('Expected InvalidArgumentException for empty cache path.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Please provide a valid cache path.', $e->getMessage());
        }

        mkdir($this->tmpRoot.DIRECTORY_SEPARATOR.'job', 0755, true);
        $engine = $this->engine();
        $engine->ensureFrameworkDirectories();
        $compiled = $engine->jobPath('storage/framework/views');

        $compiler = new BladeCompiler($fs, $compiled);
        $ref = new \ReflectionProperty($compiler, 'cachePath');
        $ref->setAccessible(true);
        $this->assertSame($compiled, $ref->getValue($compiler));
        $this->assertDirectoryExists($compiled);
    }

    public function test_installer_source_ensures_dirs_before_laravel_boot_and_migrate(): void
    {
        $src = (string) file_get_contents(__DIR__.'/../../../cpanel-installer/lib/InstallEngine.php');
        $ensurePos = strpos($src, 'ensureFrameworkDirectories()');
        $migratePos = strpos($src, "artisan('migrate'");
        $laravelAppPos = strpos($src, 'private function laravelApp()');
        $this->assertNotFalse($ensurePos);
        $this->assertNotFalse($migratePos);
        $this->assertNotFalse($laravelAppPos);
        $this->assertLessThan($migratePos, $ensurePos);

        $laravelFn = substr($src, (int) $laravelAppPos);
        $this->assertMatchesRegularExpression(
            '/function laravelApp\(\)[\s\S]*?ensureFrameworkDirectories\(\)/',
            $laravelFn
        );
    }

    private function engine(): InstallEngine
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        if (! is_dir($public)) {
            mkdir($public, 0755, true);
        }
        if (! is_dir($public.DIRECTORY_SEPARATOR.'package')) {
            mkdir($public.DIRECTORY_SEPARATOR.'package', 0755, true);
        }

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
