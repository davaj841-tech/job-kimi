<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-destructive indexes for scheduler dispatch + health filtering.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = collect(Schema::getIndexes('job_sources'))->pluck('name')->all();

        Schema::table('job_sources', function (Blueprint $table) use ($existing) {
            if (Schema::hasColumn('job_sources', 'quality_status')
                && ! in_array('job_sources_quality_status_idx', $existing, true)) {
                $table->index('quality_status', 'job_sources_quality_status_idx');
            }
            if (Schema::hasColumn('job_sources', 'health_backoff_until')
                && ! in_array('job_sources_health_backoff_idx', $existing, true)) {
                $table->index('health_backoff_until', 'job_sources_health_backoff_idx');
            }
            if (Schema::hasColumn('job_sources', 'schedule_mode')
                && ! in_array('job_sources_schedule_mode_idx', $existing, true)) {
                $table->index('schedule_mode', 'job_sources_schedule_mode_idx');
            }
            if (
                Schema::hasColumn('job_sources', 'is_enabled')
                && Schema::hasColumn('job_sources', 'is_approved')
                && Schema::hasColumn('job_sources', 'quality_status')
                && ! in_array('job_sources_dispatch_quality_idx', $existing, true)
            ) {
                $table->index(
                    ['is_enabled', 'is_approved', 'quality_status', 'priority'],
                    'job_sources_dispatch_quality_idx'
                );
            }
        });
    }

    public function down(): void
    {
        $existing = collect(Schema::getIndexes('job_sources'))->pluck('name')->all();

        Schema::table('job_sources', function (Blueprint $table) use ($existing) {
            foreach ([
                'job_sources_quality_status_idx',
                'job_sources_health_backoff_idx',
                'job_sources_schedule_mode_idx',
                'job_sources_dispatch_quality_idx',
            ] as $index) {
                if (in_array($index, $existing, true)) {
                    $table->dropIndex($index);
                }
            }
        });
    }
};
