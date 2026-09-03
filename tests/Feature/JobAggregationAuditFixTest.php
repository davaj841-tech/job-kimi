<?php

namespace Tests\Feature;

use App\Jobs\Aggregation\CrawlJobSourceJob;
use App\Models\Feature;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\User;
use App\Services\Aggregation\AggregationScheduleService;
use App\Services\FeatureFlagService;
use App\Services\SiteAutoHealService;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobAggregationAuditFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_seeder_enables_job_crawler_by_default(): void
    {
        $this->seed(FeatureSeeder::class);

        $this->assertTrue(app(FeatureFlagService::class)->isEnabled('job-crawler'));
        $this->assertDatabaseHas('features', [
            'name' => 'job-crawler',
            'enabled' => true,
        ]);
    }

    public function test_feature_seeder_does_not_re_disable_existing_flag(): void
    {
        Feature::query()->create([
            'name' => 'job-crawler',
            'enabled' => true,
            'description' => 'x',
        ]);

        // Simulate old seeder intent would have been false — firstOrCreate must keep true.
        $this->seed(FeatureSeeder::class);

        $this->assertTrue((bool) Feature::query()->where('name', 'job-crawler')->value('enabled'));
    }

    public function test_bootstrap_enables_flag_and_schedule_without_full_catalog(): void
    {
        Feature::query()->create([
            'name' => 'job-crawler',
            'enabled' => false,
            'description' => 'خزشگر',
        ]);
        app(FeatureFlagService::class)->forgetCache();

        // Avoid seeding the full official catalog (hundreds of rows) in CI.
        JobSource::factory()->whitelisted()->create(['domain' => 'boot.example.gov.ir']);

        $exit = Artisan::call('jobs:bootstrap-aggregation', [
            '--enable-schedule' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertTrue(app(FeatureFlagService::class)->isEnabled('job-crawler'));
        $this->assertTrue(app(AggregationScheduleService::class)->isEnabled());
        $this->assertNotEmpty(app(AggregationScheduleService::class)->enabledGlobalTimes());
    }

    public function test_aggregate_dispatch_skips_when_feature_flag_disabled(): void
    {
        Queue::fake();
        JobSource::factory()->whitelisted()->create(['domain' => 'skip.example.gov.ir']);

        Feature::query()->create([
            'name' => 'job-crawler',
            'enabled' => false,
            'description' => 'خزشگر',
        ]);
        app(FeatureFlagService::class)->forgetCache();

        Artisan::call('jobs:aggregate-dispatch', ['--force' => true]);

        Queue::assertNothingPushed();
        $this->assertStringContainsString('job-crawler', Artisan::output());
    }

    public function test_aggregate_dispatch_force_queues_dispatchable_source(): void
    {
        Queue::fake();
        app(FeatureFlagService::class)->enable('job-crawler');

        $source = JobSource::factory()->whitelisted()->create([
            'domain' => 'force.example.gov.ir',
            'crawl_frequency' => 'hourly',
            'last_crawled_at' => null,
        ]);

        Artisan::call('jobs:aggregate-dispatch', ['--force' => true]);

        Queue::assertPushed(CrawlJobSourceJob::class, function (CrawlJobSourceJob $job) use ($source) {
            return (int) $job->jobSourceId === (int) $source->id;
        });
    }

    public function test_public_job_posts_api_lists_approved_only(): void
    {
        JobPost::factory()->create(['status' => 'approved', 'title' => 'آگهی تایید شده']);
        JobPost::factory()->create(['status' => 'pending', 'title' => 'آگهی در انتظار']);

        $response = $this->getJson('/api/v1/job-posts')->assertOk();
        $titles = collect($response->json('data.data'))->pluck('title')->all();
        $this->assertContains('آگهی تایید شده', $titles);
        $this->assertNotContains('آگهی در انتظار', $titles);
    }

    public function test_approving_job_clears_public_list_response_cache(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $post = JobPost::factory()->create(['status' => 'pending', 'title' => 'جدید']);

        Cache::put('response:'.md5('/api/v1/job-posts'), [
            'success' => true,
            'data' => [],
        ], 120);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/job-posts/'.$post->id.'/approve')->assertOk();

        $this->assertFalse(Cache::has('response:'.md5('/api/v1/job-posts')));

        $response = $this->getJson('/api/v1/job-posts')->assertOk();
        $titles = collect($response->json('data.data'))->pluck('title')->all();
        $this->assertContains('جدید', $titles);
    }

    public function test_site_auto_heal_re_enables_job_crawler_when_sources_exist(): void
    {
        JobSource::factory()->whitelisted()->create(['domain' => 'heal.example.gov.ir']);
        Feature::query()->create([
            'name' => 'job-crawler',
            'enabled' => false,
            'description' => 'خزشگر',
        ]);
        app(FeatureFlagService::class)->forgetCache();

        $stats = app(SiteAutoHealService::class)->run();

        $this->assertSame(1, $stats['aggregation_flag_healed']);
        $this->assertTrue(app(FeatureFlagService::class)->isEnabled('job-crawler'));
    }
}
