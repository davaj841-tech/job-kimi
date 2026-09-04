<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\IdempotencyException;
use App\Models\Transaction;
use App\Models\User;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class IdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private IdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(IdempotencyService::class);
        Cache::flush();
    }

    public function test_generate_key_returns_uuid_string(): void
    {
        $key = $this->service->generateKey();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $key
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function processedStateProvider(): array
    {
        return [
            'کلید تازه' => ['ik-fresh', Transaction::STATUS_PENDING, false],
            'کلید تکمیل‌شده' => ['ik-done', Transaction::STATUS_COMPLETED, true],
            'کلید ناموفق' => ['ik-fail', Transaction::STATUS_FAILED, false],
        ];
    }

    #[DataProvider('processedStateProvider')]
    public function test_is_processed_for_various_statuses(string $key, string $status, bool $expected): void
    {
        $user = User::factory()->create();
        Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 1000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => $status,
            'idempotency_key' => $key,
            'description' => 'test',
        ]);

        $this->assertSame($expected, $this->service->isProcessed($key));
    }

    public function test_ensure_unique_throws_409_for_duplicate_key(): void
    {
        $user = User::factory()->create();
        $key = 'ik-duplicate-409';

        Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 5000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_COMPLETED,
            'idempotency_key' => $key,
            'description' => 'done',
        ]);

        $this->service->ensureUnique('ik-brand-new');

        try {
            $this->service->ensureUnique($key);
            $this->fail('Expected IdempotencyException with 409');
        } catch (IdempotencyException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertStringContainsString('already processed', $e->getMessage());
        }
    }

    public function test_acquire_and_release_lock(): void
    {
        $key = 'ik-lock-1';

        $this->assertTrue($this->service->acquireLock($key, 10));
        $this->assertFalse($this->service->acquireLock($key, 10));
        $this->assertTrue($this->service->isLocked($key));

        $this->service->releaseLock($key);

        $this->assertFalse($this->service->isLocked($key));
        $this->assertTrue($this->service->acquireLock($key, 10));
        $this->service->releaseLock($key);
    }

    public function test_lock_expires_after_ttl(): void
    {
        $key = 'ik-lock-expire';

        $this->assertTrue($this->service->acquireLock($key, 1));
        $this->assertTrue($this->service->isLocked($key));

        $this->travel(2)->seconds();

        $this->assertFalse($this->service->isLocked($key));
        $this->assertTrue($this->service->acquireLock($key, 5));
        $this->service->releaseLock($key);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function completeOnceEligibilityProvider(): array
    {
        return [
            'pending قابل پردازش' => [Transaction::STATUS_PENDING, true],
            'cancelled غیرمجاز' => [Transaction::STATUS_CANCELLED, false],
            'expired قابل بازیابی پس از verify' => [Transaction::STATUS_EXPIRED, true],
            'failed قابل پردازش' => [Transaction::STATUS_FAILED, true],
        ];
    }

    #[DataProvider('completeOnceEligibilityProvider')]
    public function test_complete_once_respects_status(string $status, bool $shouldSucceed): void
    {
        $user = User::factory()->create();
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 3000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => $status,
            'idempotency_key' => 'ik-complete-'.$status,
            'description' => 'x',
        ]);

        if ($shouldSucceed) {
            $result = $this->service->completeOnce($tx, fn (Transaction $row) => 'ok');
            $this->assertFalse($result['already_processed']);
            $this->assertSame('ok', $result['result']);
            $this->assertSame(Transaction::STATUS_COMPLETED, $result['transaction']->status);
            $this->assertTrue($this->service->isProcessed((string) $tx->idempotency_key));

            return;
        }

        $this->expectException(IdempotencyException::class);
        $this->service->completeOnce($tx, fn () => null);
    }

    public function test_complete_once_is_idempotent_for_already_completed(): void
    {
        $user = User::factory()->create();
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 3000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_COMPLETED,
            'idempotency_key' => 'ik-already',
            'description' => 'x',
        ]);

        $calls = 0;
        $result = $this->service->completeOnce($tx, function () use (&$calls) {
            $calls++;

            return true;
        });

        $this->assertTrue($result['already_processed']);
        $this->assertSame(0, $calls);
    }

    public function test_extract_key_from_request(): void
    {
        $request = Request::create('/payment/wallet', 'POST', ['ik' => 'from-body']);
        $this->assertSame('from-body', $this->service->extractKey($request));

        $request = Request::create('/payment/wallet?idempotency_key=from-query', 'GET');
        $this->assertSame('from-query', $this->service->extractKey($request));
    }
}
