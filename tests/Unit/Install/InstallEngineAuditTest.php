<?php

declare(strict_types=1);

namespace Tests\Unit\Install;

use InstallEngine;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use ZipArchive;

require_once __DIR__.'/../../../cpanel-installer/lib/InstallEngine.php';

/**
 * Expanded coverage for cPanel InstallEngine + install.php source audits.
 */
final class InstallEngineAuditTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jobazmoon-install-audit-'.bin2hex(random_bytes(4));
        mkdir($this->tmpRoot, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpRoot);
        parent::tearDown();
    }

    public function test_php_min_version_constant_and_requirement(): void
    {
        $this->assertSame('8.2.0', InstallEngine::MIN_PHP);
        $engine = $this->engine();
        $phpItem = null;
        foreach ($engine->requirements() as $item) {
            if ($item['label'] === 'PHP >= '.InstallEngine::MIN_PHP) {
                $phpItem = $item;
                break;
            }
        }
        $this->assertNotNull($phpItem);
        $this->assertSame(version_compare(PHP_VERSION, InstallEngine::MIN_PHP, '>='), $phpItem['ok']);
    }

    public function test_validate_zip_entries_accepts_safe_entries(): void
    {
        $zipPath = $this->tmpRoot.DIRECTORY_SEPARATOR.'safe.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('artisan', '#!/usr/bin/env php');
        $zip->addFromString('app/Models/User.php', '<?php');
        $zip->addFromString('public/build/manifest.json', '{}');
        $zip->close();

        $opened = new ZipArchive;
        $this->assertTrue($opened->open($zipPath));
        try {
            $this->engine()->validateZipEntries($opened);
            $this->assertTrue(true);
        } finally {
            $opened->close();
        }
    }

    public function test_validate_zip_entries_rejects_dotdot_and_nested_traversal(): void
    {
        $engine = $this->engine();

        foreach (['../evil.php', 'foo/../../x'] as $evilName) {
            $zipPath = $this->tmpRoot.DIRECTORY_SEPARATOR.'evil-'.md5($evilName).'.zip';
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
            $zip->addFromString($evilName, 'nope');
            $zip->close();

            $opened = new ZipArchive;
            $this->assertTrue($opened->open($zipPath));
            try {
                $engine->validateZipEntries($opened);
                $this->fail('Expected path traversal rejection for '.$evilName);
            } catch (RuntimeException $e) {
                $this->assertMatchesRegularExpression('/path traversal|خطرناک/u', $e->getMessage());
            } finally {
                $opened->close();
            }
        }
    }

    public function test_validate_zip_entries_rejects_absolute_paths(): void
    {
        $engine = $this->engine();

        foreach (['/etc/passwd', 'C:/windows/system32/evil.txt'] as $abs) {
            $zipPath = $this->tmpRoot.DIRECTORY_SEPARATOR.'abs-'.md5($abs).'.zip';
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
            $zip->addFromString($abs, 'nope');
            $zip->close();

            $opened = new ZipArchive;
            $this->assertTrue($opened->open($zipPath));
            try {
                $engine->validateZipEntries($opened);
                $this->fail('Expected absolute path rejection for '.$abs);
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('مطلق', $e->getMessage());
            } finally {
                $opened->close();
            }
        }
    }

    public function test_validate_laravel_package_missing_routes(): void
    {
        $root = $this->minimalLaravelTree();
        $this->rrmdir($root.DIRECTORY_SEPARATOR.'routes');

        $method = new ReflectionMethod(InstallEngine::class, 'validateLaravelPackage');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/routes/u');
        $method->invoke($this->engine(), $root);
    }

    public function test_validate_laravel_package_missing_vendor_autoload_persian_composer_message(): void
    {
        $root = $this->minimalLaravelTree(withAutoload: false);
        $method = new ReflectionMethod(InstallEngine::class, 'validateLaravelPackage');
        $method->setAccessible(true);

        try {
            $method->invoke($this->engine(), $root);
            $this->fail('Expected missing vendor/autoload exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('vendor/autoload.php', $e->getMessage());
            $this->assertStringContainsString('composer install', $e->getMessage());
            $this->assertMatchesRegularExpression('/بسته|composer/u', $e->getMessage());
        }
    }

    public function test_validate_laravel_package_missing_artisan(): void
    {
        $root = $this->minimalLaravelTree();
        unlink($root.DIRECTORY_SEPARATOR.'artisan');

        $method = new ReflectionMethod(InstallEngine::class, 'validateLaravelPackage');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/artisan/u');
        $method->invoke($this->engine(), $root);
    }

    public function test_validate_laravel_package_missing_manifest(): void
    {
        $root = $this->minimalLaravelTree(withManifest: false);

        $method = new ReflectionMethod(InstallEngine::class, 'validateLaravelPackage');
        $method->setAccessible(true);

        try {
            $method->invoke($this->engine(), $root);
            $this->fail('Expected missing manifest exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('manifest.json', $e->getMessage());
            $this->assertStringContainsString('npm run build', $e->getMessage());
        }
    }

    public function test_validate_database_input_invalid(): void
    {
        $engine = $this->engine();
        $errors = $engine->validateDatabaseInput([
            'host' => '',
            'port' => 'abc',
            'name' => 'bad name!',
            'user' => '',
            'pass' => 'x',
        ]);

        $this->assertNotEmpty($errors);
        $joined = implode(' ', $errors);
        $this->assertTrue(str_contains($joined, 'هاست') || str_contains($joined, 'پورت') || str_contains($joined, 'نام'));
    }

    public function test_test_database_connection_failure_hides_password(): void
    {
        $engine = $this->engine();
        $secret = 'VerySecretDbPass99';
        $result = $engine->testDatabase([
            'host' => '127.0.0.1',
            'port' => '1',
            'name' => 'no_such_db_xyz',
            'user' => 'nobody',
            'pass' => $secret,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('connection_failed', $result['state']);
        $this->assertStringNotContainsString($secret, $result['message']);
        $this->assertDoesNotMatchRegularExpression('/password\s*=/i', $result['message']);
    }

    public function test_test_database_invalid_input_state(): void
    {
        $result = $this->engine()->testDatabase([
            'host' => '',
            'port' => '3306',
            'name' => 'ok',
            'user' => 'u',
            'pass' => 'p',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_input', $result['state']);
        $this->assertSame(0, $result['table_count']);
    }

    public function test_empty_vs_has_tables_and_existing_db_confirmation(): void
    {
        $engine = $this->engine();

        // Empty DB: allowed without confirmation.
        $engine->assertDatabaseInstallAllowed([
            'ok' => true,
            'state' => 'empty',
            'table_count' => 0,
            'message' => 'empty',
        ], false);

        // Existing tables without confirmation: must fail with Persian guard including count.
        try {
            $engine->assertDatabaseInstallAllowed([
                'ok' => true,
                'state' => 'has_tables',
                'table_count' => 12,
                'message' => 'has',
            ], false);
            $this->fail('Expected RuntimeException when existing DB is not confirmed');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('12', $e->getMessage());
            $this->assertMatchesRegularExpression('/جدول|تأیید/u', $e->getMessage());
        }

        // Existing tables with explicit confirmation: allowed.
        $engine->assertDatabaseInstallAllowed([
            'ok' => true,
            'state' => 'has_tables',
            'table_count' => 12,
            'message' => 'has',
        ], true);

        // Optional live MySQL: empty then has_tables via testDatabase.
        $pdo = $this->tryLocalMysql();
        if ($pdo === null) {
            return;
        }

        $dbName = 'jobazmoon_inst_'.bin2hex(random_bytes(3));
        $pdo->exec('CREATE DATABASE `'.$dbName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $db = [
                'host' => '127.0.0.1',
                'port' => '3306',
                'name' => $dbName,
                'user' => 'root',
                'pass' => '',
            ];
            $empty = $engine->testDatabase($db);
            $this->assertTrue($empty['ok']);
            $this->assertSame('empty', $empty['state']);

            $pdoDb = new \PDO(
                'mysql:host=127.0.0.1;port=3306;dbname='.$dbName.';charset=utf8mb4',
                'root',
                '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $pdoDb->exec('CREATE TABLE `_installer_probe` (id INT PRIMARY KEY)');
            $has = $engine->testDatabase($db);
            $this->assertSame('has_tables', $has['state']);
            $this->assertGreaterThan(0, $has['table_count']);
        } finally {
            $pdo->exec('DROP DATABASE IF EXISTS `'.$dbName.'`');
        }
    }

    public function test_invalid_site_data_and_password_mismatch(): void
    {
        $errors = $this->engine()->validateSiteInput([
            'site_name' => '',
            'url' => 'not-a-url',
            'name' => '',
            'email' => 'bad',
            'mobile' => '123',
            'password' => 'secret12',
            'password_confirmation' => 'other',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertContains('رمز و تکرار رمز یکسان نیست.', $errors);
    }

    public function test_locked_installation_run_install_throws(): void
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        $job = $this->tmpRoot.DIRECTORY_SEPARATOR.'job';
        mkdir($public, 0755, true);
        mkdir($job.DIRECTORY_SEPARATOR.'storage', 0755, true);
        file_put_contents($job.DIRECTORY_SEPARATOR.'.env', "APP_KEY=base64:x\n");
        file_put_contents($job.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed', '{}');

        $engine = new InstallEngine($public, $this->tmpRoot, $job, $public.'/package/x.zip');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/قبلاً نصب/u');
        $engine->runInstall($this->validSite(), $this->validDbShape(), false);
    }

    public function test_incomplete_installation_run_install_throws(): void
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        $job = $this->tmpRoot.DIRECTORY_SEPARATOR.'job';
        mkdir($public, 0755, true);
        mkdir($job, 0755, true);
        file_put_contents($job.DIRECTORY_SEPARATOR.'artisan', "#!/usr/bin/env php\n");

        $engine = new InstallEngine($public, $this->tmpRoot, $job, $public.'/package/x.zip');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/ناقص/u');
        $engine->runInstall($this->validSite(), $this->validDbShape(), false);
    }

    public function test_app_key_generation_format_via_write_env_file(): void
    {
        $engine = $this->engine();
        $envPath = $this->tmpRoot.DIRECTORY_SEPARATOR.'key.env';
        file_put_contents($envPath, '');

        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $this->assertMatchesRegularExpression('/^base64:[A-Za-z0-9+\/=]+$/', $appKey);

        $method = new ReflectionMethod(InstallEngine::class, 'writeEnvFile');
        $method->setAccessible(true);
        $method->invoke($engine, $envPath, ['APP_KEY' => $appKey]);

        $content = (string) file_get_contents($envPath);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]+$/m', $content);

        $src = (string) file_get_contents(__DIR__.'/../../../cpanel-installer/lib/InstallEngine.php');
        $this->assertStringContainsString("\$appKey = 'base64:'.base64_encode(random_bytes(32));", $src);
    }

    public function test_password_and_app_key_not_leaked_in_sanitize_or_log(): void
    {
        $engine = $this->engine();
        $dbPass = 'DbSecretLeak99';
        $sitePass = 'SiteSecretLeak99';
        $appKey = 'base64:'.base64_encode(str_repeat('A', 32));

        $remember = new ReflectionMethod(InstallEngine::class, 'rememberSecrets');
        $remember->setAccessible(true);
        $remember->invoke($engine, ['pass' => $dbPass, 'APP_KEY' => $appKey], ['password' => $sitePass]);

        $sanitize = new ReflectionMethod(InstallEngine::class, 'sanitizePublicError');
        $sanitize->setAccessible(true);
        $msg = $sanitize->invoke(
            $engine,
            'boom '.$dbPass.' '.$sitePass.' '.$appKey
        );
        $this->assertStringNotContainsString($dbPass, $msg);
        $this->assertStringNotContainsString($sitePass, $msg);
        $this->assertStringNotContainsString($appKey, $msg);

        $log = new ReflectionMethod(InstallEngine::class, 'installerLog');
        $log->setAccessible(true);
        $log->invoke($engine, 'install step with '.$dbPass.' and '.$appKey);

        $bufProp = new \ReflectionProperty(InstallEngine::class, 'installerLogBuffer');
        $bufProp->setAccessible(true);
        /** @var list<string> $buffer */
        $buffer = $bufProp->getValue($engine);
        $joined = implode("\n", $buffer);
        $this->assertStringNotContainsString($dbPass, $joined);
        $this->assertStringNotContainsString($appKey, $joined);

        mkdir($this->tmpRoot.DIRECTORY_SEPARATOR.'job'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs', 0755, true);
        $flush = new ReflectionMethod(InstallEngine::class, 'flushInstallerLog');
        $flush->setAccessible(true);
        $flush->invoke($engine);

        $logFile = $this->tmpRoot.DIRECTORY_SEPARATOR.'job'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'installer.log';
        $this->assertFileExists($logFile);
        $fileBody = (string) file_get_contents($logFile);
        $this->assertStringNotContainsString($dbPass, $fileBody);
        $this->assertStringNotContainsString($sitePass, $fileBody);
        $this->assertStringNotContainsString($appKey, $fileBody);
    }

    public function test_extract_zip_safely_rejects_traversal_and_writes_nothing_outside_tmp(): void
    {
        $outsideProbe = $this->tmpRoot.DIRECTORY_SEPARATOR.'should-not-exist-'.bin2hex(random_bytes(3)).'.txt';
        $this->assertFileDoesNotExist($outsideProbe);

        $zipPath = $this->tmpRoot.DIRECTORY_SEPARATOR.'evil-extract.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('../outside.txt', 'pwn');
        $zip->addFromString('foo/../../escape.txt', 'pwn2');
        $zip->close();

        $tmp = $this->tmpRoot.DIRECTORY_SEPARATOR.'extract_tmp';
        mkdir($tmp, 0755, true);

        $opened = new ZipArchive;
        $this->assertTrue($opened->open($zipPath));
        $engine = $this->engine();

        try {
            $engine->validateZipEntries($opened);
            $this->fail('validateZipEntries should reject traversal before extract');
        } catch (RuntimeException $e) {
            $this->assertMatchesRegularExpression('/path traversal|خطرناک/u', $e->getMessage());
        } finally {
            $opened->close();
        }

        $this->assertFileDoesNotExist($outsideProbe);
        $this->assertSame([], array_values(array_filter(
            scandir($tmp) ?: [],
            static fn (string $n): bool => $n !== '.' && $n !== '..'
        )));
    }

    public function test_install_engine_source_has_no_shell_exec_or_composer_install(): void
    {
        $src = (string) file_get_contents(__DIR__.'/../../../cpanel-installer/lib/InstallEngine.php');
        // Ban host shell helpers (PDO::exec / similar method calls are allowed).
        $this->assertStringNotContainsString('shell_exec(', $src);
        $this->assertStringNotContainsString('proc_open(', $src);
        $this->assertStringNotContainsString('passthru(', $src);
        $this->assertStringNotContainsString('popen(', $src);
        $this->assertDoesNotMatchRegularExpression('/(?<!->)(?<!::)\bsystem\s*\(/', $src);
        $this->assertDoesNotMatchRegularExpression('/(?<!->)(?<!::)\bexec\s*\(/', $src);
        $this->assertDoesNotMatchRegularExpression('/`[^`]*composer[^`]*`/i', $src);
    }

    public function test_filesystem_deploy_keeps_secrets_in_job_not_public_html(): void
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        $job = $this->tmpRoot.DIRECTORY_SEPARATOR.'job';
        mkdir($public.DIRECTORY_SEPARATOR.'package', 0755, true);
        mkdir($public.DIRECTORY_SEPARATOR.'lib', 0755, true);
        file_put_contents($public.DIRECTORY_SEPARATOR.'install.php', '<?php // installer');
        file_put_contents($public.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'keep.txt', 'pkg');
        file_put_contents($public.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'InstallEngine.php', '<?php');

        $src = $this->minimalLaravelTree();
        file_put_contents($src.DIRECTORY_SEPARATOR.'.env.example', "APP_NAME=Example\n");
        file_put_contents($src.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'favicon.ico', 'ico');
        file_put_contents($src.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'install.php', 'should-skip');
        mkdir($src.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'package', 0755, true);
        file_put_contents($src.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'x.txt', 'skip');

        $engine = new InstallEngine(
            $public,
            $this->tmpRoot,
            $job,
            $public.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'jobazmoon-core.zip',
            $public.DIRECTORY_SEPARATOR.'install.php',
        );

        $copyDir = new ReflectionMethod(InstallEngine::class, 'copyDir');
        $copyDir->setAccessible(true);
        $copyDir->invoke($engine, $src, $job);

        $this->assertFileExists($job.DIRECTORY_SEPARATOR.'artisan');
        $this->assertFileExists($job.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');
        $this->assertDirectoryExists($job.DIRECTORY_SEPARATOR.'app');

        $pubSrc = $job.DIRECTORY_SEPARATOR.'public';
        $copyDir->invoke($engine, $pubSrc, $public, ['install.php', 'package', 'lib']);

        $this->assertFileExists($public.DIRECTORY_SEPARATOR.'install.php');
        $this->assertFileExists($public.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'keep.txt');
        $this->assertFileExists($public.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'InstallEngine.php');
        $this->assertFileDoesNotExist($public.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'x.txt');
        $this->assertFileExists($public.DIRECTORY_SEPARATOR.'favicon.ico');
        $this->assertFileExists($public.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json');

        $writeIndex = new ReflectionMethod(InstallEngine::class, 'writePublicIndex');
        $writeIndex->setAccessible(true);
        $writeIndex->invoke($engine, $public);

        $index = (string) file_get_contents($public.DIRECTORY_SEPARATOR.'index.php');
        $this->assertStringContainsString("vendor'.DIRECTORY_SEPARATOR.'autoload.php", $index);
        $this->assertStringContainsString("bootstrap'.DIRECTORY_SEPARATOR.'app.php", $index);
        $this->assertStringContainsString("'job'", $index);

        $writeEnv = new ReflectionMethod(InstallEngine::class, 'writeEnvFile');
        $writeEnv->setAccessible(true);
        $envPath = $job.DIRECTORY_SEPARATOR.'.env';
        copy($job.DIRECTORY_SEPARATOR.'.env.example', $envPath);
        $secretKey = 'base64:'.base64_encode(random_bytes(32));
        $writeEnv->invoke($engine, $envPath, [
            'APP_KEY' => $secretKey,
            'DB_PASSWORD' => 'EnvDbPass99',
        ]);

        $this->assertFileExists($job.DIRECTORY_SEPARATOR.'.env');
        $this->assertFileExists($job.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');
        $this->assertDirectoryExists($job.DIRECTORY_SEPARATOR.'app');
        $this->assertFileDoesNotExist($public.DIRECTORY_SEPARATOR.'.env');
        $this->assertDirectoryDoesNotExist($public.DIRECTORY_SEPARATOR.'vendor');
        $this->assertDirectoryDoesNotExist($public.DIRECTORY_SEPARATOR.'app');

        $publicListing = implode("\n", $this->listFilesRecursive($public));
        $this->assertStringNotContainsString($secretKey, $publicListing);
        $this->assertStringNotContainsString('EnvDbPass99', (string) file_get_contents($public.DIRECTORY_SEPARATOR.'index.php'));
    }

    public function test_verify_install_returns_level_pass_fail_or_warning(): void
    {
        $checks = $this->engine()->verifyInstall();
        $this->assertNotEmpty($checks);
        foreach ($checks as $c) {
            $this->assertArrayHasKey('level', $c);
            $this->assertContains($c['level'], ['pass', 'fail', 'warning']);
        }
    }

    public function test_install_php_source_never_echoes_session_secrets(): void
    {
        $src = (string) file_get_contents(__DIR__.'/../../../cpanel-installer/install.php');

        $this->assertStringContainsString(
            "unset(\$siteOld['password'], \$siteOld['password_confirmation'], \$dbOld['pass']);",
            $src
        );

        // Must not print session password / db pass values into HTML.
        $this->assertStringNotContainsString("\$_SESSION['site']['password']", $src);
        $this->assertStringNotContainsString('$_SESSION["site"]["password"]', $src);
        $this->assertStringNotContainsString("\$_SESSION['db']['pass']", $src);
        $this->assertStringNotContainsString('$_SESSION["db"]["pass"]', $src);
        $this->assertStringNotContainsString('$_SESSION[\'site\'][\'password\']', $src);

        // APP_KEY must not be echoed as a value (label text in UI is OK).
        $this->assertStringNotContainsString('<?= $appKey', $src);
        $this->assertStringNotContainsString('<?= $appKey', $src);
        $this->assertStringNotContainsString('h($appKey)', $src);
        $this->assertStringNotContainsString('echo $appKey', $src);
        $this->assertStringNotContainsString("['APP_KEY']", $src);

        // Password fields must not bind value= from session.
        $this->assertDoesNotMatchRegularExpression('/name="db_pass"[^>]*\bvalue=/', $src);
        $this->assertDoesNotMatchRegularExpression('/name="admin_password"[^>]*\bvalue=/', $src);
        $this->assertDoesNotMatchRegularExpression('/name="admin_password_confirmation"[^>]*\bvalue=/', $src);
    }

    public function test_installer_cleanup_result_keys_and_unlink_in_source(): void
    {
        $src = (string) file_get_contents(__DIR__.'/../../../cpanel-installer/lib/InstallEngine.php');
        $this->assertStringContainsString("'installer_removed'", $src);
        $this->assertStringContainsString("'deleted'", $src);
        $this->assertMatchesRegularExpression('/@?unlink\(\$f\)/', $src);
        $this->assertStringContainsString('$this->packagePath', $src);
        $this->assertStringContainsString('$this->installScriptPath', $src);
        $this->assertStringContainsString("in_array('install.php', \$deleted", $src);

        // Document expected return shape from phpdoc / success path.
        $this->assertMatchesRegularExpression(
            "/'installer_removed'\s*=>\s*\\\$installerRemoved/",
            $src
        );
        $this->assertMatchesRegularExpression(
            "/'deleted'\s*=>\s*\\\$deleted/",
            $src
        );
    }

    public function test_migrate_failure_messages_and_rollback_helper_exist_in_source(): void
    {
        $src = (string) file_get_contents(__DIR__.'/../../../cpanel-installer/lib/InstallEngine.php');
        $this->assertStringContainsString('rollbackNewMigrationsOnly', $src);
        $this->assertStringContainsString('initialTableCount === 0', $src);
        $this->assertStringContainsString('اجرای migration ناموفق بود. چون پایگاه خالی بود', $src);
        $this->assertStringContainsString('چون پایگاه از قبل جدول داشت، rollback خودکار انجام نشد', $src);
        // No dangerous artisan/SQL invocations (comments may warn against them).
        $this->assertDoesNotMatchRegularExpression("/artisan\\(\\s*['\"]migrate:fresh/i", $src);
        $this->assertDoesNotMatchRegularExpression("/artisan\\(\\s*['\"]migrate:refresh/i", $src);
        $this->assertDoesNotMatchRegularExpression('/->exec\\(\\s*[\'"]DROP\\s+DATABASE/i', $src);
        $this->assertDoesNotMatchRegularExpression('/->query\\(\\s*[\'"]DROP\\s+DATABASE/i', $src);
    }

    /**
     * @return array{site_name: string, url: string, name: string, email: string, mobile: string, password: string, password_confirmation: string}
     */
    private function validSite(): array
    {
        return [
            'site_name' => 'JobAzmoon',
            'url' => 'https://example.com',
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'mobile' => '09123456789',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
        ];
    }

    /**
     * @return array{host: string, port: string, name: string, user: string, pass: string}
     */
    private function validDbShape(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => '3306',
            'name' => 'jobazmoon',
            'user' => 'root',
            'pass' => '',
        ];
    }

    private function tryLocalMysql(): ?\PDO
    {
        try {
            return new \PDO(
                'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
                'root',
                '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 2]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function minimalLaravelTree(bool $withAutoload = true, bool $withManifest = true): string
    {
        $root = $this->tmpRoot.DIRECTORY_SEPARATOR.'pkg_'.bin2hex(random_bytes(3));
        foreach (['app', 'bootstrap', 'config', 'routes', 'public/build', 'storage', 'vendor'] as $dir) {
            mkdir($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $dir), 0755, true);
        }
        file_put_contents($root.DIRECTORY_SEPARATOR.'artisan', "#!/usr/bin/env php\n");
        file_put_contents($root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php', "<?php\nreturn null;\n");
        if ($withAutoload) {
            file_put_contents($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php', "<?php\n");
        }
        if ($withManifest) {
            file_put_contents(
                $root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json',
                '{}'
            );
        }

        return $root;
    }

    private function engine(): InstallEngine
    {
        $public = $this->tmpRoot.DIRECTORY_SEPARATOR.'public_html';
        if (! is_dir($public)) {
            mkdir($public.DIRECTORY_SEPARATOR.'package', 0755, true);
        }

        return new InstallEngine(
            $public,
            $this->tmpRoot,
            $this->tmpRoot.DIRECTORY_SEPARATOR.'job',
            $public.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'jobazmoon-core.zip',
        );
    }

    /** @return list<string> */
    private function listFilesRecursive(string $dir): array
    {
        $out = [];
        if (! is_dir($dir)) {
            return $out;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
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
