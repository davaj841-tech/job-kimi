<?php

namespace App\Services\Payment;

/**
 * In-memory gateway for PHPUnit. Stores the amount from request() and
 * refuses verify() unless the caller passes that same amount (proves the
 * application uses the database amount, not a client-supplied figure).
 */
class FakePaymentGateway implements PaymentGatewayInterface
{
    /** @var array<string, array{amount: int, verified: bool}> */
    protected static array $payments = [];

    public static bool $failNextRequest = false;

    public static bool $failNextVerify = false;

    public static function reset(): void
    {
        self::$payments = [];
        self::$failNextRequest = false;
        self::$failNextVerify = false;
    }

    public static function seed(string $authority, int $amount): void
    {
        self::$payments[$authority] = [
            'amount' => $amount,
            'verified' => false,
        ];
    }

    public static function storedAmount(string $authority): ?int
    {
        return self::$payments[$authority]['amount'] ?? null;
    }

    public function getName(): string
    {
        return 'fake';
    }

    public function getDisplayName(): string
    {
        return 'درگاه آزمایشی';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{authority: ?string, payment_url: ?string, error: ?string}
     */
    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        if (self::$failNextRequest) {
            self::$failNextRequest = false;

            return ['authority' => null, 'payment_url' => null, 'error' => 'fake gateway request failed'];
        }

        $authority = 'FAKE-'.strtoupper(bin2hex(random_bytes(16)));
        self::$payments[$authority] = [
            'amount' => $amount,
            'verified' => false,
        ];

        return [
            'authority' => $authority,
            'payment_url' => 'https://pay.fake.test/StartPay/'.$authority,
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{success: bool, ref_id: ?string, error: ?string}
     */
    public function verify(string $authority, int $amount, array $meta = []): array
    {
        if (self::$failNextVerify) {
            self::$failNextVerify = false;

            return ['success' => false, 'ref_id' => null, 'error' => 'fake gateway verify failed'];
        }

        $row = self::$payments[$authority] ?? null;
        if ($row === null) {
            return ['success' => false, 'ref_id' => null, 'error' => 'authority نامعتبر است'];
        }

        if ((int) $row['amount'] !== $amount) {
            return ['success' => false, 'ref_id' => null, 'error' => 'مبلغ تایید با مبلغ درگاه یکسان نیست'];
        }

        self::$payments[$authority]['verified'] = true;

        return [
            'success' => true,
            'ref_id' => 'REF-FAKE-'.substr($authority, -8),
            'error' => null,
        ];
    }
}
