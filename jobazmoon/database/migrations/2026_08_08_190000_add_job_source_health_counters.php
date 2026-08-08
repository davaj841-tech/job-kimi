<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('job_sources', 'consecutive_failures')) {
                $table->unsignedInteger('consecutive_failures')->default(0)->after('quality_notes');
            }
            if (! Schema::hasColumn('job_sources', 'consecutive_empty_crawls')) {
                $table->unsignedInteger('consecutive_empty_crawls')->default(0)->after('consecutive_failures');
            }
            if (! Schema::hasColumn('job_sources', 'total_successful_crawls')) {
                $table->unsignedInteger('total_successful_crawls')->default(0)->after('consecutive_empty_crawls');
            }
            if (! Schema::hasColumn('job_sources', 'total_failed_crawls')) {
                $table->unsignedInteger('total_failed_crawls')->default(0)->after('total_successful_crawls');
            }
            if (! Schema::hasColumn('job_sources', 'total_empty_successful_crawls')) {
                $table->unsignedInteger('total_empty_successful_crawls')->default(0)->after('total_failed_crawls');
            }
            if (! Schema::hasColumn('job_sources', 'lifetime_jobs_found')) {
                $table->unsignedInteger('lifetime_jobs_found')->default(0)->after('total_empty_successful_crawls');
            }
            if (! Schema::hasColumn('job_sources', 'lifetime_jobs_created')) {
                $table->unsignedInteger('lifetime_jobs_created')->default(0)->after('lifetime_jobs_found');
            }
            if (! Schema::hasColumn('job_sources', 'lifetime_jobs_updated')) {
                $table->unsignedInteger('lifetime_jobs_updated')->default(0)->after('lifetime_jobs_created');
            }
            if (! Schema::hasColumn('job_sources', 'lifetime_duplicates')) {
                $table->unsignedInteger('lifetime_duplicates')->default(0)->after('lifetime_jobs_updated');
            }
            if (! Schema::hasColumn('job_sources', 'lifetime_rejected')) {
                $table->unsignedInteger('lifetime_rejected')->default(0)->after('lifetime_duplicates');
            }
            if (! Schema::hasColumn('job_sources', 'lifetime_validation_errors')) {
                $table->unsignedInteger('lifetime_validation_errors')->default(0)->after('lifetime_rejected');
            }
            if (! Schema::hasColumn('job_sources', 'last_http_status')) {
                $table->unsignedSmallInteger('last_http_status')->nullable()->after('lifetime_validation_errors');
            }
            if (! Schema::hasColumn('job_sources', 'last_crawl_outcome')) {
                $table->string('last_crawl_outcome', 40)->nullable()->after('last_http_status');
            }
            if (! Schema::hasColumn('job_sources', 'health_backoff_until')) {
                $table->timestamp('health_backoff_until')->nullable()->after('last_crawl_outcome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_sources', function (Blueprint $table) {
            $cols = [
                'consecutive_failures',
                'consecutive_empty_crawls',
                'total_successful_crawls',
                'total_failed_crawls',
                'total_empty_successful_crawls',
                'lifetime_jobs_found',
                'lifetime_jobs_created',
                'lifetime_jobs_updated',
                'lifetime_duplicates',
                'lifetime_rejected',
                'lifetime_validation_errors',
                'last_http_status',
                'last_crawl_outcome',
                'health_backoff_until',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('job_sources', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
