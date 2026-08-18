<?php

declare(strict_types=1);

/**
 * JobAzmoon cPanel installer (standalone). Does not run Composer.
 */

@set_time_limit(0);
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '0');

session_start();

const INSTALLER_NAME = 'JobAzmoon';
const PACKAGE_FILE = 'jobazmoon-core.zip';
const MIN_PHP = '8.2.0';
const MIN_DISK_BYTES = 400 * 1024 * 1024;

$publicHtml = __DIR__;
$homeDir = dirname($publicHtml);
$jobDir = $homeDir.DIRECTORY_SEPARATOR.'job';
$packagePath = $publicHtml.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.PACKAGE_FILE;

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_ok(): bool
{
    return isset($_POST['_token'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['_token']);
}

function default_url(): string
{
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return ($https ? 'https' : 'http').'://'.$host;
}

function already_installed(string $jobDir): bool
{
    return is_file($jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed')
        && is_file($jobDir.DIRECTORY_SEPARATOR.'.env');
}

function writable_check(string $path): bool
{
    if (is_dir($path) || is_file($path)) {
        return is_writable($path);
    }

    return is_writable(dirname($path));
}

function requirements(string $jobDir, string $packagePath, string $publicHtml): array
{
    $ext = static fn (string $n): array => [
        'label' => 'افزونه PHP: '.$n,
        'ok' => extension_loaded($n),
        'fix' => 'در cPanel → Select PHP Version → Extensions گزینه '.$n.' را فعال کنید.',
        'warn' => false,
    ];

    $items = [
        [
            'label' => 'PHP >= '.MIN_PHP,
            'ok' => version_compare(PHP_VERSION, MIN_PHP, '>='),
            'fix' => 'در cPanel نسخه PHP را روی ۸.۲ یا ۸.۳ بگذارید. نسخه فعلی: '.PHP_VERSION,
            'warn' => false,
        ],
        $ext('pdo'),
        $ext('pdo_mysql'),
        $ext('openssl'),
        $ext('mbstring'),
        $ext('xml'),
        $ext('ctype'),
        $ext('json'),
        $ext('fileinfo'),
        $ext('gd'),
        $ext('zip'),
        $ext('dom'),
        [
            'label' => 'Session',
            'ok' => function_exists('session_start') && isset($_SESSION),
            'fix' => 'session در PHP غیرفعال است.',
            'warn' => false,
        ],
        [
            'label' => 'وجود بسته '.PACKAGE_FILE,
            'ok' => is_file($packagePath),
            'fix' => 'پوشه package و فایل jobazmoon-core.zip را کنار install.php آپلود کنید.',
            'warn' => false,
        ],
        [
            'label' => 'فضای دیسک کافی',
            'ok' => ((int) @disk_free_space($publicHtml)) >= MIN_DISK_BYTES,
            'fix' => 'حداقل حدود ۴۰۰ مگابایت فضای خالی لازم است.',
            'warn' => false,
        ],
        [
            'label' => 'نوشتنی بودن public_html',
            'ok' => is_writable($publicHtml),
            'fix' => 'مجوز نوشتن پوشه public_html را در File Manager بررسی کنید.',
            'warn' => false,
        ],
        [
            'label' => 'امکان ایجاد پوشه job در خانه هاست',
            'ok' => is_dir($jobDir) ? is_writable($jobDir) : is_writable(dirname($jobDir)),
            'fix' => 'کاربر هاست باید بتواند کنار public_html پوشه job بسازد.',
            'warn' => false,
        ],
        [
            'label' => 'Horizon (pcntl)',
            'ok' => extension_loaded('pcntl'),
            'fix' => 'Horizon requires pcntl/posix on Linux. نصب سایت متوقف نمی‌شود.',
            'warn' => true,
        ],
        [
            'label' => 'Horizon (posix)',
            'ok' => extension_loaded('posix'),
            'fix' => 'Horizon requires pcntl/posix on Linux. نصب سایت متوقف نمی‌شود.',
            'warn' => true,
        ],
    ];

    return $items;
}

function required_failed(array $items): array
{
    return array_values(array_filter($items, static fn (array $i): bool => ! $i['ok'] && empty($i['warn'])));
}

function write_env_file(string $path, array $overrides): void
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

function pdo_connect(array $db, bool $withDb): PDO
{
    $dsn = $withDb
        ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name'])
        : sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']);

    return new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

function rrmdir(string $dir): void
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

function copy_dir(string $src, string $dst, array $skipNames = []): void
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

function chmod_tree(string $dir, int $mode): void
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

function write_public_index(string $publicHtml): void
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

function write_htaccess(string $publicHtml): void
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

function laravel_app(string $jobDir)
{
    static $app = null;
    if ($app !== null) {
        return $app;
    }
    chdir($jobDir);
    require_once $jobDir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
    $app = require $jobDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    return $app;
}

function artisan(string $jobDir, string $command, array $params = []): array
{
    $app = laravel_app($jobDir);
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $code = $kernel->call($command, $params);

    return ['code' => $code, 'output' => $kernel->output()];
}

function create_admin(string $jobDir, array $site): string
{
    laravel_app($jobDir);

    $user = app(App\Models\User::class);
    $table = $user->getTable();
    $columns = Illuminate\Support\Facades\Schema::getColumnListing($table);

    $existing = null;
    if (in_array('email', $columns, true)) {
        $existing = Illuminate\Support\Facades\DB::table($table)->where('email', $site['email'])->first();
    }

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
        $payload['password'] = password_hash($site['password'], PASSWORD_BCRYPT);
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

        return 'updated';
    }

    $payload['created_at'] = date('Y-m-d H:i:s');
    Illuminate\Support\Facades\DB::table($table)->insert($payload);

    if (class_exists(Spatie\Permission\Models\Role::class)
        && Illuminate\Support\Facades\Schema::hasTable('roles')) {
        try {
            $role = Spatie\Permission\Models\Role::query()->where('name', 'admin')->first();
            if ($role) {
                $model = App\Models\User::query()->where('email', $site['email'])->first();
                if ($model && method_exists($model, 'assignRole')) {
                    $model->assignRole($role);
                }
            }
        } catch (Throwable) {
        }
    }

    return 'created';
}

function verify_install(string $jobDir, string $publicHtml): array
{
    $checks = [];
    $add = static function (string $label, bool $ok, string $detail = '') use (&$checks): void {
        $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    };

    $env = $jobDir.'/.env';
    $envOk = is_file($env);
    $keyOk = false;
    if ($envOk) {
        $keyOk = (bool) preg_match('/^APP_KEY=.+/m', (string) file_get_contents($env));
    }
    $add('وجود APP_KEY', $keyOk);
    $add('vendor/autoload.php', is_file($jobDir.'/vendor/autoload.php'));
    $add('bootstrap/app.php', is_file($jobDir.'/bootstrap/app.php'));
    $index = is_file($publicHtml.'/index.php') ? (string) file_get_contents($publicHtml.'/index.php') : '';
    $add('public_html/index.php', str_contains($index, 'autoload.php') && str_contains($index, 'job'));
    $add('.htaccess', is_file($publicHtml.'/.htaccess'));
    $add('storage قابل نوشتن', writable_check($jobDir.'/storage'));
    $add('bootstrap/cache قابل نوشتن', writable_check($jobDir.'/bootstrap/cache'));
    $add('storage/installed', is_file($jobDir.'/storage/installed'));

    $dbOk = false;
    $dbDetail = '';
    try {
        if ($envOk) {
            $vars = [];
            foreach (file($env, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if ($line === '' || str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v, " \t\"'");
            }
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $vars['DB_HOST'] ?? '127.0.0.1', $vars['DB_PORT'] ?? '3306', $vars['DB_DATABASE'] ?? ''),
                $vars['DB_USERNAME'] ?? '',
                $vars['DB_PASSWORD'] ?? '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->query('SELECT 1');
            $dbOk = true;
        }
    } catch (Throwable $e) {
        $dbDetail = 'اتصال ناموفق';
    }
    $add('اتصال پایگاه‌داده', $dbOk, $dbDetail);

    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $add('HTTPS', $https, $https ? '' : 'پس از نصب گواهی SSL را در cPanel فعال کنید.');

    return $checks;
}

function run_install(string $jobDir, string $publicHtml, string $packagePath, array $site, array $db): array
{
    $log = [];
    $warnings = [];
    $push = static function (string $m) use (&$log): void {
        $log[] = $m;
    };

    if (! is_dir($jobDir) && ! mkdir($jobDir, 0755, true) && ! is_dir($jobDir)) {
        throw new RuntimeException('ساخت پوشه job ممکن نشد.');
    }

    $zip = new ZipArchive;
    if ($zip->open($packagePath) !== true) {
        throw new RuntimeException('باز کردن بسته نصب ممکن نشد.');
    }
    $tmp = $publicHtml.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'_extract_'.bin2hex(random_bytes(4));
    mkdir($tmp, 0755, true);
    if (! $zip->extractTo($tmp)) {
        $zip->close();
        throw new RuntimeException('استخراج بسته نصب ممکن نشد.');
    }
    $zip->close();
    $push('بسته استخراج شد.');

    $source = $tmp;
    if (is_dir($tmp.DIRECTORY_SEPARATOR.'app') === false) {
        $dirs = glob($tmp.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];
        if (isset($dirs[0]) && is_dir($dirs[0].DIRECTORY_SEPARATOR.'app')) {
            $source = $dirs[0];
        }
    }

    copy_dir($source, $jobDir);
    $push('فایل‌های Laravel در پوشه job کپی شد.');

    $pubSrc = $jobDir.DIRECTORY_SEPARATOR.'public';
    if (is_dir($pubSrc)) {
        if (is_file($publicHtml.DIRECTORY_SEPARATOR.'index.html')) {
            @rename($publicHtml.DIRECTORY_SEPARATOR.'index.html', $publicHtml.DIRECTORY_SEPARATOR.'index.html.backup');
        }
        copy_dir($pubSrc, $publicHtml, ['install.php', 'package']);
        $push('فایل‌های public به public_html منتقل شد.');
    }

    write_public_index($publicHtml);
    write_htaccess($publicHtml);
    $push('index.php و .htaccess تنظیم شد.');

    $example = $jobDir.DIRECTORY_SEPARATOR.'.env.example';
    $envPath = $jobDir.DIRECTORY_SEPARATOR.'.env';
    if (is_file($example)) {
        copy($example, $envPath);
    }
    $appKey = 'base64:'.base64_encode(random_bytes(32));
    write_env_file($envPath, [
        'APP_NAME' => $site['site_name'],
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL' => $site['url'],
        'APP_KEY' => $appKey,
        'APP_INSTALLED' => 'true',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => $db['host'],
        'DB_PORT' => $db['port'],
        'DB_DATABASE' => $db['name'],
        'DB_USERNAME' => $db['user'],
        'DB_PASSWORD' => $db['pass'],
        'CACHE_STORE' => 'database',
        'SESSION_DRIVER' => 'database',
        'QUEUE_CONNECTION' => 'database',
    ]);
    $push('.env ساخته شد.');

    foreach (['DB_HOST' => $db['host'], 'DB_PORT' => $db['port'], 'DB_DATABASE' => $db['name'], 'DB_USERNAME' => $db['user'], 'DB_PASSWORD' => $db['pass'], 'APP_KEY' => $appKey, 'APP_URL' => $site['url']] as $k => $v) {
        putenv($k.'='.$v);
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }

    chmod_tree($jobDir.DIRECTORY_SEPARATOR.'storage', 0775);
    @chmod($jobDir.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache', 0775);

    try {
        $pdo = pdo_connect($db, false);
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $db['name']);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $push('پایگاه‌داده بررسی شد.');
    } catch (Throwable $e) {
        throw new RuntimeException('دیتابیس: اتصال یا ساخت ناموفق بود.');
    }

    $mig = artisan($jobDir, 'migrate', ['--force' => true]);
    $push('migrate اجرا شد.');
    if ($mig['code'] !== 0) {
        throw new RuntimeException('migrate ناموفق بود.');
    }

    try {
        artisan($jobDir, 'db:seed', ['--force' => true]);
        $push('seed اجرا شد.');
    } catch (Throwable $e) {
        $warnings[] = 'seed کامل نشد (نصب ادامه یافت).';
    }

    try {
        artisan($jobDir, 'storage:link', ['--force' => true]);
        $push('storage:link اجرا شد.');
    } catch (Throwable $e) {
        $link = $publicHtml.DIRECTORY_SEPARATOR.'storage';
        $target = $jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';
        if (! file_exists($link)) {
            @symlink($target, $link);
        }
        $warnings[] = 'storage:link ممکن است روی هاست بدون SSH محدود باشد.';
    }

    create_admin($jobDir, $site);
    $push('کاربر مدیر ذخیره شد.');

    try {
        artisan($jobDir, 'optimize:clear');
        artisan($jobDir, 'config:cache');
        artisan($jobDir, 'route:cache');
        artisan($jobDir, 'view:cache');
        $push('بهینه‌سازی production اجرا شد.');
    } catch (Throwable $e) {
        $warnings[] = 'برخی دستورات cache روی این هاست اجرا نشدند.';
    }

    if (class_exists(App\Models\Setting::class)) {
        try {
            App\Models\Setting::set('site_name', $site['site_name'], 'general');
        } catch (Throwable) {
        }
    }

    file_put_contents($jobDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'installed', json_encode([
        'installed_at' => date('c'),
    ]));

    rrmdir($tmp);

    $deleted = [];
    $locked = true;
    foreach ([$packagePath, __FILE__] as $f) {
        if (is_file($f) && @unlink($f)) {
            $deleted[] = basename($f);
        } else {
            $locked = false;
        }
    }

    return [
        'log' => $log,
        'warnings' => $warnings,
        'deleted' => $deleted,
        'installer_removed' => in_array('install.php', $deleted, true),
        'verify' => verify_install($jobDir, $publicHtml),
    ];
}

$step = (int) ($_SESSION['step'] ?? 1);
$errors = [];
$result = null;

if (already_installed($jobDir) && ($_GET['step'] ?? '') !== 'done') {
    $locked = true;
} else {
    $locked = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_ok()) {
        $errors[] = 'نشست امنیتی نامعتبر است. صفحه را تازه کنید.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'requirements') {
            $failed = required_failed(requirements($jobDir, $packagePath, $publicHtml));
            if ($failed) {
                $errors[] = 'پیش‌نیازهای ضروری کامل نیست.';
                $step = 1;
            } else {
                $_SESSION['step'] = $step = 2;
            }
        } elseif ($action === 'site') {
            $site = [
                'site_name' => trim((string) ($_POST['site_name'] ?? '')),
                'url' => trim((string) ($_POST['site_url'] ?? '')),
                'name' => trim((string) ($_POST['admin_name'] ?? '')),
                'email' => trim((string) ($_POST['admin_email'] ?? '')),
                'mobile' => trim((string) ($_POST['admin_mobile'] ?? '')),
                'password' => (string) ($_POST['admin_password'] ?? ''),
                'password_confirmation' => (string) ($_POST['admin_password_confirmation'] ?? ''),
            ];
            if ($site['site_name'] === '' || $site['url'] === '' || $site['name'] === '') {
                $errors[] = 'نام سایت، آدرس و نام مدیر الزامی است.';
            }
            if (! filter_var($site['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'ایمیل مدیر معتبر نیست.';
            }
            if (! preg_match('/^09\d{9}$/', $site['mobile'])) {
                $errors[] = 'موبایل باید ۱۱ رقم و با 09 شروع شود.';
            }
            if (strlen($site['password']) < 8 || $site['password'] !== $site['password_confirmation']) {
                $errors[] = 'رمز حداقل ۸ کاراکتر و با تکرار یکسان باشد.';
            }
            if (! $errors) {
                unset($site['password_confirmation']);
                $_SESSION['site'] = $site;
                $_SESSION['step'] = $step = 3;
            } else {
                $step = 2;
            }
        } elseif ($action === 'database') {
            $db = [
                'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
                'port' => trim((string) ($_POST['db_port'] ?? '3306')),
                'name' => trim((string) ($_POST['db_name'] ?? '')),
                'user' => trim((string) ($_POST['db_user'] ?? '')),
                'pass' => (string) ($_POST['db_pass'] ?? ''),
            ];
            try {
                pdo_connect($db, true);
                $_SESSION['db'] = $db;
                $_SESSION['step'] = $step = 4;
            } catch (Throwable $e) {
                try {
                    pdo_connect($db, false);
                    $_SESSION['db'] = $db;
                    $_SESSION['step'] = $step = 4;
                } catch (Throwable) {
                    $errors[] = 'اتصال به MySQL برقرار نشد. هاست، نام کاربری و رمز دیتابیس cPanel را بررسی کنید.';
                    $step = 3;
                }
            }
        } elseif ($action === 'install') {
            if (empty($_SESSION['site']) || empty($_SESSION['db'])) {
                $errors[] = 'مراحل قبل کامل نشده است.';
                $step = 1;
            } elseif ($locked) {
                $errors[] = 'این سایت قبلاً نصب شده است.';
            } else {
                try {
                    $result = run_install($jobDir, $publicHtml, $packagePath, $_SESSION['site'], $_SESSION['db']);
                    unset($_SESSION['site'], $_SESSION['db']);
                    $_SESSION['step'] = $step = 5;
                } catch (Throwable $e) {
                    $errors[] = 'نصب متوقف شد. جزئیات حساس در گزارش نیامده است. مجوز پوشه‌ها و دیتابیس را بررسی کنید.';
                    $step = 4;
                }
            }
        }
    }
}

$reqs = requirements($jobDir, $packagePath, $publicHtml);
$token = $_SESSION['csrf'];
$siteOld = $_SESSION['site'] ?? [];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نصب <?= h(INSTALLER_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{font-family:Vazirmatn,sans-serif}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
<div class="mx-auto max-w-3xl px-4 py-10">
    <h1 class="mb-2 text-center text-2xl font-bold text-slate-900">نصب <?= h(INSTALLER_NAME) ?></h1>
    <p class="mb-8 text-center text-sm text-slate-500">نصب روی cPanel بدون SSH و بدون Composer</p>

    <?php if ($locked && $step !== 5): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm leading-7">
            <p class="font-bold">این سایت قبلاً نصب شده است.</p>
            <p>برای امنیت، نصب‌کننده را دوباره اجرا نکنید. فایل <code>install.php</code> را از public_html حذف کنید.</p>
        </div>
    <?php else: ?>
        <div class="mb-6 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full bg-rose-500" style="width:<?= (int) (($step / 5) * 100) ?>%"></div></div>

        <?php foreach ($errors as $err): ?>
            <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700"><?= h($err) ?></div>
        <?php endforeach; ?>

        <?php if ($step === 1): ?>
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 font-bold">۱. بررسی محیط</h2>
                <ul class="divide-y text-sm">
                    <?php foreach ($reqs as $item): ?>
                        <li class="flex justify-between gap-3 py-2">
                            <span><?= h($item['label']) ?></span>
                            <span class="<?= $item['ok'] ? 'text-emerald-600' : ($item['warn'] ? 'text-amber-600' : 'text-red-600') ?>">
                                <?= $item['ok'] ? 'PASS' : ($item['warn'] ? 'WARNING' : 'FAIL') ?>
                            </span>
                        </li>
                        <?php if (! $item['ok']): ?>
                            <li class="pb-2 text-xs text-slate-500"><?= h($item['fix']) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <form method="post" class="mt-6">
                    <input type="hidden" name="_token" value="<?= h($token) ?>">
                    <input type="hidden" name="action" value="requirements">
                    <button class="w-full rounded-xl bg-rose-500 py-3 text-sm font-bold text-white" <?= required_failed($reqs) ? 'disabled' : '' ?>>ادامه</button>
                </form>
            </div>
        <?php elseif ($step === 2): ?>
            <form method="post" class="space-y-3 rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 font-bold">۲. اطلاعات سایت و مدیر</h2>
                <input type="hidden" name="_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="site">
                <label class="block text-sm font-bold">Site Name</label>
                <input name="site_name" required class="w-full rounded-xl border px-3 py-2" value="<?= h($siteOld['site_name'] ?? INSTALLER_NAME) ?>">
                <label class="block text-sm font-bold">Site URL</label>
                <input name="site_url" required dir="ltr" class="w-full rounded-xl border px-3 py-2" value="<?= h($siteOld['url'] ?? default_url()) ?>">
                <label class="block text-sm font-bold">Admin Name</label>
                <input name="admin_name" required class="w-full rounded-xl border px-3 py-2" value="<?= h($siteOld['name'] ?? '') ?>">
                <label class="block text-sm font-bold">Admin Email</label>
                <input type="email" name="admin_email" required dir="ltr" class="w-full rounded-xl border px-3 py-2" value="<?= h($siteOld['email'] ?? '') ?>">
                <label class="block text-sm font-bold">Admin Mobile</label>
                <input name="admin_mobile" required dir="ltr" class="w-full rounded-xl border px-3 py-2" placeholder="09xxxxxxxxx" value="<?= h($siteOld['mobile'] ?? '') ?>">
                <label class="block text-sm font-bold">Admin Password</label>
                <input type="password" name="admin_password" required minlength="8" class="w-full rounded-xl border px-3 py-2">
                <label class="block text-sm font-bold">Confirm Password</label>
                <input type="password" name="admin_password_confirmation" required minlength="8" class="w-full rounded-xl border px-3 py-2">
                <button class="w-full rounded-xl bg-rose-500 py-3 text-sm font-bold text-white">ادامه</button>
            </form>
        <?php elseif ($step === 3): ?>
            <form method="post" class="space-y-3 rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 font-bold">۳. پایگاه‌داده</h2>
                <input type="hidden" name="_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="database">
                <label class="block text-sm font-bold">Database Host</label>
                <input name="db_host" required dir="ltr" class="w-full rounded-xl border px-3 py-2" value="127.0.0.1">
                <label class="block text-sm font-bold">Database Port</label>
                <input name="db_port" required dir="ltr" class="w-full rounded-xl border px-3 py-2" value="3306">
                <label class="block text-sm font-bold">Database Name</label>
                <input name="db_name" required dir="ltr" class="w-full rounded-xl border px-3 py-2">
                <label class="block text-sm font-bold">Database Username</label>
                <input name="db_user" required dir="ltr" class="w-full rounded-xl border px-3 py-2">
                <label class="block text-sm font-bold">Database Password</label>
                <input type="password" name="db_pass" dir="ltr" class="w-full rounded-xl border px-3 py-2">
                <button class="w-full rounded-xl bg-rose-500 py-3 text-sm font-bold text-white">تست اتصال و ادامه</button>
            </form>
        <?php elseif ($step === 4): ?>
            <form method="post" class="rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="mb-2 font-bold">۴. شروع نصب</h2>
                <p class="mb-4 text-sm leading-7 text-slate-600">Laravel در پوشه <code>job</code> و فایل‌های عمومی در <code>public_html</code> قرار می‌گیرد. جداول موجود DROP نمی‌شوند.</p>
                <input type="hidden" name="_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="install">
                <button class="w-full rounded-xl bg-rose-500 py-3 text-sm font-bold text-white">نصب را شروع کن</button>
            </form>
        <?php elseif ($step === 5 && $result): ?>
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 font-bold">نصب تمام شد</h2>
                <ul class="mb-4 list-disc pr-5 text-sm">
                    <?php foreach ($result['log'] as $line): ?><li><?= h($line) ?></li><?php endforeach; ?>
                </ul>
                <?php foreach ($result['warnings'] as $w): ?>
                    <p class="mb-2 text-sm text-amber-700"><?= h($w) ?></p>
                <?php endforeach; ?>
                <?php if (! extension_loaded('pcntl') || ! extension_loaded('posix')): ?>
                    <p class="mb-2 text-sm text-amber-700">Horizon requires pcntl/posix on Linux.</p>
                <?php endif; ?>
                <h3 class="mb-2 font-bold">گزارش نهایی</h3>
                <ul class="divide-y text-sm">
                    <?php foreach ($result['verify'] as $c): ?>
                        <li class="flex justify-between py-2">
                            <span><?= h($c['label']) ?></span>
                            <span class="<?= $c['ok'] ? 'text-emerald-600' : 'text-red-600' ?>"><?= $c['ok'] ? 'PASS' : 'FAIL' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (empty($result['installer_removed'])): ?>
                    <p class="mt-4 rounded-xl bg-red-50 p-3 text-sm font-bold text-red-700">فایل install.php حذف نشد. فوراً آن را از File Manager پاک کنید.</p>
                <?php endif; ?>
                <a class="mt-4 inline-block rounded-xl bg-rose-500 px-5 py-3 text-sm font-bold text-white" href="/">ورود به سایت</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
