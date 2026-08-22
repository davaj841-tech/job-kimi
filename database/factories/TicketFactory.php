<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ticket> */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => fake()->randomElement([
                'مشکل در پرداخت',
                'سوال درباره اشتراک',
                'خطا در آزمون',
                'پیشنهاد بهبود',
            ]),
            'message' => 'متن پیام پشتیبانی کاربر برای پیگیری موضوع.',
            'category' => fake()->randomElement(['support', 'pre_sale', 'bug', 'suggestion']),
            'status' => fake()->randomElement(['open', 'closed']),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'assigned_to' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => 'open']);
    }
}
