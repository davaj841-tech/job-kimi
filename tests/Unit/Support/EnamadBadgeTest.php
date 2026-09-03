<?php

namespace Tests\Unit\Support;

use App\Support\EnamadBadge;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnamadBadgeTest extends TestCase
{
    public function test_public_payload_disabled_when_not_configured(): void
    {
        config([
            'enamad.enabled' => false,
            'enamad.id' => '',
            'enamad.code' => '',
        ]);

        $payload = EnamadBadge::publicPayload();

        $this->assertFalse($payload['enamad_enabled']);
        $this->assertSame('', $payload['enamad_url']);
        $this->assertSame('', $payload['enamad_logo_url']);
    }

    public function test_public_payload_builds_official_https_urls(): void
    {
        config([
            'enamad.enabled' => true,
            'enamad.id' => '123456',
            'enamad.code' => 'AbCdEfGhIjKlMnOp',
        ]);

        $payload = EnamadBadge::publicPayload();

        $this->assertTrue($payload['enamad_enabled']);
        $this->assertSame('123456', $payload['enamad_id']);
        $this->assertSame(
            'https://trustseal.enamad.ir/?id=123456&Code=AbCdEfGhIjKlMnOp',
            $payload['enamad_url']
        );
        $this->assertSame(
            'https://trustseal.enamad.ir/logo.aspx?id=123456&Code=AbCdEfGhIjKlMnOp',
            $payload['enamad_logo_url']
        );
        $this->assertStringNotContainsString('localhost', $payload['enamad_url']);
    }

    public function test_parse_official_url_extracts_id_and_code(): void
    {
        $parsed = EnamadBadge::parseOfficialUrl(
            'https://trustseal.enamad.ir/?id=563817&Code=j3nOn6kIY0Kr0DWQZgvXh6Fv7sRHNfjB'
        );

        $this->assertSame([
            'id' => '563817',
            'code' => 'j3nOn6kIY0Kr0DWQZgvXh6Fv7sRHNfjB',
        ], $parsed);
    }

    #[DataProvider('invalidUrlProvider')]
    public function test_parse_rejects_invalid_urls(string $url): void
    {
        $this->assertNull(EnamadBadge::parseOfficialUrl($url));
    }

    /** @return list<array{0: string}> */
    public static function invalidUrlProvider(): array
    {
        return [
            ['http://trustseal.enamad.ir/?id=1&Code=abc12345'],
            ['https://example.com/?id=1&Code=abc12345'],
            ['https://trustseal.enamad.ir/?id=abc&Code='],
            [''],
        ];
    }

    public function test_sanitize_rejects_invalid_id_and_code(): void
    {
        $this->assertSame('', EnamadBadge::sanitizeId('abc'));
        $this->assertSame('', EnamadBadge::sanitizeCode('bad code!'));
    }
}
