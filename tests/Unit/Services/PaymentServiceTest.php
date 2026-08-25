<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Actions\Payment\PaymentAction;
use App\Exceptions\IdempotencyException;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return array{service: PaymentService, gateway: MockInterface&PaymentGatewayInterface}
     */
    private function mockPaymentService(): array
    {
        /** @var MockInterface&PaymentGatewayInterface $gateway */
        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        /** @var MockInterface&PaymentGatewayManager $manager */
        $manager = Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldReceive('driver')->with('zarinpal')->andReturn($gateway);

        return [
            'service' => new PaymentService($manager),
            'gateway' => $gateway,
        ];
    }

    /**
     * @return array<string, array{0: array{authority: ?string, payment_url: ?string, error: ?string}, 1: bool}>
     */
    public static function createOutcomesProvider(): array
    {
        return [
            'موفق' => [
                [
                    'authority' => 'A000000000000000000000000000000000000',
                    'payment_url' => 'https://www.zarinpal.com/pg/StartPay/A000',
                    'error' => null,
                ],
                true,
            ],
            'ناموفق' => [
                [
                    'authority' => null,
                    'payment_url' => null,
                    'error' => 'gateway unavailable',
                ],
                false,
            ],
            'timeout' => [
                [
                    'authority' => null,
                    'payment_url' => null,
                    'error' => 'gateway timeout',
                ],
                false,
            ],
        ];
    }

    #[DataProvider('createOutcomesProvider')]
    public function test_create_delegates_to_mocked_gateway(array $gatewayResponse, bool $expectSuccess): void
    {
        ['service' => $service, 'gateway' => $gateway] = $this->mockPaymentService();

        $gateway->shouldReceive('request')
            ->once()
            ->with(50000, 'شارژ کیف پول', 'https://example.test/callback', Mockery::type('array'))
            ->andReturn($gatewayResponse);

        $result = $service->create(
            'zarinpal',
            50000,
            'شارژ کیف پول',
            'https://example.test/callback'
        );

        $this->assertSame($gatewayResponse['authority'], $result['authority']);
        $this->assertSame($gatewayResponse['payment_url'], $result['payment_url']);
        $this->assertSame($gatewayResponse['error'], $result['error']);
        $this->assertSame($expectSuccess, $result['error'] === null && $result['authority'] !== null);
    }

    /**
     * @return array<string, array{0: array{success: bool, ref_id: ?string, error: ?string}}>
     */
    public static function verifyOutcomesProvider(): array
    {
        return [
            'موفق' => [[
                'success' => true,
                'ref_id' => '123456789',
                'error' => null,
            ]],
            'ناموفق' => [[
                'success' => false,
                'ref_id' => null,
                'error' => 'verification failed',
            ]],
            'timeout' => [[
                'success' => false,
                'ref_id' => null,
                'error' => 'gateway timeout',
            ]],
        ];
    }

    #[DataProvider('verifyOutcomesProvider')]
    public function test_verify_uses_mocked_gateway(array $verifyResponse): void
    {
        ['service' => $service, 'gateway' => $gateway] = $this->mockPaymentService();

        $gateway->shouldReceive('verify')
            ->once()
            ->with('AUTH-1', 25000, Mockery::type('array'))
            ->andReturn($verifyResponse);

        $result = $service->verify('zarinpal', 'AUTH-1', 25000, ['order_id' => '9']);

        $this->assertSame($verifyResponse['success'], $result['success']);
        $this->assertSame($verifyResponse['ref_id'], $result['ref_id']);
        $this->assertSame($verifyResponse['error'], $result['error']);
    }

    public function test_failed_verify_rolls_back_wallet_credit_inside_db_transaction(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0, 'status' => 'active']);
        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 40000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-ROLLBACK',
            'idempotency_key' => 'ik-rollback-1',
            'description' => 'شارژ',
        ]);

        ['service' => $payments, 'gateway' => $gateway] = $this->mockPaymentService();
        $gateway->shouldReceive('verify')
            ->once()
            ->andReturn([
                'success' => false,
                'ref_id' => null,
                'error' => 'verify rejected',
            ]);

        $this->app->instance(PaymentService::class, $payments);

        $action = app(PaymentAction::class);

        try {
            $action->verify('AUTH-ROLLBACK', 'ik-rollback-1');
            $this->fail('Expected IdempotencyException');
        } catch (IdempotencyException $e) {
            $this->assertStringContainsString('verify rejected', $e->getMessage());
        }

        $this->assertSame(0, (int) $user->fresh()->wallet_balance);
        $this->assertSame(Transaction::STATUS_PENDING, $transaction->fresh()->status);
        $this->assertSame(
            0,
            DB::table('wallet_ledgers')->where('user_id', $user->id)->count()
        );
    }

    public function test_successful_verify_via_action_credits_wallet_once(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0, 'status' => 'active']);
        Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 15000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-OK',
            'idempotency_key' => 'ik-ok-1',
            'description' => 'شارژ',
        ]);

        ['service' => $payments, 'gateway' => $gateway] = $this->mockPaymentService();
        $gateway->shouldReceive('verify')
            ->once()
            ->andReturn([
                'success' => true,
                'ref_id' => 'REF-42',
                'error' => null,
            ]);
        $this->app->instance(PaymentService::class, $payments);

        $completed = app(PaymentAction::class)->verify('AUTH-OK', 'ik-ok-1');

        $this->assertSame(Transaction::STATUS_COMPLETED, $completed->status);
        $this->assertSame(15000, (int) $user->fresh()->wallet_balance);
    }

    public function test_refund_reverses_completed_deposit(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0, 'status' => 'active']);
        $deposit = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 20000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_COMPLETED,
            'reference_id' => 'AUTH-REF',
            'idempotency_key' => 'ik-ref-1',
            'description' => 'شارژ',
        ]);

        app(WalletService::class)->deposit($user, 20000, $deposit);
        $this->assertSame(20000, (int) $user->fresh()->wallet_balance);

        ['service' => $service] = $this->mockPaymentService();
        $refund = $service->refund($deposit->fresh());

        $this->assertSame('refund', $refund->type);
        $this->assertSame(Transaction::STATUS_COMPLETED, $refund->status);
        $this->assertSame(0, (int) $user->fresh()->wallet_balance);
    }
}
