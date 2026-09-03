<?php

namespace Tests\Feature;

use App\Models\JobSource;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PilotJobSourceSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederPriorityTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<string>  $keys */
    private function clearEnv(array $keys): void
    {
        foreach ($keys as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    /** @param  array<string, string>  $vars */
    private function setEnv(array $vars): void
    {
        foreach ($vars as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public function test_job_source_accepts_priority_within_tinyint_range(): void
    {
        $source = JobSource::factory()->create([
            'slug' => 'priority-tinyint-test',
            'priority' => 255,
        ]);

        $this->assertSame(255, $source->fresh()->priority);
    }

    public function test_pilot_job_source_seeder_is_idempotent_and_clamps_high_priorities(): void
    {
        $this->seed(PilotJobSourceSeeder::class);
        $firstCount = JobSource::query()->count();
        $this->assertGreaterThan(0, $firstCount);

        $ndf = JobSource::query()->where('slug', 'ndf-fund')->first();
        $this->assertNotNull($ndf);
        $maxAllowed = (new PilotJobSourceSeeder)->priorityColumnMax();
        $this->assertSame(min(256, $maxAllowed), $ndf->priority);
        $this->assertLessThanOrEqual($maxAllowed, $ndf->priority);

        $this->seed(PilotJobSourceSeeder::class);
        $this->assertSame($firstCount, JobSource::query()->count());
    }

    public function test_admin_user_seeder_requires_identity_env_vars(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $this->clearEnv([
            'ADMIN_SEED_MOBILE',
            'ADMIN_SEED_USERNAME',
            'ADMIN_SEED_EMAIL',
            'ADMIN_SEED_PASSWORD',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_SEED_MOBILE');

        $this->seed(AdminUserSeeder::class);
    }

    public function test_admin_user_seeder_is_idempotent_with_env(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $this->setEnv([
            'ADMIN_SEED_MOBILE' => '09121112233',
            'ADMIN_SEED_USERNAME' => 'seed_admin',
            'ADMIN_SEED_EMAIL' => 'seed-admin@example.test',
            'ADMIN_SEED_PASSWORD' => 'SecurePass123!',
        ]);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->where('mobile', '09121112233')->count());
        $user = User::query()->where('mobile', '09121112233')->firstOrFail();
        $this->assertSame('seed_admin', $user->username);
        $this->assertSame('super_admin', $user->role);
    }

    public function test_database_seeder_runs_twice_without_exception(): void
    {
        $this->setEnv([
            'ADMIN_SEED_MOBILE' => '09123334455',
            'ADMIN_SEED_USERNAME' => 'db_seed_admin',
            'ADMIN_SEED_EMAIL' => 'db-seed-admin@example.test',
            'ADMIN_SEED_PASSWORD' => 'SecurePass123!',
        ]);

        $this->seed(DatabaseSeeder::class);
        $sourcesAfterFirst = JobSource::query()->count();
        $this->assertGreaterThan(200, $sourcesAfterFirst);

        $this->seed(DatabaseSeeder::class);
        $this->assertSame($sourcesAfterFirst, JobSource::query()->count());
        $this->assertSame(1, User::query()->where('mobile', '09123334455')->count());
        $ndf = JobSource::query()->where('slug', 'ndf-fund')->first();
        $this->assertNotNull($ndf);
        $maxAllowed = (new PilotJobSourceSeeder)->priorityColumnMax();
        $this->assertSame(min(256, $maxAllowed), $ndf->priority);
    }
}
