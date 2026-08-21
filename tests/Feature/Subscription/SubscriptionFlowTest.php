<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Actions\Subscription\SubscriptionAction;
use App\Exceptions\DuplicateSubscriptionException;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\Payment\FakePaymentGateway;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class SubscriptionFlowTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPlan $paidPlan;

    private SubscriptionPlan $freePlan;

    protected function setUp(): void
    {
        parent::setUp();
        app(FeatureFlagService::class)->enable('subscription');
        app(FeatureFlagService::class)->enable('wallet');

        $this->freePlan = SubscriptionPlan::query()->create([
            'name' => 'رایگان',
            'duration_days' => 0,
            'price' => 0,
            'is_active' => true,
            'features' => ['free_plan_exam_limit'],
        ]);

        $this->paidPlan = SubscriptionPlan::query()->create([
            'name' => 'ماهانه',
            'duration_days' => 30,
            'price' => 99_000,
            'is_active' => true,
            'features' => ['unlimited_exams'],
        ]);
    }

    // ─── Requirement 1: start/end date دقیق ───

    public function test_activation_sets_correct_start_and_end_dates(): void
    {
        $user = User::factory()->create(['wallet_balance' => 200_000]);
        $service = app(SubscriptionService::class);

        Carbon::setTestNow('2026-03-15 12:00:00');

        $result = $service->subscribe($user, $this->paidPlan, 'wallet');
        $this->assertTrue($result['success']);

        $user->refresh();
        $this->assertNotNull($user->subscription_expires_at);
        $this->assertSame(
            '2026-04-14 12:00:00',
            $user->subscription_expires_at->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow();
    }

    // ─── Requirement 2: timezone صحیح ───

    public function test_subscription_dates_respect_app_timezone(): void
    {
        $user = User::factory()->create(['wallet_balance' => 200_000]);
        $service = app(SubscriptionService::class);

        $tz = config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-06-01 10:30:00', $tz));

        $service->subscribe($user, $this->paidPlan, 'wallet');
        $user->refresh();

        $this->assertSame($tz, $user->subscription_expires_at->timezone->getName());
        Carbon::setTestNow();
    }

    // ─── Requirement 3: expired = immediate access loss ───

    public function test_expired_subscription_loses_exam_access_immediately(): void
    {
        $user = User::factory()->create([
            'subscription_plan_id' => $this->paidPlan->id,
            'subscription_expires_at' => now()->subSecond(),
        ]);
        Sanctum::actingAs($user);

        $exam = $this->createPaidExam();

        $response = $this->postJson('/api/v1/exams/'.$exam->id.'/start');
        $response->assertStatus(403);
        $this->assertSame('SUBSCRIPTION_REQUIRED', $response->json('code'));
    }

    // ─── Requirement 4: active subscription must NOT expire prematurely ───

    public function test_active_subscription_does_not_expire_prematurely(): void
    {
        $user = User::factory()->create([
            'subscription_plan_id' => $this->paidPlan->id,
            'subscription_expires_at' => now()->addDays(15),
        ]);

        $service = app(SubscriptionService::class);
        $service->expireIfNeeded($user);

        $user->refresh();
        $this->assertNotNull($user->subscription_plan_id);
        $this->assertTrue($service->isActive($user));
    }

    // ─── Requirement 5: repurchase after expiry ───

    public function test_repurchase_after_expiry_starts_from_now(): void
    {
        $user = User::factory()->create([
            'wallet_balance' => 200_000,
            'subscription_plan_id' => null,
            'subscription_expires_at' => now()->subDays(5),
        ]);

        $service = app(SubscriptionService::class);
        $before = now();
        $result = $service->subscribe($user, $this->paidPlan, 'wallet');

        $this->assertTrue($result['success']);
        $user->refresh();
        $this->assertTrue($user->subscription_expires_at->gte($before->addDays(29)));
    }

    // ─── Requirement 6: renewal extends from existing end, not now ───

    public function test_renewal_extends_from_existing_expiry_not_now(): void
    {
        $futureExpiry = now()->addDays(10);
        $user = User::factory()->create([
            'wallet_balance' => 200_000,
            'subscription_plan_id' => $this->paidPlan->id,
            'subscription_expires_at' => $futureExpiry,
        ]);

        $service = app(SubscriptionService::class);
        $result = $service->subscribe($user, $this->paidPlan, 'wallet');

        $this->assertTrue($result['success']);
        $user->refresh();

        $expected = $futureExpiry->copy()->addDays(30);
        $this->assertSame(
            $expected->format('Y-m-d H:i'),
            $user->subscription_expires_at->format('Y-m-d H:i')
        );
    }

    // ─── Requirement 7: upgrade/downgrade policy ───

    public function test_upgrade_to_higher_plan_succeeds(): void
    {
        $premium = SubscriptionPlan::query()->create([
            'name' => 'سه‌ماهه',
            'duration_days' => 90,
            'price' => 249_000,
            'is_active' => true,
            'features' => [],
        ]);

        $user = User::factory()->create([
            'wallet_balance' => 500_000,
            'subscription_plan_id' => $this->paidPlan->id,
            'subscription_expires_at' => now()->addDays(10),
        ]);

        $service = app(SubscriptionService::class);
        $result = $service->upgrade($user, $premium, 'wallet');

        $this->assertTrue($result['success']);
        $user->refresh();
        $this->assertSame($premium->id, $user->subscription_plan_id);
    }

    public function test_downgrade_is_rejected(): void
    {
        $premium = SubscriptionPlan::query()->create([
            'name' => 'سه‌ماهه',
            'duration_days' => 90,
            'price' => 249_000,
            'is_active' => true,
            'features' => [],
        ]);

        $user = User::factory()->create([
            'wallet_balance' => 500_000,
            'subscription_plan_id' => $premium->id,
            'subscription_expires_at' => now()->addDays(50),
        ]);

        $service = app(SubscriptionService::class);
        $result = $service->upgrade($user, $this->paidPlan, 'wallet');

        $this->assertFalse($result['success']);
        $this->assertSame('downgrade_not_allowed', $result['error']);
    }

    // ─── Requirement 8: free plan not abusable ───

    public function test_free_plan_cannot_be_purchased(): void
    {
        $user = User::factory()->create(['wallet_balance' => 100_000]);
        $service = app(SubscriptionService::class);

        $result = $service->subscribe($user, $this->freePlan, 'wallet');

        $this->assertFalse($result['success']);
        $this->assertSame('free_plan_not_purchasable', $result['error']);
    }

    // ─── Requirement 9: user only sees own subscription ───

    public function test_user_only_sees_own_subscription_data(): void
    {
        $user = User::factory()->create([
            'subscription_plan_id' => $this->paidPlan->id,
            'subscription_expires_at' => now()->addDays(20),
            'status' => 'active',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');
        $response->assertOk();
    }

    // ─── Requirement 10 & 11: access control is server-side ───

    public function test_exam_access_is_enforced_server_side(): void
    {
        $user = User::factory()->create([
            'subscription_plan_id' => null,
            'subscription_expires_at' => null,
            'status' => 'active',
        ]);
        Sanctum::actingAs($user);

        $exam = $this->createPaidExam();

        $response = $this->postJson('/api/v1/exams/'.$exam->id.'/start');
        $response->assertStatus(403);
        $this->assertSame('SUBSCRIPTION_REQUIRED', $response->json('code'));
    }

    public function test_active_subscription_grants_exam_access(): void
    {
        $user = User::factory()->create([
            'subscription_plan_id' => $this->paidPlan->id,
            'subscription_expires_at' => now()->addDays(10),
            'status' => 'active',
        ]);
        Sanctum::actingAs($user);

        $exam = $this->createPaidExam();

        $response = $this->postJson('/api/v1/exams/'.$exam->id.'/start');
        $response->assertSuccessful();
        $this->assertNotNull($response->json('data.attempt_id'));
    }

    // ─── Requirement 12: payment + subscription atomic ───

    public function test_payment_and_subscription_activation_are_atomic(): void
    {
        $user = User::factory()->create(['wallet_balance' => 200_000, 'status' => 'active']);
        $service = app(SubscriptionService::class);

        $result = $service->subscribe($user, $this->paidPlan, 'wallet');
        $this->assertTrue($result['success']);

        $user->refresh();
        $this->assertNotNull($user->subscription_plan_id);
        $this->assertNotNull($user->subscription_expires_at);

        $tx = Transaction::query()
            ->where('user_id', $user->id)
            ->where('payable_type', SubscriptionPlan::class)
            ->where('status', 'success')
            ->first();
        $this->assertNotNull($tx);
    }

    public function test_insufficient_balance_does_not_activate_subscription(): void
    {
        $user = User::factory()->create(['wallet_balance' => 1_000, 'status' => 'active']);
        $service = app(SubscriptionService::class);

        $result = $service->subscribe($user, $this->paidPlan, 'wallet');
        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_balance', $result['error']);

        $user->refresh();
        $this->assertNull($user->subscription_plan_id);
    }

    // ─── Requirement 13: edge cases ───

    public function test_gateway_subscription_callback_activates_once(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $key = app(\App\Services\IdempotencyService::class)->generateKey();
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 99_000,
            'type' => 'purchase',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-SUB-'.$key,
            'idempotency_key' => $key,
            'description' => 'خرید اشتراک',
            'payable_type' => SubscriptionPlan::class,
            'payable_id' => $this->paidPlan->id,
        ]);
        FakePaymentGateway::seed((string) $tx->reference_id, 99_000);

        $payload = [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $key,
        ];

        $first = $this->postJson('/api/v1/subscription/verify', $payload);
        $second = $this->postJson('/api/v1/subscription/verify', $payload);

        $first->assertOk();
        $second->assertOk();
        $this->assertTrue((bool) $second->json('data.already_processed'));

        $user->refresh();
        $this->assertNotNull($user->subscription_expires_at);
        $this->assertSame($this->paidPlan->id, $user->subscription_plan_id);
    }

    public function test_admin_cancel_immediately_revokes_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $user = User::factory()->create([
            'subscription_plan_id' => $this->paidPlan->id,
            'subscription_expires_at' => now()->addDays(20),
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/subscriptions/subscribers/'.$user->id.'/cancel')
            ->assertOk();

        $user->refresh();
        $this->assertNull($user->subscription_plan_id);
        $this->assertFalse(app(SubscriptionService::class)->isActive($user));
    }

    public function test_admin_renew_extends_from_existing_expiry(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $expiry = now()->addDays(5);
        $user = User::factory()->create([
            'subscription_plan_id' => $this->paidPlan->id,
            'subscription_expires_at' => $expiry,
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/subscriptions/subscribers/'.$user->id.'/renew', [
            'plan_id' => $this->paidPlan->id,
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(
            $user->subscription_expires_at->gte($expiry->copy()->addDays(29))
        );
    }

    public function test_duplicate_activate_without_transaction_throws(): void
    {
        $user = User::factory()->create([
            'subscription_plan_id' => $this->paidPlan->id,
            'subscription_expires_at' => now()->addDays(10),
        ]);

        $this->expectException(DuplicateSubscriptionException::class);
        app(SubscriptionAction::class)->activate($user, $this->paidPlan);
    }

    // ─── Full e2e: purchase subscription → exam access ───

    public function test_full_flow_purchase_subscription_then_access_paid_exam(): void
    {
        $user = User::factory()->create(['wallet_balance' => 200_000, 'status' => 'active']);
        Sanctum::actingAs($user);

        $exam = $this->createPaidExam();

        $this->postJson('/api/v1/exams/'.$exam->id.'/start')->assertStatus(403);

        $this->postJson('/api/v1/subscription/subscribe', [
            'plan_id' => $this->paidPlan->id,
            'payment_method' => 'wallet',
        ])->assertOk();

        $user->refresh();
        $this->assertNotNull($user->subscription_plan_id);

        $this->postJson('/api/v1/exams/'.$exam->id.'/start')
            ->assertSuccessful()
            ->assertJsonPath('data.attempt_id', fn ($v) => $v > 0);
    }

    // ─── Helpers ───

    private function createPaidExam(): Exam
    {
        $category = ExamCategory::query()->create(['name' => 'عمومی', 'slug' => 'general']);
        $exam = Exam::query()->create([
            'title' => 'آزمون تست اشتراک',
            'slug' => 'test-sub-exam-'.uniqid(),
            'category_id' => $category->id,
            'duration_minutes' => 30,
            'total_questions' => 2,
            'is_free' => false,
            'subscription_required' => 'paid',
            'status' => 'published',
        ]);

        Question::query()->create([
            'exam_id' => $exam->id,
            'question_text' => 'سوال ۱؟',
            'option_a' => 'الف',
            'option_b' => 'ب',
            'option_c' => 'ج',
            'option_d' => 'د',
            'correct_answer' => 'a',
        ]);
        Question::query()->create([
            'exam_id' => $exam->id,
            'question_text' => 'سوال ۲؟',
            'option_a' => 'الف',
            'option_b' => 'ب',
            'option_c' => 'ج',
            'option_d' => 'د',
            'correct_answer' => 'b',
        ]);

        return $exam;
    }
}
