<?php

namespace Tests\Feature;

use App\Models\JobSource;
use Database\Seeders\PilotJobSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PilotJobSourceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_seeder_runs_without_out_of_range_priority(): void
    {
        $this->seed(PilotJobSourceSeeder::class);

        $this->assertGreaterThan(0, JobSource::query()->count());

        $max = JobSource::query()->max('priority');
        $this->assertLessThanOrEqual(PilotJobSourceSeeder::PRIORITY_SMALLINT_MAX, (int) $max);
        $this->assertGreaterThanOrEqual(PilotJobSourceSeeder::PRIORITY_MIN, (int) JobSource::query()->min('priority'));
    }

    public function test_normalize_priority_clamps_to_tinyint_ceiling(): void
    {
        $seeder = new PilotJobSourceSeeder;
        $this->assertSame(255, $seeder->normalizePriority(256, PilotJobSourceSeeder::PRIORITY_TINYINT_MAX));
        $this->assertSame(255, $seeder->normalizePriority(410, PilotJobSourceSeeder::PRIORITY_TINYINT_MAX));
        $this->assertSame(0, $seeder->normalizePriority(-5, PilotJobSourceSeeder::PRIORITY_TINYINT_MAX));
        $this->assertSame(50, $seeder->normalizePriority(50, PilotJobSourceSeeder::PRIORITY_TINYINT_MAX));
        $this->assertSame(256, $seeder->normalizePriority(256, PilotJobSourceSeeder::PRIORITY_SMALLINT_MAX));
    }

    public function test_seeder_is_idempotent_and_does_not_delete_existing_sources(): void
    {
        $manual = JobSource::factory()->create([
            'slug' => 'manual-keep-me',
            'name' => 'Manual Keep',
            'priority' => 10,
        ]);

        $this->seed(PilotJobSourceSeeder::class);
        $afterFirst = JobSource::query()->count();
        $this->assertGreaterThan(1, $afterFirst);
        $this->assertDatabaseHas('job_sources', ['id' => $manual->id, 'slug' => 'manual-keep-me']);

        $this->seed(PilotJobSourceSeeder::class);
        $this->assertSame($afterFirst, JobSource::query()->count());
        $this->assertDatabaseHas('job_sources', ['id' => $manual->id, 'slug' => 'manual-keep-me']);
    }

    public function test_expected_pilot_sources_exist_after_seed(): void
    {
        $this->seed(PilotJobSourceSeeder::class);

        foreach (['cbi-central-bank', 'sanjesh-org', 'ndf-fund'] as $slug) {
            $this->assertDatabaseHas('job_sources', ['slug' => $slug]);
        }

        $ndf = JobSource::query()->where('slug', 'ndf-fund')->firstOrFail();
        $maxAllowed = (new PilotJobSourceSeeder)->priorityColumnMax();
        $this->assertLessThanOrEqual($maxAllowed, $ndf->priority);
        $this->assertSame(
            min(256, $maxAllowed),
            $ndf->priority
        );
    }

    public function test_all_seeded_priorities_within_detected_column_range(): void
    {
        $this->seed(PilotJobSourceSeeder::class);

        $maxAllowed = (new PilotJobSourceSeeder)->priorityColumnMax();
        $outOfRange = JobSource::query()
            ->where('priority', '>', $maxAllowed)
            ->orWhere('priority', '<', PilotJobSourceSeeder::PRIORITY_MIN)
            ->count();

        $this->assertSame(0, $outOfRange);
    }

    public function test_config_contains_priority_above_tinyint_that_must_be_clamped_on_tinyint(): void
    {
        $sources = config('aggregation.official_sources', []);
        $above = collect($sources)->first(fn ($s) => (int) ($s['priority'] ?? 0) > 255);
        $this->assertNotNull($above, 'Fixture expectation: catalog still has priority > 255');

        $clamped = (new PilotJobSourceSeeder)->normalizePriority(
            (int) $above['priority'],
            PilotJobSourceSeeder::PRIORITY_TINYINT_MAX
        );
        $this->assertSame(255, $clamped);
    }
}
