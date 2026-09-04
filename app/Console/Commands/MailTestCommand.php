<?php

namespace App\Console\Commands;

use App\Mail\WelcomeMail;
use App\Services\MailConfigService;
use App\Support\EmailMask;
use Illuminate\Console\Command;
use Throwable;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test
                            {email? : Destination email address; omit to print mailer config only}
                            {--force : Skip interactive confirmation in production}';

    protected $description = 'Send a one-off test email via configured SMTP (manual only; no secrets printed)';

    public function handle(MailConfigService $mail): int
    {
        if (app()->environment('testing')) {
            $this->error('mail:test is disabled in the testing environment.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');
        $port = (string) config('mail.mailers.smtp.port');
        $scheme = (string) (config('mail.mailers.smtp.scheme') ?? '');
        $from = (string) config('mail.from.address');

        $this->info('Mailer: '.$mailer);
        $this->info('SMTP host: '.(filled($host) ? $host : '(not set)'));
        $this->info('SMTP port: '.(filled($port) ? $port : '(not set)'));
        $this->info('MAIL_SCHEME: '.(filled($scheme) ? $scheme : '(null / auto)'));
        $this->info('From: '.(filled($from) ? $from : '(not set)'));
        $this->line('Credentials / passwords are never printed.');

        $rawEmail = $this->argument('email');
        if ($rawEmail === null || trim((string) $rawEmail) === '') {
            $this->warn('No email provided — config check only (no email sent).');
            $this->line('Usage: php artisan mail:test you@example.com [--force]');

            if ($mailer === 'smtp' && ! filled($host)) {
                $this->error('Email Integration: FAIL (MAIL_HOST empty)');

                return self::FAILURE;
            }

            $this->info('Email config: OK (provide an address to send a real test email).');

            return self::SUCCESS;
        }

        if (app()->environment('production') && ! $this->option('force')
            && ! $this->confirm('ارسال ایمیل تست در Production؟ فقط در صورت نیاز ادامه دهید.', false)) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $rawEmail));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('آدرس ایمیل نامعتبر است.');

            return self::FAILURE;
        }

        $this->info('Recipient: '.EmailMask::mask($email));

        if ($mailer === 'smtp' && ! filled($host)) {
            $this->error('Email Integration: FAIL');
            $this->line('MAIL_HOST خالی است. در .env یا Admin → تنظیمات ایمیل، هاست SMTP (مثلاً mail. دامنه) را تنظیم کنید.');

            return self::FAILURE;
        }

        try {
            $mail->applySmtpFromSettings();
            $ok = $mail->sendNow($email, new WelcomeMail('تست سیستم ایمیل'));
        } catch (Throwable $e) {
            report($e);
            $this->error('Email Integration: FAIL');
            $this->line('ارسال ناموفق بود. جزئیات فنی فقط در لاگ ثبت شد (storage/logs).');

            return self::FAILURE;
        }

        if (! $ok) {
            $this->error('Email Integration: FAIL (invalid recipient)');

            return self::FAILURE;
        }

        $this->info('Email Integration: PASS');

        return self::SUCCESS;
    }
}
