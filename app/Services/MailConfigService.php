<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Mail\ContactFormMail;
use App\Mail\ExamReminderMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\SubscriptionExpiryMail;
use App\Mail\WelcomeMail;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class MailConfigService
{
    /**
     * Map admin/legacy encryption values to Symfony Mailer SMTP DSN schemes.
     *
     * Symfony 7 (Laravel 12) only accepts: "smtp" | "smtps".
     * - tls / starttls → smtp (STARTTLS, typically port 587)
     * - ssl / smtps → smtps (implicit TLS, typically port 465)
     * - null / none / empty → null (Laravel picks smtp vs smtps from port)
     */
    public function resolveSmtpScheme(?string $encryption, ?int $port = null): ?string
    {
        $value = strtolower(trim((string) $encryption));

        return match (true) {
            in_array($value, ['ssl', 'smtps'], true) => 'smtps',
            in_array($value, ['tls', 'starttls'], true) => 'smtp',
            in_array($value, ['smtp'], true) => 'smtp',
            in_array($value, ['null', 'none', ''], true) => null,
            default => ($port === 465) ? 'smtps' : 'smtp',
        };
    }

    /** Apply SMTP from Settings DB onto runtime config (safe for queue workers). */
    public function applySmtpFromSettings(): void
    {
        $host = Setting::getFilled('smtp_host', null);
        if (! filled($host)) {
            // Still normalize a bad MAIL_SCHEME=tls from .env so Symfony never sees it.
            $this->normalizeConfiguredSmtpScheme();

            return;
        }

        $port = (int) Setting::getFilled('smtp_port', 587);
        $port = $port > 0 ? $port : 587;
        $encryptionRaw = Setting::getFilled(
            'smtp_encryption',
            config('mail.mailers.smtp.scheme')
        );
        $scheme = $this->resolveSmtpScheme(
            is_string($encryptionRaw) ? $encryptionRaw : null,
            $port
        );

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', Setting::getFilled('smtp_username', config('mail.mailers.smtp.username')));
        Config::set('mail.mailers.smtp.password', Setting::getFilled('smtp_password', config('mail.mailers.smtp.password')));
        Config::set('mail.mailers.smtp.scheme', $scheme);
        Config::set('mail.mailers.smtp.timeout', max(5, (int) config('mail.mailers.smtp.timeout', 15) ?: 15));
        Config::set('mail.from.address', Setting::getFilled('smtp_from_address', config('mail.from.address')));
        Config::set('mail.from.name', Setting::getFilled('smtp_from_name', config('mail.from.name')));

        $this->forgetResolvedMailers();
    }

    /**
     * Normalize mail.mailers.smtp.scheme when it still holds legacy encryption labels.
     */
    public function normalizeConfiguredSmtpScheme(): void
    {
        $current = config('mail.mailers.smtp.scheme');
        if (! is_string($current) || $current === '') {
            return;
        }

        $port = (int) config('mail.mailers.smtp.port', 587);
        $normalized = $this->resolveSmtpScheme($current, $port > 0 ? $port : null);
        if ($normalized !== $current) {
            Config::set('mail.mailers.smtp.scheme', $normalized);
            $this->forgetResolvedMailers();
        }
    }

    /**
     * Drop cached Symfony transports so queue workers pick up the new scheme/host.
     */
    protected function forgetResolvedMailers(): void
    {
        try {
            if (app()->bound('mail.manager')) {
                app('mail.manager')->forgetMailers();
            }
        } catch (\Throwable) {
            // Ignore during early boot / installer.
        }
    }

    public function queueTo(string $email, object $mailable): void
    {
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $this->applySmtpFromSettings();
        SendEmailJob::dispatch($email, $mailable);
    }

    public function sendNow(string $email, object $mailable): bool
    {
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $this->applySmtpFromSettings();
        Mail::to($email)->send($mailable);

        return true;
    }

    public function sendWelcome(User $user): void
    {
        if (! $user->email) {
            return;
        }
        $this->queueTo($user->email, new WelcomeMail($user->name));
    }

    public function sendSubscriptionExpiry(User $user, string $expiresAt): void
    {
        if (! $user->email) {
            return;
        }
        $this->queueTo($user->email, new SubscriptionExpiryMail($expiresAt, $user->name));
    }

    public function sendExamReminder(User $user, string $title, ?string $examDate = null, ?string $url = null): void
    {
        if (! $user->email) {
            return;
        }
        $this->queueTo($user->email, new ExamReminderMail($title, $user->name, $examDate, $url));
    }

    public function sendPaymentReceipt(User $user, Transaction $transaction): void
    {
        if (! $user->email || $transaction->status !== 'success') {
            return;
        }

        $this->queueTo($user->email, new PaymentReceiptMail(
            name: $user->name,
            amount: (int) $transaction->amount,
            invoiceNumber: (string) ($transaction->invoice_number ?: $transaction->id),
            description: (string) ($transaction->description ?: 'پرداخت موفق جاب‌آزمون'),
            paidAt: optional($transaction->updated_at)->toDateTimeString() ?? now()->toDateTimeString(),
            invoiceUrl: rtrim(config('app.url'), '/').'/wallet',
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendContactForm(array $data): void
    {
        $admin = Setting::getFilled('support_email')
            ?: Setting::getFilled('smtp_from_address')
            ?: config('mail.from.address');
        if (! $admin || ! filter_var((string) $admin, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $this->queueTo((string) $admin, new ContactFormMail(
            $data['name'],
            $data['email'],
            $data['subject'],
            $data['message'],
            $data['tracking_code'] ?? null,
            $data['mobile'] ?? null
        ));
    }
}
