<?php

declare(strict_types=1);

/**
 * JobAzmoon cPanel installer engine (standalone — no Composer autoload for this file).
 */
final class InstallEngine
{
    public const MIN_PHP = '8.2.0';

    public const PACKAGE_FILE = 'jobazmoon-core.zip';

    public const MIN_DISK_BYTES = 400 * 1024 * 1024;

    /** @var list<callable(): void> */
    private array $rollbackStack = [];

    public function __construct(
        public readonly string $publicHtml,
        public readonly string $homeDir,
        public readonly string $jobDir,
        public readonly string $packagePath,
        public readonly string $installScriptPath = '',
    ) {}

    public function isLocked(): bool
    {
        return is_file($this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed')
            && is_file($this->jobDir.DIRECTORY_SEPARATOR.'.env');
    }

    public function defaultUrl(): string
    {
        $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return ($https ? 'https' : 'http').'://'.$host;
    }

    /**
     * @return list<array{label: string, ok: bool, fix: string, warn: bool}>
     */
    public function requirements(): array
    {
        $ext = static fn (string $n, bool $warn = false): array => [
            'label' => 'افزونه PHP: '.$n,
            'ok' => extension_loaded($n),
            'fix' => 'در cPanel → Select PHP Version → Extensions گزینه '.$n.' را فعال کنید.',
            'warn' => $warn,
        ];

        $writablePaths = [
            ['label' => 'نوشتنی بودن public_html', 'path' => $this->publicHtml],
            ['label' => 'امکان ایجاد/نوشتن پوشه job', 'path' => $this->jobDir],
        ];

        $writableItems = [];
        foreach ($writablePaths as $item) {
            $path = $item['path'];
            $ok = is_dir($path) ? is_writable($path) : is_writable(dirname($path));
            $writableItems[] = [
                'label' => $item['label'],
                'ok' => $ok,
                'fix' => 'مجوز نوشتن پوشه را در File Manager بررسی کنید (معمولاً ۷۵۵ یا ۷۷۵).',
                'warn' => false,
            ];
        }

        return array_merge([
            [
                'label' => 'PHP >= '.self::MIN_PHP,
                'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
                'fix' => 'در cPanel نسخه PHP را روی ۸.۲ یا ۸.۳ بگذارید. نسخه فعلی: '.PHP_VERSION,
                'warn' => false,
            ],
            $ext('pdo'),
            $ext('pdo_mysql'),
            $ext('openssl'),
            $ext('mbstring'),
            $ext('xml'),
            $ext('dom'),
            $ext('ctype'),
            $ext('json'),
            $ext('fileinfo'),
            $ext('gd'),
            $ext('zip'),
            $ext('curl', true),
            $ext('intl', true),
            [
                'label' => 'Session',
                'ok' => function_exists('session_start'),
                'fix' => 'session در PHP غیرفعال است.',
                'warn' => false,
            ],
            [
                'label' => 'وجود بسته '.self::PACKAGE_FILE,
                'ok' => is_file($this->packagePath),
                'fix' => 'پوشه package و فایل '.self::PACKAGE_FILE.' را کنار install.php آپلود کنید.',
                'warn' => false,
            ],
            [
                'label' => 'manifest.json در بسته (frontend build)',
                'ok' => $this->packageHasFrontendBuild(),
                'fix' => 'قبل از بسته‌بندی روی سیستم خودتان npm run build اجرا کنید تا public/build/manifest.json در zip باشد.',
                'warn' => false,
            ],
            [
                'label' => 'فضای دیسک کافی',
                'ok' => ((int) @disk_free_space($this->publicHtml)) >= self::MIN_DISK_BYTES,
                'fix' => 'حداقل حدود ۴۰۰ مگابایت فضای خالی لازم است.',
                'warn' => false,
            ],
        ], $writableItems, [
            [
                'label' => 'Horizon (pcntl)',
                'ok' => extension_loaded('pcntl'),
                'fix' => 'Horizon روی این هاست قابل اجرا نیست؛ از queue:work با cron استفاده کنید.',
                'warn' => true,
            ],
            [
                'label' => 'Horizon (posix)',
                'ok' => extension_loaded('posix'),
                'fix' => 'Horizon روی این هاست قابل اجرا نیست؛ از queue:work با cron استفاده کنید.',
                'warn' => true,
            ],
        ]);
    }

    /**
     * @return list<array{label: string, ok: bool, fix: string, warn: bool}>
     */
    public function requiredFailures(array $items): array
    {
        return array_values(array_filter($items, static fn (array $i): bool => ! $i['ok'] && empty($i['warn'])));
    }

    /**
     * @param  array{host: string, port: string, name: string, user: string, pass: string}  $db
     * @return list<string>
     */
    public function validateDatabaseInput(array $db): array
    {
        $errors = [];

        if ($db['host'] === '') {
            $errors[] = 'هاست پایگاه‌داده الزامی است.';
        }
        if (! preg_match('/^\d{1,5}$/', $db['port']) || (int) $db['port'] < 1 || (int) $db['port'] > 65535) {
            $errors[] = 'پورت پایگاه‌داده باید عددی بین ۱ تا ۶۵۵۳۵ باشد.';
        }
        if ($db['name'] === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $db['name'])) {
            $errors[] = 'نام پایگاه‌داده فقط می‌تواند شامل حروف انگلیسی، عدد و _ باشد.';
        }
        if ($db['user'] === '') {
            $errors[] = 'نام کاربری پایگاه‌داده الزامی است.';
        }
        if (strlen($db['name']) > 64) {
            $errors[] = 'نام پایگاه‌داده حداکثر ۶۴ کاراکتر است.';
        }

        return $errors;
    }

