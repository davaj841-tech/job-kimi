<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = app(WalletService::class);
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: int}>
     */
    public static function depositProvider(): array
    {
        return [
            'شارژ کوچک' => [0, 10_000, 10_000],
            'شارژ روی موجودی قبلی' => [5_000, 15_000, 20_000],
            'شارژ بزرگ' => [0, 500_000, 500_000],
        ];
    }

    #[DataProvider('depositProvider')]
    public function test_deposit_increases_balance(int $initial, int $amount, int $expected): void
    {
        $user = User::factory()->create(['wallet_balance' => $initial]);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_COMPLETED,
            'idempotency_key' => 'ik-dep-'.$amount.'-'.$initial,
            'description' => 'شارژ',
        ]);

        $this->wallet->deposit($user, $amount, $tx);

        $this->assertSame($expected, $this->wallet->getBalance($user));
        $this->assertTrue($this->wallet->reconcile($user->fresh())['ok']);
        $this->assertSame(
            1,
            WalletLedger::query()
                ->where('user_id', $user->id)
                ->where('direction', WalletLedger::DIRECTION_CREDIT)
                ->where('source_key', 'payment:'.$tx->id)
                ->count()
        );
    }

    public function test_duplicate_deposit_is_idempotent(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 12_000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_COMPLETED,
            'idempotency_key' => 'ik-dup-dep',
            'description' => 'شارژ',
        ]);

        $this->wallet->deposit($user, 12_000, $tx);
        $this->wallet->deposit($user->fresh(), 12_000, $tx);

        $this->assertSame(12_000, $this->wallet->getBalance($user));
        $this->assertSame(1, WalletLedger::query()->where('source_key', 'payment:'.$tx->id)->count());
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: bool, 3: int}>
     */
    public static function withdrawProvider(): array
    {
        return [
            'کسر موفق' => [50_000, 20_000, true, 30_000],
            'کسر کل موجودی' => [10_000, 10_000, true, 0],
            'موجودی ناکافی' => [5_000, 6_000, false, 5_000],
            'کسر صفرگونه نامعتبر با مبلغ بیش از موجودی' => [0, 1, false, 0],
        ];
    }

    #[DataProvider('withdrawProvider')]
    public function test_withdraw_and_balance_guard(int $initial, int $amount, bool $ok, int $expected): void
    {
        $user = User::factory()->create(['wallet_balance' => $initial]);

        $result = $this->wallet->withdraw($user, $amount, 'خرید آزمون');

        $this->assertSame($ok, $result);
        $this->assertSame($expected, $this->wallet->getBalance($user));
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: bool}>
     */
    public static function hasEnoughProvider(): array
    {
        return [
            'کافی' => [40_000, 10_000, true],
            'مساوی' => [10_000, 10_000, true],
            'ناکافی' => [9_999, 10_000, false],
            'صفر' => [0, 1, false],
        ];
    }

    #[DataProvider('hasEnoughProvider')]
    public function test_has_enough_balance(int $balance, int $needed, bool $expected): void
    {
        $user = User::factory()->create(['wallet_balance' => $balance]);

        $this->assertSame($expected, $this->wallet->hasEnough($user, $needed));
    }

    public function test_debit_throws_when_insufficient_balance(): void
    {
        $user = User::factory()->create(['wallet_balance' => 1_000]);

        try {
            $this->wallet->debit($user, 1_001, ['description' => 'خرید']);
            $this->fail('Expected InsufficientBalanceException');
        } catch (InsufficientBalanceException $e) {
            $this->assertSame(1_000, $e->available);
            $this->assertSame(1_001, $e->requested);
        }

        $this->assertSame(1_000, $this->wallet->getBalance($user));
        $this->assertSame(0, WalletLedger::query()->where('user_id', $user->id)->where('direction', WalletLedger::DIRECTION_DEBIT)->count());
    }

    public function test_refund_deposit_debits_wallet(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $deposit = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 8_000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_COMPLETED,
            'idempotency_key' => 'ik-wallet-ref',
            'description' => 'شارژ',
        ]);
        $this->wallet->deposit($user, 8_000, $deposit);

        $refund = $this->wallet->refund($deposit->fresh());

        $this->assertSame('refund', $refund->type);
        $this->assertSame(0, $this->wallet->getBalance($user));
        $this->assertTrue($this->wallet->reconcile($user->fresh())['ok']);
    }
}
