<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class ContentSecurityPolicyTest extends TestCase
{
    public function test_web_responses_include_csp_and_omit_xss_protection(): void
    {
        $response = $this->get('/health');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('X-XSS-Protection'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_api_responses_include_csp(): void
    {
        $response = $this->getJson('/api/v1/exams');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertNotSame('', $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertFalse($response->headers->has('X-XSS-Protection'));
    }

    public function test_csp_report_endpoint_logs_summary_and_returns_no_content(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('csp')
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'CSP violation'
                    && isset($context['summary']['violated_directive'])
                    && $context['summary']['violated_directive'] === 'script-src';
            });

        $response = $this->postJson('/csp-report', [
            'csp-report' => [
                'document-uri' => 'https://example.test/page',
                'violated-directive' => 'script-src',
                'blocked-uri' => 'https://evil.example/x.js',
                'disposition' => 'enforce',
            ],
        ]);

        $response->assertNoContent();
    }

    public function test_csp_report_endpoint_ignores_invalid_json_body(): void
    {
        Log::shouldReceive('channel')->never();

        $response = $this->call(
            'POST',
            '/csp-report',
            content: 'not-json',
            server: ['CONTENT_TYPE' => 'application/csp-report']
        );

        $response->assertNoContent();
    }
}
