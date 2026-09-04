<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPTIONAL (recommended, not required to unblock PilotJobSourceSeeder).
 *
 * job_sources.priority was created as unsignedTinyInteger (0–255).
 * Official source config assigns sequential priorities up to ~410.
 * MySQL rejects INSERT/UPDATE with 256+ (SQLSTATE[22003]).
 *
 * PilotJobSourceSeeder now clamps to the live column max, so seeding works
 * on TINYINT without this migration. Running this migration restores the
 * full priority range (SMALLINT UNSIGNED 0–65535) without deleting rows.
 *
 * Safe on production: MODIFY only, no truncate/drop/data wipe.
 * Reversible (down clamps values >255 then restores TINYINT).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_sources') || ! Schema::hasColumn('job_sources', 'priority')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE `job_sources` MODIFY `priority` SMALLINT UNSIGNED NOT NULL DEFAULT 50'
            );

            return;
        }

        // SQLite integer affinity already accepts values > 255; no ALTER needed for tests.
    }

    public function down(): void
    {
        if (! Schema::hasTable('job_sources') || ! Schema::hasColumn('job_sources', 'priority')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Clamp first so down() does not fail when priorities > 255 already exist.
            DB::table('job_sources')->where('priority', '>', 255)->update(['priority' => 255]);
            DB::statement(
                'ALTER TABLE `job_sources` MODIFY `priority` TINYINT UNSIGNED NOT NULL DEFAULT 50'
            );
        }
    }
};
