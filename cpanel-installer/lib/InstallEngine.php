<?php

declare(strict_types=1);

/**
 * JobAzmoon cPanel installer engine (standalone — no Composer autoload for this file).
 *
 * Security constraints: no shell_exec / exec / proc_open / SSH / Composer on the host.
 */
final class InstallEngine
{
    public const MIN_PHP = '8.2.0';

    public const PACKAGE_FILE = 'jobazmoon-core.zip';

    public const INSTALLER_VERSION = '2.0.0';

    /** Absolute minimum free disk when package size is unknown. */
    public const MIN_DISK_BYTES = 400 * 1024 * 1024;

    /** Extra free disk beyond packageSize * DISK_MULTIPLIER. */
    public const DISK_BUFFER_BYTES = 150 * 1024 * 1024;

    public const DISK_MULTIPLIER = 2.5;

    public const MAX_PACKAGE_BYTES = 512 * 1024 * 1024;

    public const MAX_ZIP_ENTRIES = 50000;

    public const MAX_UNCOMPRESSED_BYTES = 1024 * 1024 * 1024;

    public const MAX_COMPRESSION_RATIO = 100.0;

    /** @var list<callable(): void> */
    private array $rollbackStack = [];

    /** @var list<string> */
    private array $installerLogBuffer = [];

    /** Secrets to redact from public errors / installer logs. */
    private array $secretsToRedact = [];

    public function __construct(
        public readonly string $publicHtml,
        public readonly string $homeDir,
        public readonly string $jobDir,
        public readonly string $packagePath,
        public readonly string $installScriptPath = '',
    ) {}

