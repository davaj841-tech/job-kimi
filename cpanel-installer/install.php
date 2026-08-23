<?php

declare(strict_types=1);

/**
 * JobAzmoon cPanel installer — WordPress-style wizard (no SSH / no Composer).
 */

@set_time_limit(0);
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '0');

session_start();

require_once __DIR__.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'InstallEngine.php';

const INSTALLER_NAME = 'JobAzmoon';

$publicHtml = __DIR__;
$homeDir = dirname($publicHtml);
$jobDir = $homeDir.DIRECTORY_SEPARATOR.'job';
$packagePath = $publicHtml.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.InstallEngine::PACKAGE_FILE;

$engine = new InstallEngine($publicHtml, $homeDir, $jobDir, $packagePath, __FILE__);

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

$step = (int) ($_SESSION['step'] ?? 1);
$errors = [];
$result = null;
$dbState = $_SESSION['db_state'] ?? ['state' => 'unknown', 'table_count' => 0];
$installStatus = $engine->installationStatus();
$locked = $engine->isLocked() && ($step !== 5 || empty($result));
$blockedStatus = in_array($installStatus, ['incomplete', 'corrupted'], true) && $step !== 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $locked && ! $blockedStatus) {
    if (! csrf_ok()) {
        $errors[] = 'نشست امنیتی نامعتبر است. صفحه را تازه کنید.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'requirements') {
            if ($engine->requiredFailures($engine->requirements()) !== []) {
                $errors[] = 'پیش‌نیازهای ضروری کامل نیست.';
                $step = 1;
            } else {
                $_SESSION['step'] = $step = 2;
            }
        } elseif ($action === 'database_test') {
            header('Content-Type: application/json; charset=utf-8');
            $db = [
                'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
                'port' => trim((string) ($_POST['db_port'] ?? '3306')),
                'name' => trim((string) ($_POST['db_name'] ?? '')),
                'user' => trim((string) ($_POST['db_user'] ?? '')),
                'pass' => (string) ($_POST['db_pass'] ?? ''),
            ];
            echo json_encode($engine->testDatabase($db), JSON_UNESCAPED_UNICODE);
            exit;
        } elseif ($action === 'database') {
            $db = [
                'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
                'port' => trim((string) ($_POST['db_port'] ?? '3306')),
                'name' => trim((string) ($_POST['db_name'] ?? '')),
                'user' => trim((string) ($_POST['db_user'] ?? '')),
                'pass' => (string) ($_POST['db_pass'] ?? ''),
            ];
            $inputErrors = $engine->validateDatabaseInput($db);
            if ($inputErrors !== []) {
                $errors[] = $inputErrors[0];
                $step = 2;
            } else {
                $test = $engine->testDatabase($db);
                if (! $test['ok']) {
                    $errors[] = $test['message'];
                    $step = 2;
                } elseif ($test['state'] === 'has_tables' && empty($_POST['confirm_existing_db'])) {
                    $errors[] = 'این پایگاه‌داده '.$test['table_count'].' جدول دارد. برای ادامه گزینه تأیید را علامت بزنید.';
                    $_SESSION['db'] = $db;
                    $_SESSION['db_state'] = $test;
                    $dbState = $test;
                    $step = 2;
                } else {
                    $_SESSION['db'] = $db;
                    $_SESSION['db_state'] = $test;
                    $_SESSION['step'] = $step = 3;
                }
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
            $siteErrors = $engine->validateSiteInput($site);
            if ($siteErrors !== []) {
                $errors = array_merge($errors, $siteErrors);
                $step = 3;
            } else {
                unset($site['password_confirmation']);
                $_SESSION['site'] = $site;
                $_SESSION['step'] = $step = 4;
            }
        } elseif ($action === 'install') {
            if (empty($_SESSION['site']) || empty($_SESSION['db'])) {
                $errors[] = 'مراحل قبل کامل نشده است.';
                $step = 1;
            } elseif (empty($_POST['confirm_install'])) {
                $errors[] = 'برای شروع نصب باید تأیید نهایی را علامت بزنید.';
                $step = 4;
            } else {
                try {
                    $result = $engine->runInstall(
                        $_SESSION['site'],
                        $_SESSION['db'],
                        ! empty($_POST['confirm_existing_db']) || (($_SESSION['db_state']['state'] ?? '') !== 'has_tables')
                    );
                    unset($_SESSION['site'], $_SESSION['db'], $_SESSION['db_state']);
                    session_regenerate_id(true);
                    $_SESSION['step'] = $step = 5;
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                    $step = 4;
                }
            }
        }
    }
}

