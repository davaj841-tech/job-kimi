<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Services\MailConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MailConfigServiceSchemeTest extends TestCase
{
    use RefreshDatabase;
    #[DataProvider('encryptionProvider')]
    public function test_resolve_smtp_scheme_mapping(
        ?string $encryption,
        ?int $port,
        ?string $expected
    ): void {
        $service = app(MailConfigService::class);
        $this->assertSame($expected, $service->resolveSmtpScheme($encryption, $port));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?int, 2: ?string}>
     */
    public static function encryptionProvider(): array
    {
        return [
            'tls_587' => ['tls', 587, 'smtp'],
            'starttls' => ['starttls', 587, 'smtp'],
            'ssl_465' => ['ssl', 465, 'smtps'],
            'smtps' => ['smtps', 465, 'smtps'],
            'smtp_literal' => ['smtp', 587, 'smtp'],
            'null' => ['null', 587, null],
            'none' => ['none', 587, null],
            'empty' => ['', 587, null],
            'unknown_defaults_smtp' => ['weird', 587, 'smtp'],
            'unknown_port_465' => ['weird', 465, 'smtps'],
        ];
    }

    public function test_tls_is_never_returned_as_scheme(): void
    {
        $service = app(MailConfigService::class);
        foreach (['tls', 'TLS', ' ssl ', 'null', 'none', ''] as $raw) {
            $scheme = $service->resolveSmtpScheme($raw, 587);
            $this->assertNotSame('tls', $scheme);
            $this->assertNotSame('ssl', $scheme);
            $this->assertTrue($scheme === null || in_array($scheme, ['smtp', 'smtps'], true));
        }
    }

    public function test_apply_smtp_from_settings_maps_tls_and_forgets_mailers(): void
    {
        Setting::set('smtp_host', 'mail.jobazmoon.ir', 'mail');
        Setting::set('smtp_port', '587', 'mail');
        Setting::set('smtp_encryption', 'tls', 'mail');
        Setting::set('smtp_username', 'Admin@jobazmoon.ir', 'mail');

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.scheme' => 'smtp']);
        app('mail.manager')->mailer('smtp');

        app(MailConfigService::class)->applySmtpFromSettings();

        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('mail.jobazmoon.ir', config('mail.mailers.smtp.host'));
        $this->assertSame(587, (int) config('mail.mailers.smtp.port'));

        $ref = new \ReflectionClass(app('mail.manager'));
        $prop = $ref->getProperty('mailers');
        $prop->setAccessible(true);
        $this->assertSame([], $prop->getValue(app('mail.manager')));
    }
}
