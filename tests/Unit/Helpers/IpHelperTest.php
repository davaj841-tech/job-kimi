<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\IpHelper;
use Illuminate\Http\Request;
use Tests\TestCase;

final class IpHelperTest extends TestCase
{
    private IpHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.trusted_proxies' => '173.245.48.0/20,162.158.0.0/15',
            'app.trusted_proxies_v6' => '2400:cb00::/32,2606:4700::/32',
        ]);

        $this->helper = new IpHelper;
    }

    public function test_is_trusted_proxy_matches_cloudflare_cidr(): void
    {
        $this->assertTrue($this->helper->isTrustedProxy('162.158.10.5'));
        $this->assertTrue($this->helper->isTrustedProxy('173.245.48.10'));
        $this->assertFalse($this->helper->isTrustedProxy('8.8.8.8'));
        $this->assertFalse($this->helper->isTrustedProxy('not-an-ip'));
    }

    public function test_get_client_ip_returns_remote_when_proxy_untrusted(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ]);

        $this->assertSame('203.0.113.10', $this->helper->getClientIp($request));
    }

    public function test_get_client_ip_walks_forwarded_chain_from_trusted_proxy(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '162.158.10.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50, 162.158.10.5',
        ]);

        $this->assertSame('203.0.113.50', $this->helper->getClientIp($request));
    }

    public function test_get_client_ip_supports_ipv6_trusted_ranges(): void
    {
        $this->assertTrue($this->helper->isTrustedProxy('2606:4700:4700::1111'));

        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '2606:4700:4700::1111',
            'HTTP_X_FORWARDED_FOR' => '2001:db8::1',
        ]);

        $this->assertSame('2001:db8::1', $this->helper->getClientIp($request));
    }
}
