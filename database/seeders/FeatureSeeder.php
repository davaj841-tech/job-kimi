<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            [
                'name' => 'ai-resume',
                'enabled' => true,
                'config' => null,
                'description' => 'رزومه‌ساز هوشمند',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'pdf-store',
                'enabled' => true,
                'config' => null,
                'description' => 'فروشگاه PDF',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'job-crawler',
                'enabled' => false,
                'config' => null,
                'description' => 'خزشگر آگهی استخدام',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'subscription',
                'enabled' => true,
                'config' => null,
                'description' => 'سیستم اشتراک',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'wallet',
                'enabled' => true,
                'config' => null,
                'description' => 'کیف پول',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $row) {
            Feature::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'enabled' => $row['enabled'],
                    'config' => $row['config'],
                    'description' => $row['description'],
                ]
            );
        }
    }
}
