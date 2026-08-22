<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * مدل واقعی پروژه: Transaction (معادل Payment درخواستی).
 *
 * @extends Factory<Transaction>
 */
class PaymentFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $status = fake()->randomElement([
            Transaction::STATUS_PENDING,
            Transaction::STATUS_COMPLETED,
            Transaction::STATUS_FAILED,
        ]);

        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomElement([50000, 100000, 250000, 500000, 990000]),
            'type' => 'deposit',
            'gateway' => fake()->randomElement(['zarinpal', 'nextpay']),
            'status' => $status,
            'reference_id' => $status === Transaction::STATUS_COMPLETED
                ? 'PAY-'.Str::upper(Str::random(12))
                : null,
            'idempotency_key' => (string) Str::uuid(),
            'description' => 'پرداخت آنلاین — '.fake()->randomElement(['شارژ کیف پول', 'خرید اشتراک']),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Transaction::STATUS_PENDING, 'reference_id' => null]);
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => Transaction::STATUS_COMPLETED,
            'reference_id' => 'PAY-'.Str::upper(Str::random(12)),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => Transaction::STATUS_FAILED]);
    }
}
