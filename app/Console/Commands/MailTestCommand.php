<?php

namespace App\Console\Commands;

use App\Mail\WelcomeMail;
use App\Services\MailConfigService;
use App\Support\EmailMask;
use Illuminate\Console\Command;
use Throwable;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {email : Destination email address}';

    protected $description = 'Send a one-off test email via configured SMTP (manual only; no secrets printed)';

    public function handle(MailConfigService $mail): int
    {
        if (app()->environment('testing')) {
            $this->error('mail:test is disabled in the testing environment.');

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->confirm('ارسال ایمیل تست در Production؟ فقط در صورت نیاز ادامه دهید.', false)) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('آدرس ایمیل نامعتبر است.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');
        $from = (string) config('mail.from.address');

        $this->info('Mailer: '.$mailer);
        $this->info('SMTP host: '.(filled($host) ? $host : '(not set)'));
        $this->info('From: '.(filled($from) ? $from : '(not set)'));
        $this->info('Recipient: '.EmailMask::mask($email));
        $this->line('Credentials are not printed.');

        try {
            $mail->applySmtpFromSettings();
            $ok = $mail->sendNow($email, new WelcomeMail('تست سیستم ایمیل'));
        } catch (Throwable $e) {
            report($e);
            $this->error('Email Integration: FAIL');
            $this->line('ارسال ناموفق بود. جزئیات فنی فقط در لاگ ثبت شد.');

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
