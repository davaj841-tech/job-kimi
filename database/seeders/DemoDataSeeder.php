<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * داده نمونه برای محیط local/dev — همه مدل‌های اصلی با count(50).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SubscriptionPlanFactorySeeder::class,
            ExamSeeder::class,
            QuestionSeeder::class,
            PaymentSeeder::class,
            WalletTransactionSeeder::class,
            JobPostSeeder::class,
            ResumeSeeder::class,
            TicketSeeder::class,
        ]);
    }
}
