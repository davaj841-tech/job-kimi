<?php

namespace Database\Factories;

use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use App\Models\JobSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobSource>
 */
class JobSourceFactory extends Factory
{
    protected $model = JobSource::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Career';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'official_url' => 'https://careers.example.gov.ir/',
            'domain' => 'careers.example.gov.ir',
            'source_type' => JobSourceType::Government,
            'reliability_level' => JobSourceReliability::Unverified,
            'priority' => 50,
            'is_enabled' => false,
            'is_approved' => false,
            'crawler_type' => JobCrawlerType::Html,
            'crawl_frequency' => 'daily',
        ];
    }

    public function official(): static
    {
        return $this->state(fn () => [
            'reliability_level' => JobSourceReliability::Official,
        ]);
    }

    public function highlyTrusted(): static
    {
        return $this->state(fn () => [
            'reliability_level' => JobSourceReliability::HighlyTrusted,
        ]);
    }

    public function whitelisted(): static
    {
        return $this->state(fn () => [
            'is_enabled' => true,
            'is_approved' => true,
            'reliability_level' => JobSourceReliability::Official,
            'priority' => 10,
        ]);
    }
}
