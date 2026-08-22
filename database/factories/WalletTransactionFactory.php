<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * تراکنش کیف پول روی مدل Transaction.
 * typeهای درخواستی: charge → deposit، exam_purchase → purchase، refund → refund
 * مبلغ همیشه مثبت در DB است؛ علامت با type مشخص می‌شود (deposit/refund+، purchase−).
 *
 * @extends Factory<Transaction>
 */
class WalletTransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $kind = fake()->randomElement(['charge', 'exam_purchase', 'refund']);
        $map = [
            'charge' => 'deposit',
            'exam_purchase' => 'purchase',
            'refund' => 'refund',
        ];
        $type = $map[$kind];
        $amount = fake()->numberBetween(10_000, 500_000);
        $signed = in_array($type, ['purchase', 'withdrawal'], true) ? -$amount : $amount;

        return [
            'user_id' => User::factory(),
            'amount' => $amount,
            'type' => $type,
            'gateway' => 'wallet',
            'status' => Transaction::STATUS_COMPLETED,
            'reference_id' => 'WTX-'.Str::upper(Str::random(10)),
            'idempotency_key' => (string) Str::uuid(),
            'description' => match ($kind) {
                'charge' => "شارژ کیف پول ({$signed} ریال)",
                'exam_purchase' => "خرید آزمون ({$signed} ریال)",
                default => "بازگشت وجه ({$signed} ریال)",
            },
        ];
    }

    public function charge(int $amount = 100000): static
    {
        return $this->state(fn () => [
            'type' => 'deposit',
            'amount' => abs($amount),
            'description' => 'شارژ کیف پول (+'.abs($amount).' ریال)',
        ]);
    }

    public function examPurchase(int $amount = 50000): static
    {
        return $this->state(fn () => [
            'type' => 'purchase',
            'amount' => abs($amount),
            'description' => 'خرید آزمون (-'.abs($amount).' ریال)',
        ]);
    }

    public function refund(int $amount = 50000): static
    {
        return $this->state(fn () => [
            'type' => 'refund',
            'amount' => abs($amount),
            'description' => 'بازگشت وجه (+'.abs($amount).' ریال)',
        ]);
    }
}