$reqs = $engine->requirements();
$token = $_SESSION['csrf'];
$siteOld = $_SESSION['site'] ?? [];
$dbOld = $_SESSION['db'] ?? [];
// Never echo secrets into HTML (password fields stay empty; step 4/5 never print them).
unset($siteOld['password'], $siteOld['password_confirmation'], $dbOld['pass']);
$dbState = $_SESSION['db_state'] ?? $dbState;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نصب <?= h(INSTALLER_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: Vazirmatn, sans-serif; }
        .field { width: 100%; border-radius: 0.75rem; border: 1px solid #e2e8f0; padding: 0.65rem 0.85rem; font-size: 0.875rem; }
        .field:focus { outline: none; border-color: #ef394e; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
<div class="mx-auto max-w-3xl px-4 py-10">
    <h1 class="mb-2 text-center text-2xl font-bold text-slate-900">نصب <?= h(INSTALLER_NAME) ?></h1>
    <p class="mb-6 text-center text-sm text-slate-500">۵ مرحله — مشابه نصب وردپرس روی cPanel</p>

    <?php if ($locked && $step !== 5): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm leading-7">
            <p class="font-bold text-amber-900">این سایت قبلاً نصب شده است.</p>
            <p class="mt-2">نصب‌کننده قفل شده و دوباره قابل اجرا نیست. اگر فایل <code>install.php</code> هنوز روی هاست است، فوراً حذفش کنید.</p>
            <a href="/" class="mt-4 inline-block rounded-xl bg-rose-500 px-5 py-2 text-sm font-bold text-white">ورود به سایت</a>
        </div>
    <?php elseif ($blockedStatus): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-6 text-sm leading-7">
            <?php if ($installStatus === 'incomplete'): ?>
                <p class="font-bold text-red-900">نصب ناقص تشخیص داده شد.</p>
                <p class="mt-2">پوشه <code>job</code> (یا فایل artisan) وجود دارد ولی نشانگر <code>storage/installed</code> نیست. برای جلوگیری از بازنویسی داده‌ها، نصب متوقف شده است. پوشه job را در File Manager بررسی یا پس از پشتیبان‌گیری پاک کنید و دوباره تلاش کنید.</p>
            <?php else: ?>
                <p class="font-bold text-red-900">وضعیت نصب خراب به‌نظر می‌رسد.</p>
                <p class="mt-2">ترکیب فایل‌های .env / vendor / bootstrap / نشانگر نصب ناسازگار است. نصب‌کننده از ادامه خودداری می‌کند — فایل‌های job را دستی بررسی کنید.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php
        $labels = [1 => 'پیش‌نیاز', 2 => 'پایگاه‌داده', 3 => 'سایت و مدیر', 4 => 'تأیید', 5 => 'پایان'];
        ?>
        <ol class="mb-4 grid grid-cols-5 gap-1 text-center text-[10px] sm:text-xs">
            <?php foreach ($labels as $n => $label): ?>
                <li class="<?= $step >= $n ? 'font-bold text-rose-600' : 'text-slate-400' ?>"><?= $n ?>. <?= h($label) ?></li>
            <?php endforeach; ?>
        </ol>
        <div class="mb-6 h-2 overflow-hidden rounded-full bg-slate-200">
            <div class="h-full bg-rose-500 transition-all" style="width:<?= (int) (($step / 5) * 100) ?>%"></div>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= h($err) ?></div>
        <?php endforeach; ?>

        <?php if ($step === 1): ?>
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 font-bold">۱. بررسی محیط سرور</h2>
                <ul class="divide-y text-sm">
                    <?php foreach ($reqs as $item): ?>
                        <li class="flex justify-between gap-3 py-2">
                            <span><?= h($item['label']) ?></span>
                            <span class="<?= $item['ok'] ? 'text-emerald-600' : ($item['warn'] ? 'text-amber-600' : 'text-red-600') ?>">
                                <?= $item['ok'] ? '✓' : ($item['warn'] ? '!' : '✗') ?>
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
                    <button class="w-full rounded-xl bg-rose-500 py-3 text-sm font-bold text-white disabled:opacity-50" <?= $engine->requiredFailures($reqs) !== [] ? 'disabled' : '' ?>>ادامه</button>
                </form>
            </div>

        <?php elseif ($step === 2): ?>
            <form id="db-form" method="post" class="space-y-3 rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="mb-2 font-bold">۲. پایگاه‌داده MySQL</h2>
                <p class="mb-4 text-sm text-slate-600">اطلاعات را از cPanel → MySQL Databases بگیرید. رمز در گزارش نصب نمایش داده نمی‌شود.</p>
                <input type="hidden" name="_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="database">
                <label class="block text-sm font-bold">هاست</label>
                <input name="db_host" required dir="ltr" class="field" value="<?= h($dbOld['host'] ?? '127.0.0.1') ?>">
                <label class="block text-sm font-bold">پورت</label>
                <input name="db_port" required dir="ltr" class="field" value="<?= h($dbOld['port'] ?? '3306') ?>">
                <label class="block text-sm font-bold">نام پایگاه‌داده</label>
                <input name="db_name" required dir="ltr" class="field" pattern="[A-Za-z0-9_]+" value="<?= h($dbOld['name'] ?? '') ?>">
                <label class="block text-sm font-bold">نام کاربری</label>
                <input name="db_user" required dir="ltr" class="field" value="<?= h($dbOld['user'] ?? '') ?>">
                <label class="block text-sm font-bold">رمز عبور</label>
                <input type="password" name="db_pass" dir="ltr" class="field" autocomplete="new-password">
                <p id="db-status" class="hidden rounded-xl px-3 py-2 text-sm"></p>
                <?php if (($dbState['state'] ?? '') === 'has_tables'): ?>
                    <label class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm">
                        <input type="checkbox" name="confirm_existing_db" value="1" class="mt-1">
                        <span>می‌دانم این پایگاه <?= (int) ($dbState['table_count'] ?? 0) ?> جدول دارد. migration روی جداول موجود اجرا می‌شود و هیچ جدولی DROP نمی‌شود.</span>
                    </label>
                <?php endif; ?>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="button" id="test-db" class="flex-1 rounded-xl border border-slate-200 bg-white py-3 text-sm font-bold">تست اتصال</button>
                    <button type="submit" class="flex-1 rounded-xl bg-rose-500 py-3 text-sm font-bold text-white">ذخیره و ادامه</button>
                </div>
            </form>

        <?php elseif ($step === 3): ?>
            <form method="post" class="space-y-3 rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 font-bold">۳. اطلاعات سایت و مدیر</h2>
                <input type="hidden" name="_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="site">
                <label class="block text-sm font-bold">نام سایت</label>
                <input name="site_name" required class="field" value="<?= h($siteOld['site_name'] ?? INSTALLER_NAME) ?>">
                <label class="block text-sm font-bold">آدرس سایت (URL)</label>
                <input name="site_url" required dir="ltr" class="field" value="<?= h($siteOld['url'] ?? $engine->defaultUrl()) ?>">
                <label class="block text-sm font-bold">نام مدیر</label>
                <input name="admin_name" required class="field" value="<?= h($siteOld['name'] ?? '') ?>">
                <label class="block text-sm font-bold">ایمیل مدیر</label>
                <input type="email" name="admin_email" required dir="ltr" class="field" value="<?= h($siteOld['email'] ?? '') ?>">
                <label class="block text-sm font-bold">موبایل مدیر</label>
                <input name="admin_mobile" required dir="ltr" class="field" placeholder="09xxxxxxxxx" value="<?= h($siteOld['mobile'] ?? '') ?>">
                <label class="block text-sm font-bold">رمز مدیر</label>
                <input type="password" name="admin_password" required minlength="8" class="field" autocomplete="new-password">
                <label class="block text-sm font-bold">تکرار رمز</label>
                <input type="password" name="admin_password_confirmation" required minlength="8" class="field" autocomplete="new-password">
                <button class="w-full rounded-xl bg-rose-500 py-3 text-sm font-bold text-white">ادامه</button>
            </form>

        <?php elseif ($step === 4): ?>
            <form method="post" class="rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="mb-4 font-bold">۴. تأیید و شروع نصب</h2>
                <dl class="mb-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4 border-b pb-2"><dt class="text-slate-500">نام سایت</dt><dd class="font-bold"><?= h($_SESSION['site']['site_name'] ?? '') ?></dd></div>
                    <div class="flex justify-between gap-4 border-b pb-2"><dt class="text-slate-500">آدرس</dt><dd dir="ltr" class="font-bold"><?= h($_SESSION['site']['url'] ?? '') ?></dd></div>
                    <div class="flex justify-between gap-4 border-b pb-2"><dt class="text-slate-500">ایمیل مدیر</dt><dd dir="ltr"><?= h($_SESSION['site']['email'] ?? '') ?></dd></div>
                    <div class="flex justify-between gap-4 border-b pb-2"><dt class="text-slate-500">پایگاه‌داده</dt><dd dir="ltr"><?= h($_SESSION['db']['name'] ?? '') ?></dd></div>
                </dl>
                <p class="mb-4 text-sm leading-7 text-slate-600">
                    هسته Laravel در <code>job/</code> و فایل‌های وب در <code>public_html</code> قرار می‌گیرد.
                    migration اجرا می‌شود؛ جداول موجود DROP نمی‌شوند.
                </p>
                <label class="mb-4 flex items-start gap-2 text-sm">
                    <input type="checkbox" name="confirm_install" value="1" required class="mt-1">
                    <span>آماده نصب هستم. می‌دانم رمزها و APP_KEY در این صفحه نمایش داده نمی‌شوند.</span>
                </label>
                <input type="hidden" name="_token" value="<?= h($token) ?>">
                <input type="hidden" name="action" value="install">
                <?php if (($_SESSION['db_state']['state'] ?? '') === 'has_tables'): ?>
                    <input type="hidden" name="confirm_existing_db" value="1">
                <?php endif; ?>
                <button class="w-full rounded-xl bg-rose-500 py-3 text-sm font-bold text-white">شروع نصب</button>
            </form>

        <?php elseif ($step === 5 && $result): ?>
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="mb-2 text-xl font-bold text-emerald-700">نصب با موفقیت انجام شد</h2>
                <p class="mb-4 text-sm text-slate-600">رمز مدیر و اطلاعات حساس در این صفحه نمایش داده نمی‌شوند — آن‌ها را یادداشت کرده‌اید.</p>
                <ul class="mb-4 list-disc pr-5 text-sm leading-7">
                    <?php foreach ($result['log'] as $line): ?><li><?= h($line) ?></li><?php endforeach; ?>
                </ul>
                <?php foreach ($result['warnings'] as $w): ?>
                    <p class="mb-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800"><?= h($w) ?></p>
                <?php endforeach; ?>
                <h3 class="mb-2 font-bold">گزارش نهایی</h3>
                <ul class="divide-y text-sm">
                    <?php foreach ($result['verify'] as $c): ?>
                        <?php
                        $level = $c['level'] ?? ($c['ok'] ? 'pass' : 'fail');
                        $levelClass = match ($level) {
                            'warning' => 'text-amber-600',
                            'fail' => 'text-red-600',
                            default => 'text-emerald-600',
                        };
                        $levelLabel = match ($level) {
                            'warning' => 'WARN',
                            'fail' => 'FAIL',
                            default => 'PASS',
                        };
                        ?>
                        <li class="flex justify-between gap-3 py-2">
                            <span>
                                <?= h($c['label']) ?>
                                <?php if (! empty($c['detail'])): ?>
                                    <span class="block text-xs text-slate-500"><?= h($c['detail']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="<?= $levelClass ?>"><?= $levelLabel ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (empty($result['installer_removed'])): ?>
                    <div class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800">
                        <p class="font-bold">هشدار امنیتی: فایل install.php هنوز روی سرور است.</p>
                        <p class="mt-2 leading-7">فوراً از File Manager آن را حذف کنید. در صورت امکان یک قانون Deny برای install.php در .htaccess اضافه شده است، ولی حذف فایل الزامی است.</p>
                    </div>
                <?php else: ?>
                    <p class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">install.php به‌صورت خودکار حذف شد.</p>
                <?php endif; ?>
                <p class="mt-4 text-xs text-slate-500">Cron پیشنهادی: <code dir="ltr">* * * * * php <?= h($jobDir) ?>/artisan schedule:run >> /dev/null 2>&amp;1</code></p>
                <a class="mt-4 inline-block rounded-xl bg-rose-500 px-5 py-3 text-sm font-bold text-white" href="/">ورود به سایت</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
document.getElementById('test-db')?.addEventListener('click', async function () {
    const form = document.getElementById('db-form');
    const status = document.getElementById('db-status');
    if (!form || !status) return;
    const body = new FormData(form);
    body.set('action', 'database_test');
    status.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'bg-red-50', 'text-red-700');
    status.textContent = 'در حال تست اتصال...';
    status.classList.add('bg-slate-100', 'text-slate-700');
    try {
        const res = await fetch('', { method: 'POST', body });
        const data = await res.json();
        status.textContent = data.message || (data.ok ? 'موفق' : 'ناموفق');
        status.classList.remove('bg-slate-100', 'text-slate-700');
        status.classList.add(data.ok ? 'bg-emerald-50' : 'bg-red-50', data.ok ? 'text-emerald-800' : 'text-red-700');
    } catch (e) {
        status.textContent = 'خطا در ارسال درخواست.';
        status.classList.add('bg-red-50', 'text-red-700');
    }
});
</script>
</body>
</html>
