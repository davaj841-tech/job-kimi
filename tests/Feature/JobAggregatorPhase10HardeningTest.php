<?php

namespace Tests\Feature;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobEndpointType;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use App\Jobs\Aggregation\CrawlJobSourceJob;
use App\Models\CrawlerRun;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Models\User;
use App\Services\Aggregation\AggregationScheduleService;
use App\Services\Aggregation\CrawlOrchestrator;
use App\Services\Aggregation\JobNormalizer;
use App\Services\Aggregation\JobPublisher;
use App\Services\Aggregation\JobSourceDomainGuard;
use App\Services\Aggregation\SafeHttpFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 10 — production hardening: SSRF, auth, integrity, E2E workflow.
 */
class JobAggregatorPhase10HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    protected function actingOperator(): User
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['aggregation'],
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingJobseeker(): User
    {
        $user = User::factory()->create(['role' => 'jobseeker', 'status' => 'active']);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function makeSource(array $attrs = []): JobSource
    {
        $source = JobSource::factory()->whitelisted()->create(array_merge([
            'domain' => 'jobs.example.gov.ir',
            'official_url' => 'https://jobs.example.gov.ir/',
            'source_type' => JobSourceType::Government,
            'reliability_level' => JobSourceReliability::Official,
            'crawler_type' => JobCrawlerType::Json,
            'quality_status' => JobSourceQualityStatus::Active,
            'crawl_frequency' => 'hourly',
        ], $attrs));

        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
            'parser_type' => null,
            'is_enabled' => true,
        ]);

        return $source->fresh(['endpoints']);
    }

    // ── SSRF / URL safety ───────────────────────────────────────────────────

    public function test_fetcher_rejects_localhost_and_loopback_hosts(): void
    {
        $fetcher = app(SafeHttpFetcher::class);
        $source = $this->makeSource();

        foreach ([
            'http://localhost/admin',
            'http://127.0.0.1/secret',
            'http://0.0.0.0/x',
            'http://[::1]/',
        ] as $url) {
            try {
                $fetcher->assertUrlAllowed($url, $source);
                $this->fail("Expected blocked host for {$url}");
            } catch (\RuntimeException $e) {
                $this->assertTrue(
                    str_contains($e->getMessage(), 'Blocked')
                    || str_contains($e->getMessage(), 'outside source domain')
                    || str_contains($e->getMessage(), 'Only http'),
                    $e->getMessage()
                );
            }
        }
    }

    public function test_fetcher_rejects_private_ipv4_ranges(): void
    {
        $fetcher = app(SafeHttpFetcher::class);

        foreach (['10.0.0.1', '172.16.5.5', '192.168.1.10', '169.254.169.254'] as $ip) {
            $this->assertTrue($fetcher->isBlockedIp($ip), "Expected blocked IP {$ip}");
            $this->assertTrue($fetcher->isBlockedHost($ip));
        }
    }

    public function test_fetcher_allows_rfc1918_for_whitelisted_host_dns(): void
    {
        $fetcher = app(SafeHttpFetcher::class);

        $this->assertFalse($fetcher->isBlockedIp('10.56.4.11', allowPrivateOrgRanges: true));
        $this->assertFalse($fetcher->isBlockedIp('172.16.5.5', allowPrivateOrgRanges: true));
        $this->assertFalse($fetcher->isBlockedIp('192.168.1.10', allowPrivateOrgRanges: true));

        // Loopback / metadata still blocked even for whitelisted CDN DNS.
        $this->assertTrue($fetcher->isBlockedIp('127.0.0.1', allowPrivateOrgRanges: true));
        $this->assertTrue($fetcher->isBlockedIp('169.254.169.254', allowPrivateOrgRanges: true));
        $this->assertTrue($fetcher->isBlockedIp('::ffff:10.0.0.1', allowPrivateOrgRanges: false));
        $this->assertFalse($fetcher->isBlockedIp('::ffff:10.0.0.1', allowPrivateOrgRanges: true));
    }

    public function test_fetcher_rejects_private_ipv6_and_mapped_loopback(): void
    {
        $fetcher = app(SafeHttpFetcher::class);

        $this->assertTrue($fetcher->isBlockedIp('::1'));
        $this->assertTrue($fetcher->isBlockedIp('fc00::1'));
        $this->assertTrue($fetcher->isBlockedIp('fd12:3456::1'));
        $this->assertTrue($fetcher->isBlockedIp('fe80::1'));
        $this->assertTrue($fetcher->isBlockedIp('::ffff:127.0.0.1'));
        $this->assertTrue($fetcher->isBlockedIp('::ffff:10.0.0.1'));
    }

    public function test_fetcher_rejects_dangerous_schemes_and_credentials(): void
    {
        $fetcher = app(SafeHttpFetcher::class);
        $source = $this->makeSource();

        foreach ([
            'file:///etc/passwd',
            'ftp://jobs.example.gov.ir/x',
            'javascript:alert(1)',
            'data:text/html,hi',
            'https://user:pass@jobs.example.gov.ir/x',
        ] as $url) {
            try {
                $fetcher->assertUrlAllowed($url, $source);
                $this->fail("Expected rejection for {$url}");
            } catch (\RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_fetcher_rejects_cross_domain_and_private_redirects(): void
    {
        $source = $this->makeSource();

        Http::fake([
            'jobs.example.gov.ir/start' => Http::response('', 302, [
                'Location' => 'https://evil.example.com/steal',
            ]),
            'jobs.example.gov.ir/to-local' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/internal',
            ]),
            'jobs.example.gov.ir/ok' => Http::response('{"jobs":[]}', 200),
        ]);

        $fetcher = app(SafeHttpFetcher::class);

        try {
            $fetcher->get('https://jobs.example.gov.ir/start', $source);
            $this->fail('Expected cross-domain redirect rejection');
        } catch (\RuntimeException $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), 'outside source domain')
                || str_contains($e->getMessage(), 'not on the administrator allowlist')
                || str_contains($e->getMessage(), 'Blocked'),
                $e->getMessage()
            );
        }

        try {
            $fetcher->get('https://jobs.example.gov.ir/to-local', $source);
            $this->fail('Expected private redirect rejection');
        } catch (\RuntimeException $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), 'Blocked')
                || str_contains($e->getMessage(), 'outside source domain'),
                $e->getMessage()
            );
        }

        $ok = $fetcher->get('https://jobs.example.gov.ir/ok', $source);
        $this->assertSame(200, $ok->status());
    }

    public function test_domain_guard_rejects_blocked_hosts_on_endpoints(): void
    {
        $this->actingAdmin();
        $source = JobSource::factory()->whitelisted()->create([
            'domain' => 'jobs.example.gov.ir',
            'official_url' => 'https://jobs.example.gov.ir/',
        ]);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/endpoints", [
            'url' => 'http://127.0.0.1/feed',
            'endpoint_type' => JobEndpointType::Rss->value,
            'http_method' => 'GET',
            'is_enabled' => true,
        ])->assertStatus(422);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/endpoints", [
            'url' => 'file:///tmp/x',
            'endpoint_type' => JobEndpointType::Html->value,
            'http_method' => 'GET',
            'is_enabled' => true,
        ])->assertStatus(422);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/endpoints", [
            'url' => 'https://other.gov.ir/jobs',
            'endpoint_type' => JobEndpointType::Html->value,
            'http_method' => 'GET',
            'is_enabled' => true,
        ])->assertStatus(422);
    }

    // ── Admin authorization ─────────────────────────────────────────────────

    public function test_jobseeker_cannot_mutate_aggregation_resources(): void
    {
        $this->actingJobseeker();
        $source = $this->makeSource(['is_enabled' => false, 'is_approved' => false]);

        $this->getJson('/api/v1/admin/job-sources')->assertForbidden();
        $this->postJson('/api/v1/admin/job-sources', [
            'name' => 'X',
            'official_url' => 'https://jobs.example.gov.ir/',
            'domain' => 'jobs.example.gov.ir',
            'source_type' => JobSourceType::Government->value,
            'reliability_level' => JobSourceReliability::Official->value,
            'crawler_type' => JobCrawlerType::Json->value,
        ])->assertForbidden();

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/approve")->assertForbidden();
        $this->postJson("/api/v1/admin/job-sources/{$source->id}/enable")->assertForbidden();
        $this->postJson("/api/v1/admin/job-sources/{$source->id}/test-crawl")->assertForbidden();
        $this->postJson("/api/v1/admin/job-sources/{$source->id}/reset-health")->assertForbidden();

        $this->getJson('/api/v1/admin/aggregation-schedule')->assertForbidden();
        $this->putJson('/api/v1/admin/aggregation-schedule', ['enabled' => true])->assertForbidden();
        $this->postJson('/api/v1/admin/aggregation-schedule/dispatch-now')->assertForbidden();

        $this->postJson('/api/v1/admin/aggregation/jobs/1/approve')->assertForbidden();
        $this->postJson('/api/v1/admin/aggregation/jobs/1/reject')->assertForbidden();
    }

    public function test_guest_cannot_access_aggregation_admin_apis(): void
    {
        $this->getJson('/api/v1/admin/job-sources')->assertUnauthorized();
        $this->getJson('/api/v1/admin/aggregation-schedule')->assertUnauthorized();
        $this->postJson('/api/v1/admin/aggregation-schedule/dispatch-now')->assertUnauthorized();
    }

    public function test_operator_can_manage_sources_and_schedule(): void
    {
        $this->actingOperator();

        $this->getJson('/api/v1/admin/job-sources')->assertOk();
        $this->getJson('/api/v1/admin/aggregation-schedule')->assertOk();

        $create = $this->postJson('/api/v1/admin/job-sources', [
            'name' => 'منبع اپراتور',
            'official_url' => 'https://ops.example.gov.ir/',
            'domain' => 'ops.example.gov.ir',
            'source_type' => JobSourceType::Government->value,
            'reliability_level' => JobSourceReliability::Official->value,
            'crawler_type' => JobCrawlerType::Json->value,
            'is_enabled' => false,
            'is_approved' => false,
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->postJson("/api/v1/admin/job-sources/{$id}/approve")->assertOk();
        $this->postJson("/api/v1/admin/job-sources/{$id}/enable")->assertOk();
    }

    // ── Data integrity ──────────────────────────────────────────────────────

    public function test_normalizer_strips_html_from_external_content(): void
    {
        $normalized = app(JobNormalizer::class)->normalize([
            'title' => '<b>استخدام</b> <script>alert(1)</script>کارشناس',
            'company_name' => 'سازمان <img src=x onerror=alert(1)>نمونه',
            'description' => '<p>شرح</p><script>evil()</script>',
            'apply_url' => 'https://jobs.example.gov.ir/apply/1',
            'source_url' => 'https://jobs.example.gov.ir/j/1',
            'id' => 'XSS-1',
        ]);

        $this->assertStringNotContainsString('<script>', (string) $normalized['title']);
        $this->assertStringNotContainsString('<b>', (string) $normalized['title']);
        $this->assertStringNotContainsString('<script>', (string) $normalized['description']);
        $this->assertStringNotContainsString('<p>', (string) $normalized['description']);
        $this->assertStringContainsString('استخدام', (string) $normalized['title']);
        $this->assertStringContainsString('شرح', (string) $normalized['description']);
    }

    public function test_aggregation_does_not_overwrite_manual_job_posts(): void
    {
        $manual = JobPost::factory()->create([
            'title' => 'آگهی دستی محافظت‌شده',
            'company_name' => 'شرکت دستی',
            'description' => 'محتوای دستی',
            'registration_link' => 'https://jobs.example.gov.ir/apply/shared',
            'status' => 'approved',
            'job_source_id' => null,
            'external_id' => null,
        ]);

        Http::fake([
            'jobs.example.gov.ir/*' => Http::response([
                'jobs' => [[
                    'id' => 'AGG-SHARED',
                    'title' => 'آگهی تجمیعی',
                    'organization' => 'سازمان تجمیع',
                    'description' => 'نباید جایگزین دستی شود',
                    'apply_url' => 'https://jobs.example.gov.ir/apply/shared',
                    'source_url' => 'https://jobs.example.gov.ir/listing/agg',
                ]],
            ], 200),
        ]);

        $source = $this->makeSource();
        $result = app(CrawlOrchestrator::class)->crawlSource($source);

        $this->assertSame(0, $result['summary']['created']);
        $this->assertSame(0, $result['summary']['updated']);
        $this->assertSame(1, $result['summary']['duplicates']);

        $manual->refresh();
        $this->assertNull($manual->job_source_id);
        $this->assertSame('approved', $manual->status);
        $this->assertSame('آگهی دستی محافظت‌شده', $manual->title);
        $this->assertSame('محتوای دستی', $manual->description);
        $this->assertDatabaseMissing('job_posts', ['external_id' => 'AGG-SHARED']);
    }

    public function test_publisher_refuses_foreign_source_overwrite(): void
    {
        $sourceA = $this->makeSource(['domain' => 'a.example.gov.ir', 'official_url' => 'https://a.example.gov.ir/']);
        $sourceB = $this->makeSource(['domain' => 'b.example.gov.ir', 'official_url' => 'https://b.example.gov.ir/']);

        $post = JobPost::factory()->create([
            'job_source_id' => $sourceA->id,
            'external_id' => 'A-1',
            'title' => 'Owned by A',
            'status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);
        app(JobPublisher::class)->updateExisting($post, [
            'title' => 'Hijack',
            'company_name' => 'B',
        ], $sourceB);
    }

    // ── Queue / concurrent isolation ────────────────────────────────────────

    public function test_failed_source_does_not_block_other_queued_sources(): void
    {
        Queue::fake();

        $ok = $this->makeSource([
            'domain' => 'ok.example.gov.ir',
            'official_url' => 'https://ok.example.gov.ir/',
            'name' => 'OK Source',
        ]);
        $ok->endpoints()->delete();
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $ok->id,
            'url' => 'https://ok.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
            'is_enabled' => true,
        ]);

        $bad = $this->makeSource([
            'domain' => 'bad.example.gov.ir',
            'official_url' => 'https://bad.example.gov.ir/',
            'name' => 'Bad Source',
        ]);
        $bad->endpoints()->delete();
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $bad->id,
            'url' => 'https://bad.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
            'is_enabled' => true,
        ]);

        $this->artisan('jobs:aggregate-dispatch', ['--force' => true])->assertSuccessful();

        Queue::assertPushed(CrawlJobSourceJob::class, 2);

        Http::fake([
            'ok.example.gov.ir/*' => Http::response([
                'jobs' => [[
                    'id' => 'OK-1',
                    'title' => 'استخدام کارشناس موفق',
                    'organization' => 'سازمان موفق',
                    'description' => 'شرح',
                    'apply_url' => 'https://ok.example.gov.ir/apply/1',
                    'source_url' => 'https://ok.example.gov.ir/j/1',
                ]],
            ], 200),
            'bad.example.gov.ir/*' => Http::response('down', 500),
        ]);

        (new CrawlJobSourceJob($bad->id))->handle(app(CrawlOrchestrator::class));
        (new CrawlJobSourceJob($ok->id))->handle(app(CrawlOrchestrator::class));

        $this->assertDatabaseHas('job_posts', ['external_id' => 'OK-1', 'status' => 'pending']);
        $this->assertSame(
            CrawlerRunStatus::Failed,
            CrawlerRun::query()->where('job_source_id', $bad->id)->latest('id')->first()->status
        );
        $this->assertSame(
            CrawlerRunStatus::Completed,
            CrawlerRun::query()->where('job_source_id', $ok->id)->latest('id')->first()->status
        );
    }

    public function test_stuck_running_run_does_not_block_dispatch_forever(): void
    {
        Queue::fake();
        $source = $this->makeSource();

        CrawlerRun::query()->create([
            'job_source_id' => $source->id,
            'status' => CrawlerRunStatus::Running,
            'started_at' => now()->subHours(2),
            'meta' => ['stuck' => true],
        ]);

        $this->artisan('jobs:aggregate-dispatch', ['--force' => true])->assertSuccessful();

        Queue::assertPushed(CrawlJobSourceJob::class, fn (CrawlJobSourceJob $job) => $job->jobSourceId === $source->id);
    }

    public function test_schedule_clamps_dangerous_concurrency_and_retries(): void
    {
        $schedule = app(AggregationScheduleService::class);
        $config = $schedule->update([
            'enabled' => true,
            'timezone' => 'Asia/Tehran',
            'max_concurrent' => 999,
            'dispatch_delay_seconds' => 9999,
            'retry_tries' => 99,
            'times' => [['time' => '09:00', 'enabled' => true]],
        ]);

        $this->assertSame(20, $config['max_concurrent']);
        $this->assertSame(300, $config['dispatch_delay_seconds']);
        $this->assertSame(5, $config['retry_tries']);
    }

    public function test_legacy_crawl_jobs_schedule_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('aggregation.enable_legacy_crawl_jobs_schedule'));
    }

    // ── End-to-end workflow ─────────────────────────────────────────────────

    public function test_full_aggregation_workflow_to_admin_publish(): void
    {
        Queue::fake();
        $this->actingAdmin();

        app(AggregationScheduleService::class)->update([
            'enabled' => true,
            'timezone' => 'Asia/Tehran',
            'max_concurrent' => 3,
            'dispatch_delay_seconds' => 0,
            'retry_tries' => 2,
            'times' => [['time' => '08:00', 'enabled' => true]],
        ]);

        $source = $this->makeSource([
            'name' => 'منبع E2E',
            'quality_status' => JobSourceQualityStatus::Active,
        ]);

        $this->artisan('jobs:aggregate-dispatch', ['--force' => true])->assertSuccessful();
        Queue::assertPushed(CrawlJobSourceJob::class, 1);

        Http::fake([
            'jobs.example.gov.ir/*' => Http::response([
                'jobs' => [[
                    'id' => 'E2E-1',
                    'title' => 'استخدام کارشناس E2E',
                    'organization' => 'سازمان E2E',
                    'description' => '<b>شرح</b> امن',
                    'requirements' => 'کارشناسی',
                    'apply_url' => 'https://jobs.example.gov.ir/apply/e2e-1',
                    'source_url' => 'https://jobs.example.gov.ir/listing/e2e-1',
                    'deadline' => '2026-12-01',
                ]],
            ], 200),
        ]);

        (new CrawlJobSourceJob($source->id))->handle(app(CrawlOrchestrator::class));

        $post = JobPost::query()->where('external_id', 'E2E-1')->first();
        $this->assertNotNull($post);
        $this->assertSame('pending', $post->status);
        $this->assertSame($source->id, $post->job_source_id);
        $this->assertSame('https://jobs.example.gov.ir/apply/e2e-1', $post->registration_link);
        $this->assertSame('https://jobs.example.gov.ir/listing/e2e-1', $post->source_url);
        $this->assertStringNotContainsString('<b>', (string) $post->description);

        $this->getJson('/api/v1/admin/aggregation/pending-jobs')
            ->assertOk()
            ->assertJsonFragment(['external_id' => 'E2E-1']);

        $this->postJson("/api/v1/admin/aggregation/jobs/{$post->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', $post->fresh()->status);
    }

    public function test_domain_guard_sanitize_context_strips_secrets(): void
    {
        $clean = app(JobSourceDomainGuard::class)->sanitizeContext([
            'url' => 'https://jobs.example.gov.ir/x',
            'password' => 'secret',
            'api_token' => 'tok',
            'Authorization' => 'Bearer x',
            'cookie' => 'sid=1',
            'safe' => 'ok',
            'nested' => ['api_key' => 'k', 'code' => 1],
        ]);

        $this->assertSame('https://jobs.example.gov.ir/x', $clean['url']);
        $this->assertSame('ok', $clean['safe']);
        $this->assertArrayNotHasKey('password', $clean);
        $this->assertArrayNotHasKey('api_token', $clean);
        $this->assertArrayNotHasKey('Authorization', $clean);
        $this->assertArrayNotHasKey('cookie', $clean);
        $this->assertSame(['code' => 1], $clean['nested']);
    }
}
