<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Mail\ContactFormMail;
use App\Mail\ExamReminderMail;
use App\Mail\SubscriptionExpiryMail;
use App\Mail\WelcomeMail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class MailConfigService
{
    /** اعمال تنظیمات SMTP از جدول settings روی config runtime */
    public function applySmtpFromSettings(): void
    {
        $host = Setting::get('smtp_host');
        if (! $host) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', (int) Setting::get('smtp_port', 587));
        Config::set('mail.mailers.smtp.username', Setting::get('smtp_username'));
        Config::set('mail.mailers.smtp.password', Setting::get('smtp_password'));
        Config::set('mail.from.address', Setting::get('smtp_from_address', config('mail.from.address')));
        Config::set('mail.from.name', Setting::get('smtp_from_name', config('mail.from.name')));
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendContactForm(array $data): void
    {
        $admin = Setting::get('support_email') ?: Setting::get('smtp_from_address') ?: config('mail.from.address');
        if (! $admin) {
            return;
        }
        $this->queueTo($admin, new ContactFormMail(
            $data['name'],
            $data['email'],
            $data['subject'],
            $data['message'],
            $data['tracking_code'] ?? null,
            $data['mobile'] ?? null
        ));
    }
}
