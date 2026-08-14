<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class HomeBannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::query()->updateOrCreate(
            ['position' => 'home_hero', 'title' => 'جاب‌آزمون'],
            [
                'image' => '/banners/jobazmoon-home-hero.png',
                'link' => '/exams',
                'is_active' => true,
                'sort_order' => 0,
            ]
        );
    }
}
