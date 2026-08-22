<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(10000, 1_000_000),
            'type' => fake()->randomElement(['deposit', 'purchase', 'refund', 'withdrawal']),
            'gateway' => fake()->randomElement(['zarinpal', 'nextpay', 'wallet']),
            'status' => fake()->randomElement(['pending', 'success', 'failed']),
            'reference_id' => 'TX-'.Str::upper(Str::random(10)),
            'idempotency_key' => (string) Str::uuid(),
            'description' => fake()->randomElement(['تراکنش آزمایشی', 'پرداخت', 'کیف پول']),
        ];
    }
}