    /**
     * Locked only when both .env and storage/installed exist.
     */
    public function isLocked(): bool
    {
        return is_file($this->jobDir.DIRECTORY_SEPARATOR.'.env')
            && is_file($this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed');
    }

    /**
     * @return 'locked'|'not_installed'|'incomplete'|'corrupted'
     */
    public function installationStatus(): string
    {
        $envExists = is_file($this->jobDir.DIRECTORY_SEPARATOR.'.env');
        $markerExists = is_file($this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed');
        $artisanExists = is_file($this->jobDir.DIRECTORY_SEPARATOR.'artisan');
        $autoloadExists = is_file($this->jobDir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');
        $bootstrapExists = is_file($this->jobDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php');

        if ($envExists && $markerExists) {
            if ($artisanExists && (! $autoloadExists || ! $bootstrapExists)) {
                return 'corrupted';
            }

            return 'locked';
        }

        if ($artisanExists && ! $markerExists) {
            return 'incomplete';
        }

        if ($markerExists && ! $envExists) {
            return 'corrupted';
        }

        if ($artisanExists && $envExists && ! $markerExists) {
            return 'incomplete';
        }

        return 'not_installed';
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

        $packageOk = is_file($this->packagePath);
        $packageSize = $packageOk ? (int) @filesize($this->packagePath) : 0;
        $requiredDisk = $packageSize > 0
            ? (int) ($packageSize * self::DISK_MULTIPLIER) + self::DISK_BUFFER_BYTES
            : self::MIN_DISK_BYTES;
        $freeDisk = (int) @disk_free_space($this->publicHtml);

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
                'ok' => $packageOk,
                'fix' => 'پوشه package و فایل '.self::PACKAGE_FILE.' را کنار install.php آپلود کنید.',
                'warn' => false,
            ],
            [
                'label' => 'اندازه بسته در محدوده مجاز',
                'ok' => ! $packageOk || ($packageSize > 0 && $packageSize <= self::MAX_PACKAGE_BYTES),
                'fix' => 'اندازه بسته بیش از حد مجاز است. بسته را دوباره بسازید.',
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
                'ok' => $freeDisk >= $requiredDisk,
                'fix' => 'حداقل حدود '.ceil($requiredDisk / (1024 * 1024)).' مگابایت فضای خالی لازم است (اندازه بسته × ۲٫۵ + ۱۵۰ مگابایت).',
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

        if (($db['host'] ?? '') === '') {
            $errors[] = 'هاست پایگاه‌داده الزامی است.';
        }
        if (! preg_match('/^\d{1,5}$/', (string) ($db['port'] ?? '')) || (int) $db['port'] < 1 || (int) $db['port'] > 65535) {
            $errors[] = 'پورت پایگاه‌داده باید عددی بین ۱ تا ۶۵۵۳۵ باشد.';
        }
        if (($db['name'] ?? '') === '' || ! preg_match('/^[A-Za-z0-9_]+$/', (string) $db['name'])) {
            $errors[] = 'نام پایگاه‌داده فقط می‌تواند شامل حروف انگلیسی، عدد و _ باشد.';
        }
        if (($db['user'] ?? '') === '') {
            $errors[] = 'نام کاربری پایگاه‌داده الزامی است.';
        }
        if (strlen((string) ($db['name'] ?? '')) > 64) {
            $errors[] = 'نام پایگاه‌داده حداکثر ۶۴ کاراکتر است.';
        }

        return $errors;
    }

    /**
     * Connect WITH dbname first (cPanel style). Do not depend on CREATE DATABASE.
     *
     * @param  array{host: string, port: string, name: string, user: string, pass: string}  $db
     * @return array{ok: bool, message: string, state: string, table_count: int}
     */
    public function testDatabase(array $db): array
    {
        $this->rememberSecrets($db);

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
            try {
                $pdoDb = $this->pdoConnect($db, true);
            } catch (PDOException $e) {
                if (! $this->isUnknownDatabaseError($e)) {
                    throw $e;
                }

                // Optional fallback: try CREATE only if connect failed with "unknown database".
                try {
                    $pdo = $this->pdoConnect($db, false);
                    $safeName = $this->safeDatabaseName($db['name']);
                    $pdo->exec(
                        'CREATE DATABASE IF NOT EXISTS `'.$safeName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                    );
                    $pdoDb = $this->pdoConnect($db, true);
                } catch (Throwable) {
                    return [
                        'ok' => false,
                        'message' => 'پایگاه‌داده «'.$db['name'].'» وجود ندارد یا قابل دسترسی نیست. لطفاً آن را از cPanel → MySQL Databases بسازید و کاربر را به آن اختصاص دهید.',
                        'state' => 'missing_database',
                        'table_count' => 0,
                    ];
                }
            }

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
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->sanitizePublicError(
                    'اتصال به MySQL برقرار نشد. هاست، نام کاربری، رمز و نام پایگاه‌داده را در cPanel بررسی کنید.'
                ),
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
     * @return array{ok: bool, log: list<string>, warnings: list<string>, verify: list<array{label: string, ok: bool, detail: string, level?: string}>, installer_removed: bool, deleted: list<string>}
     */
    public function runInstall(array $site, array $db, bool $confirmedExistingDb = false): array
    {
        $this->rememberSecrets($db, $site);
        $this->installerLogBuffer = [];
        $this->rollbackStack = [];

        if ($this->isLocked()) {
            throw new RuntimeException('این سایت قبلاً نصب شده است.');
        }

        $status = $this->installationStatus();
        if ($status === 'incomplete') {
            throw new RuntimeException(
                'نصب ناقص تشخیص داده شد (پوشه job/artisan وجود دارد ولی storage/installed نیست). برای جلوگیری از بازنویسی، نصب متوقف شد. پوشه job را بررسی یا پاک کنید و دوباره تلاش کنید.'
            );
        }
        if ($status === 'corrupted') {
            throw new RuntimeException(
                'وضعیت نصب خراب به‌نظر می‌رسد. فایل‌های job را دستی بررسی کنید؛ نصب‌کننده از بازنویسی خودداری می‌کند.'
            );
        }

        $dbTest = $this->testDatabase($db);
        if (! $dbTest['ok']) {
            throw new RuntimeException($dbTest['message']);
        }
        if ($dbTest['state'] === 'has_tables' && ! $confirmedExistingDb) {
            throw new RuntimeException(
                'این پایگاه‌داده '.$dbTest['table_count'].' جدول دارد. برای ادامه باید تأیید صریح بدهید.'
            );
        }

        $siteErrors = $this->validateSiteInput(array_merge($site, [
            'password_confirmation' => $site['password_confirmation'] ?? $site['password'] ?? '',
        ]));
        if ($siteErrors !== []) {
            throw new RuntimeException($siteErrors[0]);
        }

        $log = [];
        $warnings = [];
        $tmp = null;
        $backedUpIndex = null;
        $backedUpHtaccess = null;
        $initialTableCount = (int) ($dbTest['table_count'] ?? 0);
        $migrationBatchBefore = null;

        $push = function (string $m) use (&$log): void {
            $log[] = $m;
            $this->installerLog($m);
        };

        try {
            if (! is_file($this->packagePath)) {
                throw new RuntimeException('بسته نصب یافت نشد: '.self::PACKAGE_FILE);
            }
            $packageSize = (int) filesize($this->packagePath);
            if ($packageSize <= 0 || $packageSize > self::MAX_PACKAGE_BYTES) {
                throw new RuntimeException('اندازه بسته نصب نامعتبر یا بیش از حد مجاز است.');
            }

            if (! is_dir($this->jobDir)) {
                if (! mkdir($this->jobDir, 0775, true) && ! is_dir($this->jobDir)) {
                    throw new RuntimeException('ساخت پوشه job ممکن نشد.');
                }
                $this->onRollback(function (): void {
                    $this->rrmdir($this->jobDir);
                });
            }

            $zip = new ZipArchive;
            if ($zip->open($this->packagePath) !== true) {
                throw new RuntimeException('باز کردن بسته نصب ممکن نشد.');
            }

            try {
                $this->validateZipEntries($zip);
            } catch (Throwable $e) {
                $zip->close();
                throw $e;
            }

            $tmp = $this->publicHtml.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'_extract_'.bin2hex(random_bytes(4));
            if (! mkdir($tmp, 0775, true) && ! is_dir($tmp)) {
                $zip->close();
                throw new RuntimeException('ساخت پوشه موقت استخراج ممکن نشد.');
            }
            $this->onRollback(function () use (&$tmp): void {
                if ($tmp !== null && is_dir($tmp)) {
                    $this->rrmdir($tmp);
                }
            });

            $this->extractZipSafely($zip, $tmp);
            $zip->close();
            $push('بسته با کنترل امنیتی استخراج شد.');

            $source = $this->resolveExtractRoot($tmp);
            $this->validateLaravelPackage($source);
            $push('بسته Laravel اعتبارسنجی شد.');

            $this->copyDir($source, $this->jobDir);
            $push('فایل‌های Laravel در پوشه job کپی شد.');

            if (! is_file($this->jobDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php')) {
                throw new RuntimeException('فایل bootstrap/app.php پس از کپی یافت نشد. نصب متوقف شد.');
            }

            $pubSrc = $this->jobDir.DIRECTORY_SEPARATOR.'public';
            if (is_dir($pubSrc)) {
                $indexPath = $this->publicHtml.DIRECTORY_SEPARATOR.'index.php';
                $htPath = $this->publicHtml.DIRECTORY_SEPARATOR.'.htaccess';
                if (is_file($indexPath)) {
                    $backedUpIndex = $this->publicHtml.DIRECTORY_SEPARATOR.'index.php.installer-bak';
                    @rename($indexPath, $backedUpIndex);
                }
                if (is_file($htPath)) {
                    $backedUpHtaccess = $this->publicHtml.DIRECTORY_SEPARATOR.'.htaccess.installer-bak';
                    @copy($htPath, $backedUpHtaccess);
                }
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
            if (! is_file($envPath)) {
                if (is_file($example)) {
                    copy($example, $envPath);
                } else {
                    file_put_contents($envPath, '');
                }
                $this->onRollback(function () use ($envPath): void {
                    if (is_file($envPath)) {
                        @unlink($envPath);
                    }
                });
            }

            $appKey = 'base64:'.base64_encode(random_bytes(32));
            $this->rememberSecrets(['pass' => $appKey], ['password' => $site['password'] ?? '']);
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
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
            ] as $k => $v) {
                putenv($k.'='.$v);
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }

            $this->applyPermissionsRecursive($this->jobDir);
            $perm = $this->permissionReport();
            if (! $perm['ok']) {
                throw new RuntimeException('برخی پوشه‌های storage یا bootstrap/cache قابل نوشتن نیستند.');
            }
            $push('مجوزهای پوشه بررسی شد.');

            // Snapshot migrations before migrate — never migrate:fresh / refresh / DROP.
            try {
                $pdoSnap = $this->pdoConnect($db, true);
                if ($this->tableExists($pdoSnap, 'migrations')) {
                    $migrationBatchBefore = (int) $pdoSnap->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn();
                } else {
                    $migrationBatchBefore = 0;
                }
            } catch (Throwable) {
                $migrationBatchBefore = 0;
            }

            $mig = $this->artisan('migrate', ['--force' => true]);
            if ($mig['code'] !== 0) {
                if ($initialTableCount === 0) {
                    try {
                        $this->rollbackNewMigrationsOnly($db, (int) $migrationBatchBefore);
                    } catch (Throwable) {
                    }
                    throw new RuntimeException('اجرای migration ناموفق بود. چون پایگاه خالی بود، مراحل جدید برگردانده شد.');
                }

                throw new RuntimeException(
                    'اجرای migration ناموفق بود. چون پایگاه از قبل جدول داشت، rollback خودکار انجام نشد — وضعیت را دستی بررسی کنید.'
                );
            }
            $push('migrate اجرا شد.');

            // Never register blind migrate:rollback for existing DBs.
            if ($initialTableCount === 0) {
                $batchBefore = (int) $migrationBatchBefore;
                $this->onRollback(function () use ($db, $batchBefore): void {
                    try {
                        $this->rollbackNewMigrationsOnly($db, $batchBefore);
                    } catch (Throwable) {
                    }
                });
            }

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

            $linkResult = $this->ensureStorageLink();
            if ($linkResult === 'pass') {
                $push('لینک storage برقرار شد.');
            } elseif ($linkResult === 'warn') {
                $warnings[] = 'storage:link ممکن است روی هاست محدود باشد — symlink دستی بررسی شود.';
            } else {
                $warnings[] = 'لینک storage برقرار نشد (FAIL). مسیر public_html/storage را دستی به job/storage/app/public وصل کنید.';
            }

            $this->createAdmin($site);
            $push('کاربر مدیر ذخیره شد.');

            try {
                $this->artisan('optimize:clear');
                $this->artisan('config:cache');
                $this->artisan('view:cache');
                $routeCache = $this->artisan('route:cache');
                if ($routeCache['code'] !== 0) {
                    $warnings[] = 'route:cache اجرا نشد (هشدار — نصب ادامه یافت).';
                } else {
                    $push('کش production ساخته شد.');
                }
            } catch (Throwable) {
                $warnings[] = 'برخی دستورات cache روی این هاست اجرا نشدند.';
            }

            if (class_exists(\App\Models\Setting::class)) {
                try {
                    \App\Models\Setting::set('site_name', $site['site_name'], 'general');
                } catch (Throwable) {
                }
            }

            $appVersion = $this->detectApplicationVersion();
            $installedPath = $this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed';
            $installedDir = dirname($installedPath);
            if (! is_dir($installedDir)) {
                @mkdir($installedDir, 0775, true);
            }
            file_put_contents($installedPath, json_encode([
                'installed_at' => date('c'),
                'installer_version' => self::INSTALLER_VERSION,
                'application_version' => $appVersion,
                'installer' => 'cpanel',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->writeEnvFile($envPath, ['APP_INSTALLED' => 'true']);
            $push('نشانگر نصب ذخیره شد.');

            if ($tmp !== null) {
                $this->rrmdir($tmp);
                $tmp = null;
            }

            foreach ([$backedUpIndex, $backedUpHtaccess] as $bak) {
                if ($bak !== null && is_file($bak)) {
                    @unlink($bak);
                }
            }
            $backedUpIndex = null;
            $backedUpHtaccess = null;

            $this->rollbackStack = [];
            $this->flushInstallerLog();

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

            $installerRemoved = in_array('install.php', $deleted, true);
            if (! $installerRemoved && $this->installScriptPath !== '' && is_file($this->installScriptPath)) {
                $this->denyInstallPhpViaHtaccess();
                $warnings[] = 'فایل install.php حذف نشد. فوراً از File Manager پاک کنید. یک قانون Deny در .htaccess اضافه شد.';
            }

            return [
                'ok' => true,
                'log' => $log,
                'warnings' => $warnings,
                'verify' => $this->verifyInstall(),
                'installer_removed' => $installerRemoved,
                'deleted' => $deleted,
            ];
        } catch (Throwable $e) {
            $this->installerLog('ERROR: '.$this->sanitizePublicError($e->getMessage()));
            $this->flushInstallerLog();
            $this->rollback();

            throw new RuntimeException($this->sanitizePublicError($e->getMessage()), 0, $e);
        }
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string, level: string}>
     */
    public function verifyInstall(): array
    {
        $checks = [];
        $add = static function (string $label, bool $pass, string $detail = '', string $level = '') use (&$checks): void {
            if ($level === '') {
                $level = $pass ? 'pass' : 'fail';
            }
            // ok=false only for fail; pass AND warning keep ok=true for BC.
            $ok = $level !== 'fail';
            $checks[] = [
                'label' => $label,
                'ok' => $ok,
                'detail' => $detail,
                'level' => $level,
            ];
        };

        $envPath = $this->jobDir.DIRECTORY_SEPARATOR.'.env';
        $envOk = is_file($envPath);
        $add('وجود فایل .env', $envOk);

        $vars = $envOk ? $this->parseEnvFile($envPath) : [];
        $keyOk = $envOk && (bool) preg_match('/^APP_KEY=base64:.+/m', (string) file_get_contents($envPath));
        $add('وجود APP_KEY', $keyOk);

        $envProd = strtolower((string) ($vars['APP_ENV'] ?? '')) === 'production';
        $add('APP_ENV=production', $envProd, $envProd ? '' : 'مقدار فعلی: '.($vars['APP_ENV'] ?? 'خالی'));

        $debugVal = strtolower((string) ($vars['APP_DEBUG'] ?? 'true'));
        $debugOff = in_array($debugVal, ['false', '0', 'off', 'no'], true);
        $add('APP_DEBUG=false', $debugOff, $debugOff ? '' : 'برای production باید false باشد.');

        $add('vendor/autoload.php', is_file($this->jobDir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php'));
        $add('artisan', is_file($this->jobDir.DIRECTORY_SEPARATOR.'artisan'));
        $add('bootstrap/app.php', is_file($this->jobDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php'));

        $index = is_file($this->publicHtml.DIRECTORY_SEPARATOR.'index.php')
            ? (string) file_get_contents($this->publicHtml.DIRECTORY_SEPARATOR.'index.php')
            : '';
        $add(
            'public_html/index.php',
            str_contains($index, 'autoload.php') && str_contains($index, 'job') && str_contains($index, 'handleRequest')
        );
        $add('.htaccess', is_file($this->publicHtml.DIRECTORY_SEPARATOR.'.htaccess'));

        $manifestOk = is_file($this->publicHtml.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json')
            || is_file($this->jobDir.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json');
        $add('public/build/manifest.json', $manifestOk);

        $add('storage قابل نوشتن', $this->writableCheck($this->jobDir.DIRECTORY_SEPARATOR.'storage'));
        $add('bootstrap/cache قابل نوشتن', $this->writableCheck($this->jobDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'));

        $dbOk = false;
        $usersOk = false;
        $migrationsOk = false;
        try {
            if ($envOk && ($vars['DB_DATABASE'] ?? '') !== '') {
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
                $usersOk = $this->tableExists($pdo, 'users');
                $migrationsOk = $this->tableExists($pdo, 'migrations');
            }
        } catch (Throwable) {
        }
        $add('اتصال پایگاه‌داده', $dbOk);
        $add('جدول users', $usersOk, $usersOk ? '' : 'جدول users یافت نشد.');
        $add('جدول migrations', $migrationsOk, $migrationsOk ? '' : 'جدول migrations یافت نشد.');

        $linkPath = $this->publicHtml.DIRECTORY_SEPARATOR.'storage';
        $linkTarget = $this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';
        if (is_link($linkPath) || (PHP_OS_FAMILY === 'Windows' && is_dir($linkPath))) {
            $add('لینک storage', true, 'برقرار است', 'pass');
        } elseif (file_exists($linkPath)) {
            $add('لینک storage', true, 'مسیر storage وجود دارد ولی symlink نیست', 'warning');
        } else {
            $add('لینک storage', false, 'لینک storage وجود ندارد — هدف: '.$linkTarget, 'fail');
        }

        $markerPath = $this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed';
        $markerOk = is_file($markerPath);
        $add('نشانگر نصب (storage/installed)', $markerOk);

        $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        if ($https) {
            $add('HTTPS', true, '', 'pass');
        } else {
            $add('HTTPS', true, 'پس از نصب SSL را در cPanel فعال کنید.', 'warning');
        }

        return $checks;
    }

    /**
     * @return array{ok: bool, items: list<array{label: string, ok: bool}>}
     */
    public function permissionReport(): array
    {
        $paths = [
            'storage' => $this->jobDir.DIRECTORY_SEPARATOR.'storage',
            'storage/framework' => $this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework',
            'storage/logs' => $this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs',
            'bootstrap/cache' => $this->jobDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache',
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

    /**
     * Reject path traversal, absolute paths, null bytes, symlinks, and ZIP bombs.
     *
     * @throws RuntimeException
     */
    public function validateZipEntries(ZipArchive $zip): void
    {
        $num = $zip->numFiles;
        if ($num <= 0) {
            throw new RuntimeException('بسته نصب خالی است.');
        }
        if ($num > self::MAX_ZIP_ENTRIES) {
            throw new RuntimeException('تعداد فایل‌های بسته بیش از حد مجاز است (ZIP bomb؟).');
        }

        $totalUncompressed = 0;
        $totalCompressed = 0;

        for ($i = 0; $i < $num; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                throw new RuntimeException('خواندن اطلاعات ورودی ZIP ممکن نشد.');
            }

            $name = (string) ($stat['name'] ?? '');
            if ($name === '' || str_contains($name, "\0")) {
                throw new RuntimeException('نام ورودی نامعتبر در بسته نصب (null byte).');
            }

            $normalized = str_replace('\\', '/', $name);

            if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
                throw new RuntimeException('مسیر خطرناک در بسته نصب شناسایی شد (path traversal).');
            }
            if (preg_match('#^[a-zA-Z]:/#', $normalized) || str_starts_with($normalized, '/') || str_starts_with($normalized, '//')) {
                throw new RuntimeException('مسیر مطلق در بسته نصب مجاز نیست.');
            }
            if (preg_match('/[\x00-\x1F\x7F]/', $normalized)) {
                throw new RuntimeException('نام فایل غیرمجاز در بسته نصب.');
            }

            // Symlink / special entry detection via external attributes (Unix).
            if (method_exists($zip, 'getExternalAttributesIndex')) {
                $attrOpsys = 0;
                $attr = 0;
                if ($zip->getExternalAttributesIndex($i, $attrOpsys, $attr) && $attrOpsys === ZipArchive::OPSYS_UNIX) {
                    $mode = ($attr >> 16) & 0xFFFF;
                    // S_IFLNK = 0120000
                    if (($mode & 0xF000) === 0xA000) {
                        throw new RuntimeException('بسته نصب شامل symlink است که مجاز نیست.');
                    }
                }
            }

            $uncompressed = (int) ($stat['size'] ?? 0);
            $compressed = (int) ($stat['comp_size'] ?? 0);
            if ($uncompressed < 0 || $compressed < 0) {
                throw new RuntimeException('اندازه ورودی ZIP نامعتبر است.');
            }
            $totalUncompressed += $uncompressed;
            $totalCompressed += max($compressed, 0);

            if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('حجم غیرفشرده بسته بیش از حد مجاز است (ZIP bomb؟).');
            }

            if ($compressed > 0 && $uncompressed > 0) {
                $ratio = $uncompressed / max($compressed, 1);
                if ($ratio > self::MAX_COMPRESSION_RATIO && $uncompressed > 1024 * 1024) {
                    throw new RuntimeException('نسبت فشرده‌سازی مشکوک در بسته نصب (ZIP bomb؟).');
                }
            }
        }

        if ($totalCompressed > 0 && $totalUncompressed / max($totalCompressed, 1) > self::MAX_COMPRESSION_RATIO
            && $totalUncompressed > 10 * 1024 * 1024) {
            throw new RuntimeException('نسبت فشرده‌سازی کلی بسته مشکوک است.');
        }
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
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (str_ends_with($name, 'public/build/manifest.json') || str_ends_with($name, 'build/manifest.json')) {
                $found = true;
                break;
            }
        }
        $zip->close();

        return $found;
    }

    /**
     * Extract entry-by-entry into $tmp only; verify realpath stays under tmp.
     *
     * @throws RuntimeException
     */
    private function extractZipSafely(ZipArchive $zip, string $tmp): void
    {
        $tmpReal = realpath($tmp);
        if ($tmpReal === false) {
            throw new RuntimeException('مسیر موقت استخراج نامعتبر است.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || str_ends_with($name, '/')) {
                $dir = $tmpReal.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, rtrim($name, '/'));
                if ($name !== '' && ! is_dir($dir)) {
                    if (! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                        throw new RuntimeException('ساخت پوشه هنگام استخراج ممکن نشد.');
                    }
                }
                continue;
            }

            $target = $tmpReal.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
            $parent = dirname($target);
            if (! is_dir($parent) && ! mkdir($parent, 0775, true) && ! is_dir($parent)) {
                throw new RuntimeException('ساخت پوشه والد هنگام استخراج ممکن نشد.');
            }

            $parentReal = realpath($parent);
            if ($parentReal === false || ! $this->pathIsInside($parentReal, $tmpReal)) {
                throw new RuntimeException('مسیر استخراج خارج از پوشه موقت تشخیص داده شد.');
            }

            $stream = $zip->getStream($name);
            if ($stream === false) {
                throw new RuntimeException('خواندن ورودی ZIP ممکن نشد.');
            }

            $out = fopen($target, 'wb');
            if ($out === false) {
                fclose($stream);
                throw new RuntimeException('نوشتن فایل استخراج‌شده ممکن نشد.');
            }

            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            $writtenReal = realpath($target);
            if ($writtenReal === false || ! $this->pathIsInside($writtenReal, $tmpReal)) {
                @unlink($target);
                throw new RuntimeException('فایل استخراج‌شده خارج از محدوده مجاز است.');
            }
        }
    }

    private function pathIsInside(string $path, string $root): bool
    {
        $path = strtolower(str_replace('\\', '/', $path));
        $root = strtolower(str_replace('\\', '/', $root));
        $root = rtrim($root, '/');

        return $path === $root || str_starts_with($path, $root.'/');
    }

    /**
     * @throws RuntimeException
     */
    private function validateLaravelPackage(string $root): void
    {
        $required = [
            'artisan' => 'فایل artisan',
            'app' => 'پوشه app/',
            'bootstrap' => 'پوشه bootstrap/',
            'bootstrap'.DIRECTORY_SEPARATOR.'app.php' => 'فایل bootstrap/app.php',
            'config' => 'پوشه config/',
            'vendor'.DIRECTORY_SEPARATOR.'autoload.php' => 'فایل vendor/autoload.php',
            'public' => 'پوشه public/',
            'storage' => 'پوشه storage/',
        ];

        foreach ($required as $rel => $label) {
            $full = $root.DIRECTORY_SEPARATOR.$rel;
            if (in_array($rel, ['app', 'bootstrap', 'config', 'public', 'storage'], true)) {
                $ok = is_dir($full);
            } else {
                $ok = is_file($full);
            }

            if (! $ok) {
                if (str_ends_with($rel, 'autoload.php')) {
                    throw new RuntimeException(
                        'فایل vendor/autoload.php در بسته نیست. روی سیستم توسعه خودتان دستور '
                        .'composer install --no-dev --optimize-autoloader را اجرا کنید و دوباره بسته را بسازید.'
                    );
                }
                throw new RuntimeException('بسته Laravel ناقص است: '.$label.' یافت نشد.');
            }
        }

        $manifest = $root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($manifest)) {
            throw new RuntimeException(
                'فایل public/build/manifest.json یافت نشد. قبل از بسته‌بندی روی سیستم خودتان npm run build اجرا کنید.'
            );
        }
    }

    private function resolveExtractRoot(string $tmp): string
    {
        if (is_dir($tmp.DIRECTORY_SEPARATOR.'app') && is_file($tmp.DIRECTORY_SEPARATOR.'artisan')) {
            return $tmp;
        }

        $preferred = ['jobazmoon', 'jobazmoon-core', 'job'];
        foreach ($preferred as $name) {
            $candidate = $tmp.DIRECTORY_SEPARATOR.$name;
            if (is_dir($candidate.DIRECTORY_SEPARATOR.'app') && is_file($candidate.DIRECTORY_SEPARATOR.'artisan')) {
                return $candidate;
            }
        }

        $dirs = glob($tmp.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            if (is_dir($dir.DIRECTORY_SEPARATOR.'app') && is_file($dir.DIRECTORY_SEPARATOR.'artisan')) {
                return $dir;
            }
        }

        throw new RuntimeException('ریشه پروژه Laravel داخل بسته یافت نشد (انتظار app/ یا jobazmoon/).');
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

    private function isUnknownDatabaseError(PDOException $e): bool
    {
        $msg = strtolower($e->getMessage());
        $code = (string) ($e->errorInfo[1] ?? $e->getCode());

        return $code === '1049'
            || str_contains($msg, 'unknown database')
            || str_contains($msg, '1049');
    }

    private function safeDatabaseName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '';
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Roll back only migration batches newer than $batchBefore. Never DROP DATABASE/TABLE blindly.
     *
     * @param  array{host: string, port: string, name: string, user: string, pass: string}  $db
     */
    private function rollbackNewMigrationsOnly(array $db, int $batchBefore): void
    {
        try {
            $pdo = $this->pdoConnect($db, true);
            if (! $this->tableExists($pdo, 'migrations')) {
                return;
            }
            $max = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn();
            for ($b = $max; $b > $batchBefore; $b--) {
                $this->artisan('migrate:rollback', ['--force' => true, '--step' => 1]);
            }
        } catch (Throwable) {
            // Best-effort only for empty-DB installs.
        }
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

    /**
     * @param  array{pass?: string}|array{host?: string, port?: string, name?: string, user?: string, pass?: string}  $db
     * @param  array{password?: string}|null  $site
     */
    private function rememberSecrets(array $db = [], ?array $site = null): void
    {
        foreach (['pass', 'password', 'DB_PASSWORD', 'APP_KEY'] as $k) {
            if (! empty($db[$k]) && is_string($db[$k]) && strlen($db[$k]) >= 3) {
                $this->secretsToRedact[] = $db[$k];
            }
        }
        if ($site !== null && ! empty($site['password']) && is_string($site['password']) && strlen($site['password']) >= 3) {
            $this->secretsToRedact[] = $site['password'];
        }
        $this->secretsToRedact = array_values(array_unique($this->secretsToRedact));
    }

    private function sanitizePublicError(string $message): string
    {
        $redacted = $message;
        foreach ($this->secretsToRedact as $secret) {
            if ($secret !== '') {
                $redacted = str_replace($secret, '[REDACTED]', $redacted);
            }
        }

        $lower = strtolower($redacted);
        if (
            str_contains($lower, 'password')
            || str_contains($lower, 'access denied')
            || str_contains($lower, 'sqlstate')
            || str_contains($lower, 'using password')
        ) {
            return 'نصب متوقف شد. اتصال پایگاه‌داده، مجوز پوشه‌ها و log سرور را بررسی کنید.';
        }

        return $redacted !== '' ? $redacted : 'نصب متوقف شد. لطفاً log سرور را بررسی کنید.';
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
        $lines = is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $seen = [];
        $out = [];

        foreach ($lines as $line) {
            if ($line === '' || str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                $out[] = $line;
                continue;
            }
            [$k] = explode('=', $line, 2);
            $key = trim($k);
            if (array_key_exists($key, $overrides)) {
                if (isset($seen[$key])) {
                    continue; // drop duplicate keys
                }
                $out[] = $key.'='.$this->formatEnvValue((string) $overrides[$key]);
                $seen[$key] = true;
            } else {
                if (isset($seen[$key])) {
                    continue;
                }
                $out[] = $line;
                $seen[$key] = true;
            }
        }

        foreach ($overrides as $key => $value) {
            if (! isset($seen[$key])) {
                $out[] = $key.'='.$this->formatEnvValue((string) $value);
                $seen[$key] = true;
            }
        }

        file_put_contents($path, implode("\n", $out)."\n");
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"\'\\\\$]/', $value)) {
            return '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', ''], $value).'"';
        }

        return $value;
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
            mkdir($dst, 0775, true);
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
                    mkdir($target, 0775, true);
                }
            } else {
                $dir = dirname($target);
                if (! is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * Dirs 0775, files 0644. If chmod fails but is_writable OK, continue.
     */
    private function applyPermissionsRecursive(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        @chmod($root, 0775);

        foreach ($it as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                if (! @chmod($path, 0775) && ! is_writable($path)) {
                    // continue — writable check later
                }
            } else {
                @chmod($path, 0644);
            }
        }

        foreach (['storage', 'bootstrap'.DIRECTORY_SEPARATOR.'cache'] as $rel) {
            $p = $root.DIRECTORY_SEPARATOR.$rel;
            if (is_dir($p)) {
                @chmod($p, 0775);
            }
        }
    }

    private function writePublicIndex(string $publicHtml): void
    {
        $bootstrap = $this->jobDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
        if (! is_file($bootstrap)) {
            throw new RuntimeException('فایل bootstrap/app.php یافت نشد؛ نوشتن index.php ممکن نیست.');
        }

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

$appBootstrap = $job.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
if (! is_file($appBootstrap)) {
    http_response_code(500);
    echo 'Application bootstrap missing.';
    exit(1);
}

(require_once $appBootstrap)
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

    private function denyInstallPhpViaHtaccess(): void
    {
        $path = $this->publicHtml.DIRECTORY_SEPARATOR.'.htaccess';
        $snippet = <<<'HT'

# JobAzmoon installer lock — remove install.php from File Manager
<Files "install.php">
    Require all denied
</Files>
HT;
        $existing = is_file($path) ? (string) file_get_contents($path) : '';
        if (! str_contains($existing, 'Files "install.php"')) {
            file_put_contents($path, rtrim($existing)."\n".$snippet."\n");
        }
    }

    /**
     * @return 'pass'|'warn'|'fail'
     */
    private function ensureStorageLink(): string
    {
        $link = $this->publicHtml.DIRECTORY_SEPARATOR.'storage';
        $target = $this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';

        if (! is_dir($target)) {
            @mkdir($target, 0775, true);
        }

        try {
            $result = $this->artisan('storage:link', ['--force' => true]);
            if ($result['code'] === 0 && (is_link($link) || is_dir($link))) {
                return 'pass';
            }
        } catch (Throwable) {
        }

        if (! file_exists($link)) {
            if (@symlink($target, $link)) {
                return is_link($link) || is_dir($link) ? 'pass' : 'warn';
            }

            return 'fail';
        }

        return 'warn';
    }

    private function detectApplicationVersion(): string
    {
        $composer = $this->jobDir.DIRECTORY_SEPARATOR.'composer.json';
        if (is_file($composer)) {
            $data = json_decode((string) file_get_contents($composer), true);
            if (is_array($data) && isset($data['version']) && is_string($data['version'])) {
                return $data['version'];
            }
        }

        return 'unknown';
    }

    private function installerLog(string $message): void
    {
        $this->installerLogBuffer[] = '['.date('c').'] '.$this->sanitizePublicError($message);
    }

    private function flushInstallerLog(): void
    {
        if ($this->installerLogBuffer === []) {
            return;
        }
        $dir = $this->jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.DIRECTORY_SEPARATOR.'installer.log';
        @file_put_contents($file, implode("\n", $this->installerLogBuffer)."\n", FILE_APPEND);
        $this->installerLogBuffer = [];
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
     * Create admin without overwriting an existing user's password.
     *
     * @param  array{site_name: string, url: string, name: string, email: string, mobile: string, password: string}  $site
     */
    private function createAdmin(array $site): void
    {
        $this->laravelApp();

        if (! Illuminate\Support\Facades\Schema::hasTable('users')) {
            throw new RuntimeException('جدول users وجود ندارد؛ migration را بررسی کنید.');
        }

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
            // Do NOT overwrite existing user password.
            unset($payload['password']);
            Illuminate\Support\Facades\DB::table($table)->where('id', $existing->id)->update($payload);
        } else {
            if (in_array('password', $columns, true)) {
                $payload['password'] = Illuminate\Support\Facades\Hash::make($site['password']);
            }
            $payload['created_at'] = date('Y-m-d H:i:s');
            Illuminate\Support\Facades\DB::table($table)->insert($payload);
        }

        // Spatie assignRole — soft-fail.
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
