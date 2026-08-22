<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /** @var list<string> */
    private const FIRST_NAMES = [
        'علی', 'محمد', 'حسین', 'رضا', 'امیر', 'مهدی', 'سعید', 'حسین',
        'زهرا', 'فاطمه', 'مریم', 'سارا', 'نرگس', 'مینا', 'الهام', 'نگار',
    ];

    /** @var list<string> */
    private const LAST_NAMES = [
        'محمدی', 'حسینی', 'رضایی', 'کریمی', 'موسوی', 'جعفری', 'احمدی',
        'نوری', 'صادقی', 'کاظمی', 'حیدری', 'اکبری', 'باقری', 'نظری',
    ];

    public function definition(): array
    {
        $first = fake()->randomElement(self::FIRST_NAMES);
        $last = fake()->randomElement(self::LAST_NAMES);
        $name = $first.' '.$last;

        return [
            'name' => $name,
            'mobile' => $this->iranianMobile(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'jobseeker',
            'status' => 'active',
            'is_verified' => true,
            'province' => fake()->randomElement(['تهران', 'اصفهان', 'فارس', 'خراسان رضوی', 'آذربایجان شرقی']),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    /** شماره موبایل ایرانی: 09XXXXXXXXX */
    private function iranianMobile(): string
    {
        $prefix = fake()->randomElement(['0912', '0913', '0914', '0915', '0916', '0917', '0918', '0919', '0901', '0902', '0935', '0936']);

        return $prefix.fake()->unique()->numerify('#######');
    }
}
