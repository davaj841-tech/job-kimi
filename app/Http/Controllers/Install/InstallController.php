<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PDO;
use Throwable;

class InstallController extends Controller
{
    public function __construct()
    {
        $this->ensureEnvironmentFile();
    }

    public function welcome(): View|RedirectResponse
    {
        $this->setStepAtLeast(1);

        return view('install.welcome', [
            'step' => 1,
            'requirements' => $this->requirementChecks(),
            'migrationCount' => $this->migrationCount(),
            'canContinue' => $this->requirementsPassed(),
        ]);
    }

    public function storeRequirements(): RedirectResponse
    {
        $this->assertStep(1);

        if (! $this->requirementsPassed()) {
            return redirect()
                ->route('install.welcome')
                ->withErrors(['requirements' => 'پیش‌نیازها هنوز کامل نشده‌اند.']);
        }

        session(['install_step' => 2]);

        return redirect()->route('install.database');
    }

    public function database(): View|RedirectResponse
    {
        $this->assertStep(2);

        return view('install.database', [
            'step' => 2,
            'old' => [
                'db_host' => old('db_host', env('DB_HOST', '127.0.0.1')),
                'db_port' => old('db_port', env('DB_PORT', '3306')),
                'db_database' => old('db_database', env('DB_DATABASE', '')),
                'db_username' => old('db_username', env('DB_USERNAME', '')),
                'db_password' => old('db_password', ''),
                'db_prefix' => old('db_prefix', env('DB_PREFIX', '')),
            ],
        ]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $this->assertStep(2);

        $data = $this->validateDatabase($request);

        try {
            $this->createDatabaseIfMissing($data);
            $pdo = $this->pdoWithDatabase($data);
            $pdo->query('SELECT 1');
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'اتصال برقرار شد و پایگاه‌داده آماده است.',
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        $this->assertStep(2);

        $data = $this->validateDatabase($request);

        try {
            $this->createDatabaseIfMissing($data);
            $this->pdoWithDatabase($data)->query('SELECT 1');
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['db' => $e->getMessage()]);
        }

        $this->writeEnv([
            'APP_URL' => $request->getSchemeAndHttpHost(),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'],
            'DB_PREFIX' => $data['db_prefix'],
        ]);

        $this->applyDatabaseConfig($data);
        session(['install_step' => 3]);

        return redirect()->route('install.migrate');
    }

    public function migrate(): View|RedirectResponse
    {
        $this->assertStep(3);

        return view('install.migrate', [
            'step' => 3,
            'migrationCount' => $this->migrationCount(),
        ]);
    }

    public function runMigrate(): JsonResponse
    {
        $this->assertStep(3);

        $lines = [];

        try {
            $this->reloadDatabaseFromEnv();

            $lines[] = '['.now()->toDateTimeString().'] php artisan migrate --force';
            Artisan::call('migrate', ['--force' => true]);
            $migrateOutput = trim(Artisan::output());
            foreach (preg_split("/\r\n|\n|\r/", $migrateOutput) ?: [] as $line) {
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
            $lines[] = '['.now()->toDateTimeString().'] migrate تمام شد.';

            $lines[] = '['.now()->toDateTimeString().'] php artisan db:seed --force';
            Artisan::call('db:seed', ['--force' => true]);
            $seedOutput = trim(Artisan::output());
            foreach (preg_split("/\r\n|\n|\r/", $seedOutput) ?: [] as $line) {
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
            $lines[] = '['.now()->toDateTimeString().'] db:seed تمام شد.';

            session(['install_step' => 4]);

            return response()->json([
                'ok' => true,
                'output' => implode("\n", $lines),
            ]);
        } catch (Throwable $e) {
            $lines[] = 'ERROR: '.$e->getMessage();

            return response()->json([
                'ok' => false,
                'output' => implode("\n", $lines),
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function admin(): View|RedirectResponse
    {
        $this->assertStep(4);

        return view('install.admin', [
            'step' => 4,
            'old' => [
                'site_name' => old('site_name', env('APP_NAME', '')),
                'name' => old('name', ''),
                'email' => old('email', ''),
            ],
        ]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $this->assertStep(4);

        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->writeEnv([
            'APP_NAME' => $data['site_name'],
        ]);

        config(['app.name' => $data['site_name']]);

        try {
            $this->createAdministrator($data);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['admin' => $e->getMessage()]);
        }

        $this->persistSiteNameIfPossible($data['site_name']);

        session(['install_step' => 5]);

        return redirect()->route('install.finish');
    }

    public function finish(): View|RedirectResponse
    {
        $this->assertStep(5);

        $this->writeEnv([
            'APP_INSTALLED' => 'true',
            'APP_DEBUG' => 'false',
        ]);

        File::put(
            storage_path('installed'),
            json_encode([
                'installed_at' => now()->toIso8601String(),
                'app_url' => config('app.url'),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        try {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
        } catch (Throwable) {
            // فایل نصب ساخته شده؛ پاکسازی کش نباید مانع پایان شود.
        }

        session()->forget('install_step');

        return view('install.finish', [
            'step' => 5,
            'loginUrl' => url('/login'),
            'homeUrl' => url('/'),
        ]);
    }

    protected function assertStep(int $required): void
    {
        $current = (int) session('install_step', 1);

        if ($current < $required) {
            throw new HttpResponseException(redirect($this->urlForStep(max(1, $current))));
        }
    }

    protected function setStepAtLeast(int $step): void
    {
        $current = (int) session('install_step', 1);
        if ($current < $step) {
            session(['install_step' => $step]);
        }
    }

    protected function urlForStep(int $step): string
    {
        return match ($step) {
            2 => route('install.database'),
            3 => route('install.migrate'),
            4 => route('install.admin'),
            5 => route('install.finish'),
            default => route('install.welcome'),
        };
    }

    /**
     * @return array<int, array{label: string, ok: bool, detail: string}>
     */
    protected function requirementChecks(): array
    {
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $extensions = ['openssl', 'pdo', 'pdo_mysql', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'curl'];
        $paths = [
            storage_path(),
            storage_path('app'),
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];
        if (is_file(base_path('.env'))) {
            $paths[] = base_path('.env');
        }

        $checks = [
            [
                'label' => 'نسخه PHP ≥ ۸.۲',
                'ok' => $phpOk,
                'detail' => PHP_VERSION,
            ],
        ];

        foreach ($extensions as $ext) {
            $ok = extension_loaded($ext);
            $checks[] = [
                'label' => 'افزونه '.$ext,
                'ok' => $ok,
                'detail' => $ok ? 'فعال' : 'نصب نشده',
            ];
        }

        foreach ($paths as $path) {
            $ok = is_dir($path) || is_file($path) ? is_writable($path) : is_writable(dirname($path));
            $checks[] = [
                'label' => 'نوشتنی بودن '.$this->relativePath($path),
                'ok' => $ok,
                'detail' => $ok ? 'قابل نوشتن' : 'غیرقابل نوشتن',
            ];
        }

        $checks[] = [
            'label' => 'فایل‌های migration',
            'ok' => $this->migrationCount() > 0,
            'detail' => (string) $this->migrationCount().' فایل در database/migrations',
        ];

        return $checks;
    }

    protected function requirementsPassed(): bool
    {
        foreach ($this->requirementChecks() as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    protected function migrationCount(): int
    {
        $files = glob(database_path('migrations/*.php')) ?: [];

        return count($files);
    }

    protected function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    /**
     * @return array{db_host: string, db_port: string, db_database: string, db_username: string, db_password: string, db_prefix: string}
     */
    protected function validateDatabase(Request $request): array
    {
        $validated = $request->validate([
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'numeric'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'db_prefix' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]*$/'],
        ]);

        $validated['db_password'] = (string) ($validated['db_password'] ?? '');
        $validated['db_prefix'] = (string) ($validated['db_prefix'] ?? '');

        return $validated;
    }

    /**
     * @param  array{db_host: string, db_port: string, db_database: string, db_username: string, db_password: string, db_prefix: string}  $data
     */
    protected function createDatabaseIfMissing(array $data): void
    {
        $pdo = $this->pdoWithoutDatabase($data);
        $name = str_replace('`', '', $data['db_database']);
        $pdo->exec(
            'CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    /**
     * @param  array{db_host: string, db_port: string, db_database: string, db_username: string, db_password: string, db_prefix: string}  $data
     */
    protected function pdoWithoutDatabase(array $data): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $data['db_host'], $data['db_port']),
            $data['db_username'],
            $data['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * @param  array{db_host: string, db_port: string, db_database: string, db_username: string, db_password: string, db_prefix: string}  $data
     */
    protected function pdoWithDatabase(array $data): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $data['db_host'],
                $data['db_port'],
                $data['db_database']
            ),
            $data['db_username'],
            $data['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * @param  array{db_host: string, db_port: string, db_database: string, db_username: string, db_password: string, db_prefix: string}  $data
     */
    protected function applyDatabaseConfig(array $data): void
    {
        $connection = config('database.default', 'mysql');

        config([
            "database.connections.{$connection}.host" => $data['db_host'],
            "database.connections.{$connection}.port" => $data['db_port'],
            "database.connections.{$connection}.database" => $data['db_database'],
            "database.connections.{$connection}.username" => $data['db_username'],
            "database.connections.{$connection}.password" => $data['db_password'],
            "database.connections.{$connection}.prefix" => $data['db_prefix'],
            'database.connections.mysql.host' => $data['db_host'],
            'database.connections.mysql.port' => $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => $data['db_password'],
            'database.connections.mysql.prefix' => $data['db_prefix'],
            'database.default' => 'mysql',
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    protected function reloadDatabaseFromEnv(): void
    {
        if (is_file(base_path('.env'))) {
            foreach (file(base_path('.env'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if ($line === '' || str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (in_array($key, ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_PREFIX', 'DB_CONNECTION'], true)) {
                    putenv($key.'='.$value);
                    $_ENV[$key] = $value;
                }
            }
        }

        $this->applyDatabaseConfig([
            'db_host' => (string) env('DB_HOST', '127.0.0.1'),
            'db_port' => (string) env('DB_PORT', '3306'),
            'db_database' => (string) env('DB_DATABASE', ''),
            'db_username' => (string) env('DB_USERNAME', ''),
            'db_password' => (string) env('DB_PASSWORD', ''),
            'db_prefix' => (string) env('DB_PREFIX', ''),
        ]);
    }

    /**
     * @param  array{site_name: string, name: string, email: string, password: string}  $data
     */
    protected function createAdministrator(array $data): void
    {
        $user = app(User::class);
        $table = $user->getTable();
        $columns = Schema::getColumnListing($table);
        $fillable = $user->getFillable();
        $allowed = $fillable !== [] ? array_values(array_intersect($fillable, $columns)) : $columns;

        $payload = [];
        $map = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ];

        foreach ($map as $key => $value) {
            if (in_array($key, $allowed, true) || in_array($key, $columns, true)) {
                $payload[$key] = $value;
            }
        }

        if (in_array('role', $columns, true)) {
            $payload['role'] = 'admin';
        }
        if (in_array('status', $columns, true)) {
            $payload['status'] = 'active';
        }
        if (in_array('is_verified', $columns, true)) {
            $payload['is_verified'] = true;
        }
        if (in_array('username', $columns, true) && empty($payload['username'])) {
            $payload['username'] = strstr($data['email'], '@', true) ?: 'admin';
        }
        if (in_array('mobile', $columns, true) && empty($payload['mobile'])) {
            $payload['mobile'] = '09'.substr(str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT), 0, 9);
        }

        foreach ($payload as $key => $value) {
            if (! in_array($key, $columns, true)) {
                unset($payload[$key]);
            }
        }

        $user->fill($payload);
        if (isset($payload['password'])) {
            $user->password = $payload['password'];
        }
        $user->save();
    }

    protected function persistSiteNameIfPossible(string $siteName): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
            if (! class_exists(\App\Models\Setting::class)) {
                return;
            }
            $setting = app(\App\Models\Setting::class);
            if (method_exists($setting, 'set')) {
                $setting::set('site_name', $siteName, 'general');
            }
        } catch (Throwable) {
            // اختیاری است
        }
    }

    protected function ensureEnvironmentFile(): void
    {
        $env = base_path('.env');
        $example = base_path('.env.example');

        if (! is_file($env) && is_file($example)) {
            File::copy($example, $env);
        }

        if (! is_file($env)) {
            return;
        }

        $contents = File::get($env);
        if (! preg_match('/^APP_KEY=.+/m', $contents) || preg_match('/^APP_KEY=\s*$/m', $contents)) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $this->writeEnv(['APP_KEY' => $key]);
            config(['app.key' => $key]);
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    protected function writeEnv(array $values): void
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            $example = base_path('.env.example');
            if (is_file($example)) {
                File::copy($example, $path);
            } else {
                File::put($path, '');
            }
        }

        $content = File::get($path);

        foreach ($values as $key => $value) {
            $value = (string) $value;
            $needsQuotes = preg_match('/[\s#"\']/', $value) === 1;
            $formatted = $needsQuotes ? '"'.str_replace('"', '\\"', $value).'"' : $value;
            $line = $key.'='.$formatted;

            if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $content)) {
                $content = preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $content) ?? $content;
            } else {
                $content = rtrim($content)."\n".$line."\n";
            }

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        File::put($path, $content);
    }
}