    /**
     * @param  array{host: string, port: string, name: string, user: string, pass: string}  $db
     * @return array{ok: bool, message: string, state: string, table_count: int}
     */
    public function testDatabase(array $db): array
    {
        $inputErrors = $this->validateDatabaseInput($db);
        if ($inputErrors !== []) {
            return [
                'ok' => false,
                'message' => $inputErrors[0],
                'state' => 'invalid_input',
                'table_count' => 0,
            ];
        }

        try {
            $pdo = $this->pdoConnect($db, false);
            $safeName = $this->safeDatabaseName($db['name']);
            $pdo->exec(
                'CREATE DATABASE IF NOT EXISTS `'.$safeName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            $pdoDb = $this->pdoConnect($db, true);
            $pdoDb->query('SELECT 1');
            $tableCount = (int) $pdoDb->query(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '.$pdoDb->quote($db['name'])
            )->fetchColumn();

            $state = $tableCount === 0 ? 'empty' : 'has_tables';

            return [
                'ok' => true,
                'message' => $tableCount === 0
                    ? 'اتصال برقرار شد. پایگاه‌داده خالی است و آماده نصب.'
                    : 'اتصال برقرار شد. این پایگاه '.$tableCount.' جدول دارد — برای ادامه باید تأیید کنید.',
                'state' => $state,
                'table_count' => $tableCount,
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'message' => 'اتصال به MySQL برقرار نشد. هاست، نام کاربری، رمز و نام پایگاه‌داده را در cPanel بررسی کنید.',
                'state' => 'connection_failed',
                'table_count' => 0,
            ];
        }
    }

    /**
     * @param  array{site_name: string, url: string, name: string, email: string, mobile: string, password: string, password_confirmation?: string}  $site
     * @return list<string>
     */
    public function validateSiteInput(array $site): array
    {
        $errors = [];

        if (trim($site['site_name'] ?? '') === '') {
            $errors[] = 'نام سایت الزامی است.';
        }
        if (trim($site['url'] ?? '') === '' || ! filter_var($site['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'آدرس سایت باید یک URL معتبر باشد (مثلاً https://example.com).';
        }
        if (trim($site['name'] ?? '') === '') {
            $errors[] = 'نام مدیر الزامی است.';
        }
        if (! filter_var($site['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'ایمیل مدیر معتبر نیست.';
        }
        if (! preg_match('/^09\d{9}$/', (string) ($site['mobile'] ?? ''))) {
            $errors[] = 'موبایل باید ۱۱ رقم و با 09 شروع شود.';
        }
        $password = (string) ($site['password'] ?? '');
        if (strlen($password) < 8) {
            $errors[] = 'رمز مدیر حداقل ۸ کاراکتر باشد.';
        }
        if ($password !== (string) ($site['password_confirmation'] ?? '')) {
            $errors[] = 'رمز و تکرار رمز یکسان نیست.';
        }

        return $errors;
    }

    /**
     * @param  array{site_name: string, url: string, name: string, email: string, mobile: string, password: string}  $site
     * @param  array{host: string, port: string, name: string, user: string, pass: string}  $db
     * @return array{ok: bool, log: list<string>, warnings: list<string>, verify: list<array{label: string, ok: bool, detail: string}>, installer_removed: bool, deleted: list<string>}
     */
    public function runInstall(array $site, array $db, bool $confirmedExistingDb = false): array
    {
        if ($this->isLocked()) {
            throw new RuntimeException('این سایت قبلاً نصب شده است.');
        }

        $dbTest = $this->testDatabase($db);
        if (! $dbTest['ok']) {
            throw new RuntimeException($dbTest['message']);
        }
        if ($dbTest['state'] === 'has_tables' && ! $confirmedExistingDb) {
            throw new RuntimeException('پایگاه‌داده جدول دارد. برای ادامه باید تأیید صریح بدهید.');
        }

        $siteErrors = $this->validateSiteInput($site);
        if ($siteErrors !== []) {
            throw new RuntimeException($siteErrors[0]);
        }

        $this->rollbackStack = [];
        $log = [];
        $warnings = [];
        $tmp = null;
        $jobExisted = is_file($this->jobDir.DIRECTORY_SEPARATOR.'artisan');

        try {
            $push = static function (string $m) use (&$log): void {
                $log[] = $m;
            };

            if (! is_dir($this->jobDir) && ! mkdir($this->jobDir, 0755, true) && ! is_dir($this->jobDir)) {
                throw new RuntimeException('ساخت پوشه job ممکن نشد.');
            }
            if (! $jobExisted) {
                $this->onRollback(function (): void {
                    $this->rrmdir($this->jobDir);
                });
            }

            $zip = new ZipArchive;
            if ($zip->open($this->packagePath) !== true) {
                throw new RuntimeException('باز کردن بسته نصب ممکن نشد.');
            }
            $tmp = $this->publicHtml.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'_extract_'.bin2hex(random_bytes(4));
            mkdir($tmp, 0755, true);
            $this->onRollback(function () use ($tmp): void {
                if ($tmp !== null) {
                    $this->rrmdir($tmp);
                }
            });

            if (! $zip->extractTo($tmp)) {
                $zip->close();
                throw new RuntimeException('استخراج بسته نصب ممکن نشد.');
            }
            $zip->close();
            $push('بسته استخراج شد.');

            $source = $this->resolveExtractRoot($tmp);
            $this->copyDir($source, $this->jobDir);
            $push('فایل‌های Laravel در پوشه job کپی شد.');

            if (! is_file($this->jobDir.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json')) {
                throw new RuntimeException('فایل public/build/manifest.json یافت نشد. ابتدا npm run build اجرا کنید.');
            }

            $pubSrc = $this->jobDir.DIRECTORY_SEPARATOR.'public';
            if (is_dir($pubSrc)) {
                if (is_file($this->publicHtml.DIRECTORY_SEPARATOR.'index.html')) {
                    @rename(
                        $this->publicHtml.DIRECTORY_SEPARATOR.'index.html',
                        $this->publicHtml.DIRECTORY_SEPARATOR.'index.html.backup'
                    );
                }
                $this->copyDir($pubSrc, $this->publicHtml, ['install.php', 'package', 'lib']);
                $push('فایل‌های public به public_html منتقل شد.');
            }

            $this->writePublicIndex($this->publicHtml);
            $this->writeHtaccess($this->publicHtml);
            $push('index.php و .htaccess تنظیم شد.');

            $example = $this->jobDir.DIRECTORY_SEPARATOR.'.env.example';
            $envPath = $this->jobDir.DIRECTORY_SEPARATOR.'.env';
            if (is_file($example)) {
                copy($example, $envPath);
            }
            $appKey = 'base64:'.base64_encode(random_bytes(32));
            $this->writeEnvFile($envPath, [
                'APP_NAME' => $site['site_name'],
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => rtrim($site['url'], '/'),
                'APP_KEY' => $appKey,
                'APP_INSTALLED' => 'false',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $db['host'],
                'DB_PORT' => $db['port'],
                'DB_DATABASE' => $db['name'],
                'DB_USERNAME' => $db['user'],
                'DB_PASSWORD' => $db['pass'],
                'CACHE_STORE' => 'database',
                'SESSION_DRIVER' => 'database',
                'QUEUE_CONNECTION' => 'database',
                'TELESCOPE_ENABLED' => 'false',
                'SMS_ALLOW_LOG_FALLBACK' => 'false',
            ]);
            $this->onRollback(function () use ($envPath): void {
                if (is_file($envPath)) {
                    @unlink($envPath);
                }
            });
            $push('.env ساخته شد.');

            foreach ([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $db['host'],
                'DB_PORT' => $db['port'],
                'DB_DATABASE' => $db['name'],
                'DB_USERNAME' => $db['user'],
                'DB_PASSWORD' => $db['pass'],
                'APP_KEY' => $appKey,
                'APP_URL' => rtrim($site['url'], '/'),
            ] as $k => $v) {
                putenv($k.'='.$v);
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }

            $this->chmodTree($this->jobDir.DIRECTORY_SEPARATOR.'storage', 0775);
            @chmod($this->jobDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache', 0775);
            $perm = $this->permissionReport();
            if (! $perm['ok']) {
                throw new RuntimeException('برخی پوشه‌های storage یا bootstrap/cache قابل نوشتن نیستند.');
            }
            $push('مجوزهای پوشه بررسی شد.');

            $mig = $this->artisan('migrate', ['--force' => true]);
            $push('migrate اجرا شد.');
            if ($mig['code'] !== 0) {
                $this->artisan('migrate:rollback', ['--force' => true]);
                throw new RuntimeException('اجرای migration ناموفق بود. تغییرات migration برگردانده شد.');
            }
            $this->onRollback(function (): void {
                try {
                    $this->artisan('migrate:rollback', ['--force' => true]);
                } catch (Throwable) {
                }
            });

            try {
                $seed = $this->artisan('db:seed', ['--force' => true]);
                if ($seed['code'] === 0) {
                    $push('seed اجرا شد.');
                } else {
                    $warnings[] = 'seed کامل نشد — می‌توانید بعداً از پنل تنظیم کنید.';
                }
            } catch (Throwable) {
                $warnings[] = 'seed کامل نشد — می‌توانید بعداً از پنل تنظیم کنید.';
            }

            try {
                $this->artisan('storage:link', ['--force' => true]);
                $push('storage:link اجرا شد.');
            } catch (Throwable) {
                $link = $this->publicHtml.DIRECTORY_SEPARATOR.'storage';
                $target = $this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';
                if (! file_exists($link)) {
                    @symlink($target, $link);
                }
                $warnings[] = 'storage:link ممکن است روی هاست محدود باشد — symlink دستی بررسی شود.';
            }

            $this->createAdmin($site);
            $push('کاربر مدیر ذخیره شد.');

            try {
                $this->artisan('optimize:clear');
                $this->artisan('config:cache');
                $this->artisan('route:cache');
                $this->artisan('view:cache');
                $push('کش production ساخته شد.');
            } catch (Throwable) {
                $warnings[] = 'برخی دستورات cache روی این هاست اجرا نشدند.';
            }

            if (class_exists(App\Models\Setting::class)) {
                try {
                    App\Models\Setting::set('site_name', $site['site_name'], 'general');
                } catch (Throwable) {
                }
            }

            $installedPath = $this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed';
            file_put_contents($installedPath, json_encode([
                'installed_at' => date('c'),
                'installer' => 'cpanel',
            ], JSON_UNESCAPED_UNICODE));
            $this->writeEnvFile($envPath, ['APP_INSTALLED' => 'true']);

            if ($tmp !== null) {
                $this->rrmdir($tmp);
                $tmp = null;
            }

            $this->rollbackStack = [];

            $deleted = [];
            $removeFiles = [$this->packagePath];
            if ($this->installScriptPath !== '' && is_file($this->installScriptPath)) {
                $removeFiles[] = $this->installScriptPath;
            }
            foreach ($removeFiles as $f) {
                if (is_file($f) && @unlink($f)) {
                    $deleted[] = basename($f);
                }
            }

            return [
                'ok' => true,
                'log' => $log,
                'warnings' => $warnings,
                'verify' => $this->verifyInstall(),
                'installer_removed' => in_array('install.php', $deleted, true),
                'deleted' => $deleted,
            ];
        } catch (Throwable $e) {
            $this->rollback();

            throw new RuntimeException($this->sanitizePublicError($e->getMessage()), 0, $e);
        }
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    public function verifyInstall(): array
    {
        $checks = [];
        $add = static function (string $label, bool $ok, string $detail = '') use (&$checks): void {
            $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
        };

        $env = $this->jobDir.'/.env';
        $keyOk = is_file($env) && (bool) preg_match('/^APP_KEY=base64:.+/m', (string) file_get_contents($env));
        $add('وجود APP_KEY', $keyOk);
        $add('vendor/autoload.php', is_file($this->jobDir.'/vendor/autoload.php'));
        $add('public/build/manifest.json', is_file($this->publicHtml.'/build/manifest.json') || is_file($this->jobDir.'/public/build/manifest.json'));
        $index = is_file($this->publicHtml.'/index.php') ? (string) file_get_contents($this->publicHtml.'/index.php') : '';
        $add('public_html/index.php', str_contains($index, 'autoload.php') && str_contains($index, 'job'));
        $add('.htaccess', is_file($this->publicHtml.'/.htaccess'));
        $add('storage قابل نوشتن', $this->writableCheck($this->jobDir.'/storage'));
        $add('bootstrap/cache قابل نوشتن', $this->writableCheck($this->jobDir.'/bootstrap/cache'));
        $add('قفل نصب (storage/installed)', is_file($this->jobDir.'/storage/installed'));

        $dbOk = false;
        try {
            if (is_file($env)) {
                $vars = $this->parseEnvFile($env);
                $pdo = new PDO(
                    sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                        $vars['DB_HOST'] ?? '127.0.0.1',
                        $vars['DB_PORT'] ?? '3306',
                        $vars['DB_DATABASE'] ?? ''
                    ),
                    $vars['DB_USERNAME'] ?? '',
                    $vars['DB_PASSWORD'] ?? '',
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $pdo->query('SELECT 1');
                $dbOk = true;
            }
        } catch (Throwable) {
        }
        $add('اتصال پایگاه‌داده', $dbOk);

        $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $add('HTTPS', $https, $https ? '' : 'پس از نصب SSL را در cPanel فعال کنید.');

        return $checks;
    }

    /**
     * @return array{ok: bool, items: list<array{label: string, ok: bool}>}
     */
    public function permissionReport(): array
    {
        $paths = [
            'storage' => $this->jobDir.'/storage',
            'storage/framework' => $this->jobDir.'/storage/framework',
            'storage/logs' => $this->jobDir.'/storage/logs',
            'bootstrap/cache' => $this->jobDir.'/bootstrap/cache',
        ];
        $items = [];
        $ok = true;
        foreach ($paths as $label => $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $writable = $this->writableCheck($path);
            $items[] = ['label' => $label, 'ok' => $writable];
            if (! $writable) {
                $ok = false;
            }
        }

        return ['ok' => $ok, 'items' => $items];
    }

    private function packageHasFrontendBuild(): bool
    {
        if (! is_file($this->packagePath)) {
            return false;
        }
        $zip = new ZipArchive;
        if ($zip->open($this->packagePath) !== true) {
            return false;
        }
        $found = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with($name, 'public/build/manifest.json') || str_ends_with($name, 'build/manifest.json')) {
                $found = true;
                break;
            }
        }
        $zip->close();

        return $found;
    }

    /**
     * @param  array{host: string, port: string, name: string, user: string, pass: string}  $db
     */
    private function pdoConnect(array $db, bool $withDb): PDO
    {
        $dsn = $withDb
            ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name'])
            : sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']);

        return new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function safeDatabaseName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '';
    }

    private function resolveExtractRoot(string $tmp): string
    {
        if (is_dir($tmp.DIRECTORY_SEPARATOR.'app')) {
            return $tmp;
        }
        $dirs = glob($tmp.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];
        if (isset($dirs[0]) && is_dir($dirs[0].DIRECTORY_SEPARATOR.'app')) {
            return $dirs[0];
        }

        return $tmp;
    }

    private function onRollback(callable $fn): void
    {
        $this->rollbackStack[] = $fn;
    }

    private function rollback(): void
    {
        while ($fn = array_pop($this->rollbackStack)) {
            try {
                $fn();
            } catch (Throwable) {
            }
        }
    }

    private function sanitizePublicError(string $message): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'password') || str_contains($lower, 'access denied') || str_contains($lower, 'sqlstate')) {
            return 'نصب متوقف شد. اتصال پایگاه‌داده، مجوز پوشه‌ها و log سرور را بررسی کنید.';
        }

        return $message !== '' ? $message : 'نصب متوقف شد. لطفاً log سرور را بررسی کنید.';
    }

    private function writableCheck(string $path): bool
    {
        if (is_dir($path) || is_file($path)) {
            return is_writable($path);
        }

        return is_writable(dirname($path));
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function writeEnvFile(string $path, array $overrides): void
    {
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        foreach ($overrides as $key => $value) {
            $value = (string) $value;
            $formatted = preg_match('/[\s#"\']/', $value) ? '"'.str_replace(['\\', '"'], ['\\\\', '\"'], $value).'"' : $value;
            $line = $key.'='.$formatted;
            if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $content)) {
                $content = (string) preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $content);
            } else {
                $content = rtrim($content)."\n".$line."\n";
            }
        }
        file_put_contents($path, $content);
    }

    /**
     * @return array<string, string>
     */
    private function parseEnvFile(string $path): array
    {
        $vars = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if ($line === '' || str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $vars[trim($k)] = trim($v, " \t\"'");
        }

        return $vars;
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * @param  list<string>  $skipNames
     */
    private function copyDir(string $src, string $dst, array $skipNames = []): void
    {
        if (! is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $rel = substr($item->getPathname(), strlen($src) + 1);
            $base = explode(DIRECTORY_SEPARATOR, $rel)[0] ?? '';
            if (in_array($base, $skipNames, true) || in_array($item->getFilename(), $skipNames, true)) {
                continue;
            }
            $target = $dst.DIRECTORY_SEPARATOR.$rel;
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                $dir = dirname($target);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                copy($item->getPathname(), $target);
            }
        }
    }

    private function chmodTree(string $dir, int $mode): void
    {
        if (! is_dir($dir)) {
            return;
        }
        @chmod($dir, $mode);
        foreach (['framework', 'logs', 'app'] as $sub) {
            $p = $dir.DIRECTORY_SEPARATOR.$sub;
            if (is_dir($p)) {
                @chmod($p, $mode);
            }
        }
    }

    private function writePublicIndex(string $publicHtml): void
    {
        $code = <<<'PHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$job = dirname(__DIR__).DIRECTORY_SEPARATOR.'job';

$phpTimeLimit = (int) ini_get('max_execution_time');
if ($phpTimeLimit > 0 && $phpTimeLimit < 60) {
    @set_time_limit(120);
}

if (file_exists($maintenance = $job.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'maintenance.php')) {
    require $maintenance;
}

require $job.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

(require_once $job.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php')
    ->handleRequest(Request::capture());
PHP;
        file_put_contents($publicHtml.DIRECTORY_SEPARATOR.'index.php', $code);
    }

    private function writeHtaccess(string $publicHtml): void
    {
        $path = $publicHtml.DIRECTORY_SEPARATOR.'.htaccess';
        if (is_file($path)) {
            @copy($path, $publicHtml.DIRECTORY_SEPARATOR.'.htaccess.backup');
        }
        $ht = <<<'HT'
<IfModule mod_headers.c>
    <FilesMatch "sw\.js$">
        Header set Service-Worker-Allowed "/"
        Header set Cache-Control "no-cache"
    </FilesMatch>
</IfModule>

<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HT;
        file_put_contents($path, $ht);
    }

    private function laravelApp()
    {
        static $app = null;
        if ($app !== null) {
            return $app;
        }
        chdir($this->jobDir);
        require_once $this->jobDir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
        $app = require $this->jobDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{code: int, output: string}
     */
    private function artisan(string $command, array $params = []): array
    {
        $app = $this->laravelApp();
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $code = $kernel->call($command, $params);

        return ['code' => $code, 'output' => $kernel->output()];
    }

    /**
     * @param  array{site_name: string, url: string, name: string, email: string, mobile: string, password: string}  $site
     */
    private function createAdmin(array $site): void
    {
        $this->laravelApp();

        $user = app(App\Models\User::class);
        $table = $user->getTable();
        $columns = Illuminate\Support\Facades\Schema::getColumnListing($table);

        $existing = Illuminate\Support\Facades\DB::table($table)->where('email', $site['email'])->first();

        $payload = [];
        if (in_array('name', $columns, true)) {
            $payload['name'] = $site['name'];
        }
        if (in_array('email', $columns, true)) {
            $payload['email'] = $site['email'];
        }
        if (in_array('mobile', $columns, true)) {
            $payload['mobile'] = $site['mobile'];
        }
        if (in_array('password', $columns, true)) {
            $payload['password'] = Illuminate\Support\Facades\Hash::make($site['password']);
        }
        if (in_array('role', $columns, true)) {
            $payload['role'] = 'admin';
        }
        if (in_array('status', $columns, true)) {
            $payload['status'] = 'active';
        }
        if (in_array('is_verified', $columns, true)) {
            $payload['is_verified'] = 1;
        }
        if (in_array('username', $columns, true)) {
            $payload['username'] = strstr($site['email'], '@', true) ?: 'admin';
        }
        $payload['updated_at'] = date('Y-m-d H:i:s');

        if ($existing) {
            Illuminate\Support\Facades\DB::table($table)->where('id', $existing->id)->update($payload);

            return;
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        Illuminate\Support\Facades\DB::table($table)->insert($payload);

        if (class_exists(Spatie\Permission\Models\Role::class)
            && Illuminate\Support\Facades\Schema::hasTable('roles')) {
            try {
                $role = Spatie\Permission\Models\Role::query()->where('name', 'admin')->first();
                $model = App\Models\User::query()->where('email', $site['email'])->first();
                if ($role && $model && method_exists($model, 'assignRole')) {
                    $model->assignRole($role);
                }
            } catch (Throwable) {
            }
        }
    }
}
