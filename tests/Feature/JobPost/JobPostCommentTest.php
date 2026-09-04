<?php

declare(strict_types=1);

namespace Tests\Feature\JobPost;

use App\Models\JobPost;
use App\Models\JobPostComment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class JobPostCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_lists_only_approved_comments(): void
    {
        $job = JobPost::factory()->approved()->create();
        $user = User::factory()->create();

        JobPostComment::query()->create([
            'job_post_id' => $job->id,
            'user_id' => $user->id,
            'content' => 'نظر تایید شده',
            'status' => 'approved',
        ]);
        JobPostComment::query()->create([
            'job_post_id' => $job->id,
            'user_id' => $user->id,
            'content' => 'نظر در انتظار',
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/v1/job-posts/{$job->id}/comments");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.data') ?? $response->json('data') ?? [];
        $contents = collect($items)->pluck('content')->all();

        $this->assertContains('نظر تایید شده', $contents);
        $this->assertNotContains('نظر در انتظار', $contents);
    }

    public function test_authenticated_user_submits_pending_comment_by_default(): void
    {
        Setting::set('job_comments_require_approval', 'true', 'general');
        $job = JobPost::factory()->approved()->create();
        $user = User::factory()->create(['role' => 'jobseeker']);

        $response = $this->actingAs($user)->postJson("/api/v1/job-posts/{$job->id}/comments", [
            'content' => 'این آگهی مفید بود',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('job_post_comments', [
            'job_post_id' => $job->id,
            'user_id' => $user->id,
            'content' => 'این آگهی مفید بود',
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_approve_and_reject_comments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $job = JobPost::factory()->approved()->create();
        $user = User::factory()->create();

        $comment = JobPostComment::query()->create([
            'job_post_id' => $job->id,
            'user_id' => $user->id,
            'content' => 'نظر برای تایید',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/job-post-comments/{$comment->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('job_post_comments', [
            'id' => $comment->id,
            'status' => 'approved',
        ]);

        $other = JobPostComment::query()->create([
            'job_post_id' => $job->id,
            'user_id' => $user->id,
            'content' => 'نظر برای رد',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/job-post-comments/{$other->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_cannot_comment_on_unapproved_job(): void
    {
        $job = JobPost::factory()->create(['status' => 'pending']);
        $user = User::factory()->create(['role' => 'jobseeker']);

        $this->actingAs($user)
            ->postJson("/api/v1/job-posts/{$job->id}/comments", [
                'content' => 'نباید ثبت شود',
            ])
            ->assertNotFound();
    }
}
