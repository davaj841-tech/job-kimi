<?php

namespace Tests\Feature\Mail;

use App\Events\PaymentSuccessful;
use App\Events\UserRegistered;
use App\Jobs\SendEmailJob;
use App\Jobs\SendSubscriptionExpiryNotification;
use App\Mail\PasswordResetMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\SubscriptionExpiryMail;
use App\Mail\WelcomeMail;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MailConfigService;
use App\Services\SMSService;
use App\Support\EmailMask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_email_is_queued_on_registration(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'welcome@example.com']);
        event(new UserRegistered($user));

        Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail) {
            return $mail->hasTo('welcome@example.com');
        });
    }

    public function test_forgot_password_sends_reset_mail_without_enumeration(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'reset@example.com']);

        $known = $this->postJson('/api/v1/auth/forgot-password', $this->withAuthCaptcha([
            'identifier' => 'reset@example.com',
        ]));
        $unknown = $this->postJson('/api/v1/auth/forgot-password', $this->withAuthCaptcha([
            'identifier' => 'nobody@example.com',
        ]));

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));

        Mail::assertSent(PasswordResetMail::class, 1);
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) {
            return $mail->hasTo('reset@example.com')
                && str_contains($mail->resetUrl, 'token=')
                && str_contains($mail->resetUrl, 'email=');
        });
    }

    public function test_password_reset_rejects_invalid_and_expired_tokens(): void
    {
        $user = User::factory()->create(['email' => 'token@example.com', 'password' => 'OldPass123!']);
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now()->subHours(2),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertStatus(422);

        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'wrong-token',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertStatus(422);
    }

    public function test_password_reset_success_revokes_token_and_sanctum_tokens(): void
    {
        $user = User::factory()->create(['email' => 'ok@example.com', 'password' => 'OldPass123!']);
        $user->createToken('api');
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertOk();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertSame(0, $user->fresh()->tokens()->count());
        $this->assertTrue(Hash::check('NewPass123!', $user->fresh()->password));
    }

    public function test_payment_receipt_sent_once_on_success_only(): void
    {
        Mail::fake();
        Cache::flush();

        $user = User::factory()->create(['email' => 'pay@example.com']);
        $tx = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => 'success',
            'amount' => 150000,
            'invoice_number' => 'IR-TEST-0001',
            'invoice_pdf' => 'invoices/IR-TEST-0001.pdf',
            'description' => 'خرید اشتراک',
        ]);

        event(new PaymentSuccessful($tx));
        event(new PaymentSuccessful($tx));

        Mail::assertSent(PaymentReceiptMail::class, 1);
        Mail::assertSent(PaymentReceiptMail::class, function (PaymentReceiptMail $mail) {
            return $mail->hasTo('pay@example.com')
                && $mail->invoiceNumber === 'IR-TEST-0001'
                && $mail->amount === 150000;
        });

        $failed = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => 'failed',
            'amount' => 1000,
        ]);
        event(new PaymentSuccessful($failed));
        Mail::assertSent(PaymentReceiptMail::class, 1);
    }

    public function test_subscription_expiry_job_is_idempotent_per_day(): void
    {
        Mail::fake();
        Cache::flush();
        Http::fake([
            '*' => Http::response(['RetStatus' => 1], 200),
        ]);

        User::factory()->create([
            'email' => 'sub@example.com',
            'subscription_expires_at' => now()->addDay(),
        ]);

        (new SendSubscriptionExpiryNotification)->handle(
            app(SMSService::class),
            app(MailConfigService::class)
        );
        (new SendSubscriptionExpiryNotification)->handle(
            app(SMSService::class),
            app(MailConfigService::class)
        );

        Mail::assertSent(SubscriptionExpiryMail::class, 1);
    }

    public function test_send_email_job_applies_smtp_from_settings(): void
    {
        Mail::fake();
        Setting::set('smtp_host', 'smtp.jobazmoon.test', 'mail');
        Setting::set('smtp_port', '465', 'mail');
        Setting::set('smtp_encryption', 'ssl', 'mail');
        Setting::set('smtp_from_address', 'noreply@jobazmoon.ir', 'mail');
        Setting::set('smtp_from_name', 'جاب‌آزمون', 'mail');

        $job = new SendEmailJob('worker@example.com', new WelcomeMail('Worker'));
        $job->handle(app(MailConfigService::class));

        $this->assertSame('smtp.jobazmoon.test', Config::get('mail.mailers.smtp.host'));
        $this->assertSame(465, (int) Config::get('mail.mailers.smtp.port'));
        $this->assertSame('smtps', Config::get('mail.mailers.smtp.scheme'));
        Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail) {
            return $mail->hasTo('worker@example.com');
        });
    }

    public function test_smtp_encryption_tls_maps_to_smtp_scheme_not_tls(): void
    {
        Setting::set('smtp_host', 'smtp.example.com', 'mail');
        Setting::set('smtp_port', '587', 'mail');
        Setting::set('smtp_encryption', 'tls', 'mail');

        $mail = app(MailConfigService::class);
        $mail->applySmtpFromSettings();

        $scheme = Config::get('mail.mailers.smtp.scheme');
        $this->assertSame('smtp', $scheme);
        $this->assertNotSame('tls', $scheme);
        $this->assertContains($scheme, ['smtp', 'smtps', null]);
    }

    public function test_legacy_env_scheme_tls_is_normalized(): void
    {
        Config::set('mail.mailers.smtp.scheme', 'tls');
        Config::set('mail.mailers.smtp.port', 587);

        app(MailConfigService::class)->normalizeConfiguredSmtpScheme();

        $this->assertSame('smtp', Config::get('mail.mailers.smtp.scheme'));
    }

    public function test_apply_smtp_purges_cached_mailer_after_scheme_fix(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.scheme', 'smtp');
        Config::set('mail.mailers.smtp.host', '127.0.0.1');
        Config::set('mail.mailers.smtp.port', 587);

        app('mail.manager')->mailer('smtp');

        Setting::set('smtp_host', 'mail.jobazmoon.ir', 'mail');
        Setting::set('smtp_port', '587', 'mail');
        Setting::set('smtp_encryption', 'tls', 'mail');
        Setting::set('smtp_username', 'Admin@jobazmoon.ir', 'mail');

        app(MailConfigService::class)->applySmtpFromSettings();

        $ref = new \ReflectionClass(app('mail.manager'));
        $prop = $ref->getProperty('mailers');
        $prop->setAccessible(true);
        $this->assertSame([], $prop->getValue(app('mail.manager')));

        $this->assertSame('smtp', Config::get('mail.mailers.smtp.scheme'));
        $this->assertSame('mail.jobazmoon.ir', Config::get('mail.mailers.smtp.host'));
        $this->assertNotSame('tls', Config::get('mail.mailers.smtp.scheme'));

        $mailer = app('mail.manager')->mailer('smtp');
        $this->assertNotNull($mailer);
    }

    public function test_forgot_password_survives_smtp_scheme_misconfiguration(): void
    {
        Mail::fake();

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.scheme', 'tls');
        Config::set('mail.mailers.smtp.host', 'smtp.example.com');
        Config::set('mail.mailers.smtp.port', 587);

        Setting::set('smtp_host', 'smtp.example.com', 'mail');
        Setting::set('smtp_port', '587', 'mail');
        Setting::set('smtp_encryption', 'tls', 'mail');

        User::factory()->create(['email' => 'reset-ok@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', $this->withAuthCaptcha([
            'identifier' => 'reset-ok@example.com',
        ]));

        $response->assertOk();
        $this->assertSame('smtp', Config::get('mail.mailers.smtp.scheme'));
        Mail::assertSent(PasswordResetMail::class, 1);
    }

    public function test_forgot_password_invalid_smtp_host_does_not_leak_exception(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('queue.default', 'sync');
        Config::set('mail.mailers.smtp.timeout', 1);
        Setting::set('smtp_host', '127.0.0.1', 'mail');
        Setting::set('smtp_port', '1', 'mail');
        Setting::set('smtp_encryption', 'none', 'mail');
        Setting::set('smtp_username', 'x', 'mail');
        Setting::set('smtp_password', 'y', 'mail');

        User::factory()->create(['email' => 'fail-mail@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', $this->withAuthCaptcha([
            'identifier' => 'fail-mail@example.com',
        ]));

        $response->assertOk();
        $response->assertJsonMissingPath('exception');
        $this->assertStringNotContainsString('UnsupportedSchemeException', (string) $response->getContent());
        $this->assertStringNotContainsString('smtp_password', (string) $response->getContent());
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'fail-mail@example.com']);
    }

    public function test_forgot_password_rejects_missing_captcha(): void
    {
        User::factory()->create(['email' => 'cap@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => 'cap@example.com',
        ])->assertStatus(422);
    }

    public function test_email_mask_hides_local_part(): void
    {
        $this->assertSame('ab***@jobazmoon.ir', EmailMask::mask('abc@jobazmoon.ir'));
        $this->assertSame('***', EmailMask::mask('not-an-email'));
    }

    public function test_mail_test_command_disabled_in_testing(): void
    {
        $this->artisan('mail:test', ['email' => 'a@b.com'])->assertFailed();
    }

    public function test_queue_dispatch_path_uses_send_email_job(): void
    {
        Queue::fake();

        app(MailConfigService::class)->queueTo('q@example.com', new WelcomeMail('Q'));

        Queue::assertPushed(SendEmailJob::class, function (SendEmailJob $job) {
            return $job->recipient === 'q@example.com'
                && $job->mailable instanceof WelcomeMail;
        });
    }
}
