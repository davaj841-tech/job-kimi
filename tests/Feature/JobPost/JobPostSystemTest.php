<?php

declare(strict_types=1);

namespace Tests\Feature\JobPost;

use App\Models\JobClassification;
use App\Models\JobPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class JobPostSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private JobClassification $classification;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'jobseeker']);
        $this->classification = JobClassification::firstOrCreate(
            ['name' => 'بانک‌ها'],
            ['is_active' => true, 'sort_order' => 1]
        );
    }

    // ─── Requirement 1: draft/published/expired/rejected statuses ───

    public function test_admin_can_create_draft_job(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/job-posts', [
            'title' => 'استخدام نمونه',
            'job_classification_id' => $this->classification->id,
            'description' => 'توضیحات',
            'registration_deadline' => now()->addMonth()->toDateString(),
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('job_posts', ['title' => 'استخدام نمونه', 'status' => 'draft']);
    }

    public function test_expired_status_set_by_command(): void
    {
        $job = JobPost::create([
            'title' => 'آگهی قدیمی',
            'company_name' => 'شرکت',
            'job_classification_id' => $this->classification->id,
            'description' => 'متن',
            'status' => 'approved',
            'registration_deadline' => now()->subDay(),
            'created_by' => $this->admin->id,
        ]);

        $this->artisan('jobs:expire')->assertSuccessful();

        $job->refresh();
        $this->assertEquals('expired', $job->status);
    }

    // ─── Requirement 2: Only published/approved shown publicly ───

    public function test_only_approved_jobs_shown_publicly(): void
    {
        JobPost::create([
            'title' => 'Pending Job',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);
        JobPost::create([
            'title' => 'Approved Job',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'approved',
            'registration_deadline' => now()->addMonth(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/job-posts');
        $response->assertOk();

        $titles = collect($response->json('data.data'))->pluck('title');
        $this->assertContains('Approved Job', $titles);
        $this->assertNotContains('Pending Job', $titles);
    }

    // ─── Requirement 3: Auto-expiration ───

    public function test_expired_job_not_shown_publicly(): void
    {
        JobPost::create([
            'title' => 'Expired Job',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'expired',
            'registration_deadline' => now()->subDay(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/job-posts');
        $titles = collect($response->json('data.data'))->pluck('title');
        $this->assertNotContains('Expired Job', $titles);
    }

    // ─── Requirement 4: Duplicate control ───

    public function test_user_cannot_submit_duplicate_title(): void
    {
        JobPost::create([
            'title' => 'آگهی تکراری',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/job-posts/submit', [
            'title' => 'آگهی تکراری',
            'job_classification_id' => $this->classification->id,
            'description' => 'توضیحات',
            'registration_deadline' => now()->addMonth()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    // ─── Requirement 5: Classification relation ───

    public function test_job_has_classification_relation(): void
    {
        $job = JobPost::create([
            'title' => 'Job With Class',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'approved',
            'registration_deadline' => now()->addMonth(),
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals($this->classification->id, $job->classification->id);
    }

    // ─── Requirement 6: Persian search ───

    public function test_persian_search_works(): void
    {
        JobPost::create([
            'title' => 'استخدام بانک ملت',
            'company_name' => 'بانک ملت',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'approved',
            'registration_deadline' => now()->addMonth(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/job-posts?search=بانک+ملت');
        $titles = collect($response->json('data.data'))->pluck('title');
        $this->assertContains('استخدام بانک ملت', $titles);
    }

    // ─── Requirement 7: Filters ───

    public function test_province_filter(): void
    {
        JobPost::create([
            'title' => 'تهران Job',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'approved',
            'province' => 'تهران',
            'provinces' => ['تهران'],
            'registration_deadline' => now()->addMonth(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/job-posts?province=تهران');
        $this->assertNotEmpty($response->json('data.data'));
    }

    public function test_employment_type_filter(): void
    {
        JobPost::create([
            'title' => 'Remote Job',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'approved',
            'employment_type' => 'remote',
            'registration_deadline' => now()->addMonth(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/job-posts?employment_type=remote');
        $titles = collect($response->json('data.data'))->pluck('title');
        $this->assertContains('Remote Job', $titles);
    }

    // ─── Requirement 8: Secure pagination ───

    public function test_pagination_clamped(): void
    {
        $response = $this->getJson('/api/v1/job-posts?per_page=999');
        $response->assertOk();
        $perPage = $response->json('data.meta.per_page');
        $this->assertLessThanOrEqual(50, $perPage);
    }

    // ─── Requirement 9: Unique slug (seo_tag) ───

    public function test_seo_tag_unique_enforced(): void
    {
        JobPost::create([
            'title' => 'Job A',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'approved',
            'seo_tag' => 'unique_tag',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/job-posts', [
            'title' => 'Job B',
            'job_classification_id' => $this->classification->id,
            'description' => 'y',
            'registration_deadline' => now()->addMonth()->toDateString(),
            'seo_tag' => 'unique_tag',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('seo_tag');
    }

    // ─── Requirement 10: SEO schema ───

    public function test_job_detail_includes_schema(): void
    {
        $job = JobPost::create([
            'title' => 'SEO Job',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'approved',
            'registration_deadline' => now()->addMonth(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/job-posts/{$job->id}");
        $response->assertOk();
        $response->assertJsonPath('data.schema.@type', 'JobPosting');
    }

    // ─── Requirement 11: Spam prevention (rate limit) ───

    public function test_user_rate_limited_at_3_per_day(): void
    {
        for ($i = 0; $i < 3; $i++) {
            JobPost::create([
                'title' => "Job {$i}",
                'company_name' => 'C',
                'job_classification_id' => $this->classification->id,
                'description' => 'x',
                'status' => 'pending',
                'created_by' => $this->user->id,
                'created_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->user)->postJson('/api/v1/job-posts/submit', [
            'title' => 'New Job',
            'job_classification_id' => $this->classification->id,
            'description' => 'y',
            'registration_deadline' => now()->addMonth()->toDateString(),
        ]);

        $response->assertStatus(429);
    }

    // ─── Requirement 12: Admin moderation ───

    public function test_admin_can_approve_job(): void
    {
        $job = JobPost::create([
            'title' => 'Pending',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/job-posts/{$job->id}/approve");
        $response->assertOk();

        $job->refresh();
        $this->assertEquals('approved', $job->status);
    }

    public function test_admin_can_reject_job(): void
    {
        $job = JobPost::create([
            'title' => 'To Reject',
            'company_name' => 'C',
            'job_classification_id' => $this->classification->id,
            'description' => 'x',
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/job-posts/{$job->id}/reject", [
            'reason' => 'نامعتبر',
        ]);
        $response->assertOk();

        $job->refresh();
        $this->assertEquals('rejected', $job->status);
    }
}
