<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Helpers\IpHelper;
use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class TrustProxiesTest extends TestCase
{
    private const PROBE_URI = '/api/__trust-proxies-probe';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.trusted_proxies' => '173.245.48.0/20,162.158.0.0/15',
            'app.trusted_proxies_v6' => '2400:cb00::/32,2606:4700::/32',
        ]);

        // Path must stay outside the SPA catch-all (web + ForceHttps).
        Route::middleware(TrustProxies::class)->get(self::PROBE_URI, function (Request $request) {
            return response()->json([
                'ip' => $request->trustedIp(),
                'xff' => $request->headers->get('X-Forwarded-For'),
                'cf' => $request->headers->get('CF-Connecting-IP'),
            ]);
        });
    }

    /**
     * @param  array<string, string>  $server
     */
    private function probe(array $server): TestResponse
    {
        $this->app->instance('env', 'production');

        return $this->call('GET', self::PROBE_URI, server: $server);
    }

    public function test_trusted_proxy_allows_forwarded_client_ip(): void
    {
        $response = $this->probe([
            'REMOTE_ADDR' => '162.158.10.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.77',
        ]);

        $response->assertOk()
            ->assertJsonPath('ip', '203.0.113.77')
            ->assertJsonPath('xff', '203.0.113.77');
    }

    public function test_untrusted_proxy_strips_forwarded_headers_in_production(): void
    {
        $response = $this->probe([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
            'HTTP_CF_CONNECTING_IP' => '5.6.7.8',
        ]);

        $response->assertOk()
            ->assertJsonPath('ip', '203.0.113.10')
            ->assertJsonPath('xff', null)
            ->assertJsonPath('cf', null);
    }

    public function test_ip_spoofing_via_xff_from_untrusted_source_is_ignored(): void
    {
        $response = $this->probe([
            'REMOTE_ADDR' => '198.51.100.20',
            'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 1.1.1.1',
        ]);

        $response->assertOk()->assertJsonPath('ip', '198.51.100.20');
    }

    public function test_empty_trusted_proxies_treats_all_peers_as_untrusted(): void
    {
        config([
            'app.trusted_proxies' => '',
            'app.trusted_proxies_v6' => '',
        ]);

        $this->assertFalse(app(IpHelper::class)->isTrustedProxy('162.158.10.5'));

        $response = $this->probe([
            'REMOTE_ADDR' => '162.158.10.5',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9',
        ]);

        $response->assertOk()
            ->assertJsonPath('ip', '162.158.10.5')
            ->assertJsonPath('xff', null);
    }

    public function test_ipv6_trusted_proxy_resolves_client(): void
    {
        $response = $this->probe([
            'REMOTE_ADDR' => '2606:4700:4700::1001',
            'HTTP_X_FORWARDED_FOR' => '2001:db8::abcd',
        ]);

        $response->assertOk()->assertJsonPath('ip', '2001:db8::abcd');
    }

    public function test_request_trusted_ip_macro_works(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '162.158.10.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ]);

        $this->assertSame('203.0.113.99', $request->trustedIp());
    }
}
