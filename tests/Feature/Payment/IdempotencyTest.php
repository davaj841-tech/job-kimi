<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\IdempotencyService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSuccessfulVerify(): void
    {
        Setting::set('zarinpal_merchant_id', 'test-merchant-id', 'payments');
        Setting::set('zarinpal_sandbox', 'true', 'payments');

        Http::fake([
            '*/pg/v4/payment/verify.json' => Http::response([
                'data' => [
                    'code' => 100,
                    'ref_id' => 998877,
                ],
            ], 200),
        ]);
    }

    /**
     * @return array{0: User, 1: Transaction}
     */
    private function pendingWalletDeposit(int $amount = 50000): array
    {
        $user = User::factory()->create([
            'wallet_balance' => 0,
            'role' => 'jobseeker',
            'status' => 'active',
        ]);

        $key = app(IdempotencyService::class)->generateKey();

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-WALLET-'.$key,
            'idempotency_key' => $key,
            'description' => 'شارژ کیف پول',
        ]);

        return [$user, $transaction];
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $this->fakeSuccessfulVerify();
        [$user, $transaction] = $this->pendingWalletDeposit(50000);

        $payload = [
            'Authority' => $transaction->reference_id,
            'Status' => 'OK',
            'ik' => $transaction->idempotency_key,
        ];

        $first = $this->postJson('/api/v1/wallet/verify', $payload);
        $second = $this->postJson('/api/v1/wallet/verify', $payload);

        $first->assertOk();
        $second->assertOk();

        $this->assertSame(50000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(1, Transaction::query()->where('user_id', $user->id)->where('status', Transaction::STATUS_COMPLETED)->count());
        $this->assertTrue((bool) $second->json('data.already_processed'));
    }

    public function test_concurrent_callbacks_are_safe(): void
    {
        [$user, $transaction] = $this->pendingWalletDeposit(25000);
        $wallet = app(WalletService::class);
        $idempotency = app(IdempotencyService::class);

        $run = function () use ($idempotency, $transaction, $wallet): void {
            $idempotency->completeOnce($transaction, function (Transaction $locked) use ($wallet) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('SELECT SLEEP(0.05)');
                }

                $wallet->deposit($locked->user, (int) $locked->amount, $locked);

                return true;
            });
        };

        $run();
        $run();

        $this->assertSame(25000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(Transaction::STATUS_COMPLETED, $transaction->fresh()->status);
    }

    public function test_different_keys_process_separately(): void
    {
        $this->fakeSuccessfulVerify();

        $user = User::factory()->create([
            'wallet_balance' => 0,
            'role' => 'jobseeker',
            'status' => 'active',
        ]);

        $idempotency = app(IdempotencyService::class);
        $keyA = $idempotency->generateKey();
        $keyB = $idempotency->generateKey();

        $txA = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 10000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-A-'.$keyA,
            'idempotency_key' => $keyA,
            'description' => 'A',
        ]);

        $txB = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 20000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-B-'.$keyB,
            'idempotency_key' => $keyB,
            'description' => 'B',
        ]);

        $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $txA->reference_id,
            'Status' => 'OK',
            'ik' => $keyA,
        ])->assertOk();

        $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $txB->reference_id,
            'Status' => 'OK',
            'ik' => $keyB,
        ])->assertOk();

        $this->assertSame(30000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(2, Transaction::query()->where('user_id', $user->id)->where('status', Transaction::STATUS_COMPLETED)->count());
    }

    public function test_failed_transaction_can_be_retried(): void
    {
        $this->fakeSuccessfulVerify();
        [$user, $transaction] = $this->pendingWalletDeposit(40000);

        $transaction->update(['status' => Transaction::STATUS_FAILED]);

        $response = $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $transaction->reference_id,
            'Status' => 'OK',
            'ik' => $transaction->idempotency_key,
        ]);

        $response->assertOk();
        $this->assertSame(40000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(Transaction::STATUS_COMPLETED, $transaction->fresh()->status);
    }

    public function test_subscription_verify_is_idempotent(): void
    {
        $this->fakeSuccessfulVerify();

        $plan = SubscriptionPlan::query()->create([
            'name' => 'ماهانه',
            'duration_days' => 30,
            'price' => 90000,
            'is_active' => true,
            'features' => [],
        ]);

        $user = User::factory()->create([
            'role' => 'jobseeker',
            'status' => 'active',
            'subscription_plan_id' => null,
            'subscription_expires_at' => null,
        ]);

        $key = app(IdempotencyService::class)->generateKey();

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 90000,
            'type' => 'purchase',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-SUB-'.$key,
            'idempotency_key' => $key,
            'description' => 'خرید اشتراک',
            'payable_type' => SubscriptionPlan::class,
            'payable_id' => $plan->id,
        ]);

        $payload = [
            'Authority' => $transaction->reference_id,
            'Status' => 'OK',
            'ik' => $key,
        ];

        $this->postJson('/api/v1/subscription/verify', $payload)->assertOk();
        $this->postJson('/api/v1/subscription/verify', $payload)->assertOk();

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->subscription_expires_at);
        $this->assertSame($plan->id, $fresh->subscription_plan_id);
        $this->assertSame(1, Transaction::query()->where('idempotency_key', $key)->where('status', Transaction::STATUS_COMPLETED)->count());
    }

    public function test_is_processed_and_mark_processed_roundtrip(): void
    {
        $service = app(IdempotencyService::class);
        $key = $service->generateKey();

        $user = User::factory()->create();
        Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 1000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => $key,
            'description' => 'test',
        ]);

        $this->assertFalse($service->isProcessed($key));
        $service->markProcessed($key, ['description' => 'done']);
        $this->assertTrue($service->isProcessed($key));
        $this->assertSame(Transaction::STATUS_COMPLETED, $service->getTransaction($key)?->status);
    }
}
