<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_sources')) {
            Schema::create('job_sources', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug', 120)->unique();
                $table->string('official_url', 500)->nullable();
                $table->string('domain', 255)->index();
                $table->string('source_type', 40)->index();
                $table->string('reliability_level', 40)->default('unverified')->index();
                $table->unsignedTinyInteger('priority')->default(50)->index();
                $table->boolean('is_enabled')->default(false)->index();
                $table->boolean('is_approved')->default(false)->index();
                $table->string('crawler_type', 40)->default('html');
                $table->string('crawl_frequency', 40)->default('daily');
                $table->timestamp('last_crawled_at')->nullable()->index();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_failure_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['is_enabled', 'is_approved', 'reliability_level'], 'job_sources_dispatchable_idx');
            });
        }

        if (! Schema::hasTable('job_source_endpoints')) {
            Schema::create('job_source_endpoints', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_source_id')->constrained('job_sources')->cascadeOnDelete();
                $table->string('url', 1000);
                $table->string('endpoint_type', 40)->default('html');
                $table->string('http_method', 10)->default('GET');
                $table->string('parser_type', 80)->nullable();
                $table->boolean('is_enabled')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['job_source_id', 'is_enabled'], 'job_source_endpoints_source_enabled_idx');
            });
        }

        if (! Schema::hasTable('crawler_runs')) {
            Schema::create('crawler_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_source_id')->nullable()->constrained('job_sources')->nullOnDelete();
                $table->string('status', 40)->default('pending')->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('jobs_found')->default(0);
                $table->unsignedInteger('jobs_created')->default(0);
                $table->unsignedInteger('jobs_updated')->default(0);
                $table->unsignedInteger('duplicates')->default(0);
                $table->unsignedInteger('errors_count')->default(0);
                $table->unsignedInteger('execution_ms')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['job_source_id', 'status'], 'crawler_runs_source_status_idx');
                $table->index('started_at');
            });
        }

        if (! Schema::hasTable('crawler_errors')) {
            Schema::create('crawler_errors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_source_id')->nullable()->constrained('job_sources')->nullOnDelete();
                $table->foreignId('crawler_run_id')->nullable()->constrained('crawler_runs')->cascadeOnDelete();
                $table->string('error_type', 80)->index();
                $table->text('message');
                $table->string('url', 1000)->nullable();
                $table->json('context')->nullable();
                $table->timestamp('occurred_at')->useCurrent()->index();
                $table->timestamps();

                $table->index(['job_source_id', 'occurred_at'], 'crawler_errors_source_time_idx');
            });
        }

        if (! Schema::hasTable('job_duplicates')) {
            Schema::create('job_duplicates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('original_job_post_id')->constrained('job_posts')->cascadeOnDelete();
                $table->foreignId('duplicate_job_post_id')->constrained('job_posts')->cascadeOnDelete();
                $table->decimal('similarity_score', 5, 2)->nullable();
                $table->string('detection_reason', 120)->nullable();
                $table->timestamps();

                $table->unique(['original_job_post_id', 'duplicate_job_post_id'], 'job_duplicates_pair_unique');
                $table->index('original_job_post_id');
                $table->index('duplicate_job_post_id');
            });
        }

        // Non-destructive helper columns/indexes on existing job_posts for future aggregation
        Schema::table('job_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('job_posts', 'job_source_id')) {
                $table->foreignId('job_source_id')
                    ->nullable()
                    ->after('approved_by')
                    ->constrained('job_sources')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('job_posts', 'external_id')) {
                $table->string('external_id', 191)->nullable()->after('job_source_id');
            }
            if (! Schema::hasColumn('job_posts', 'content_hash')) {
                $table->string('content_hash', 64)->nullable()->after('external_id');
            }
        });

        try {
            Schema::table('job_posts', function (Blueprint $table) {
                $table->index('registration_deadline', 'job_posts_deadline_idx');
            });
        } catch (\Throwable) {
            // index may already exist
        }

        try {
            Schema::table('job_posts', function (Blueprint $table) {
                $table->index(['job_source_id', 'external_id'], 'job_posts_source_external_idx');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('job_posts', function (Blueprint $table) {
                $table->index('content_hash', 'job_posts_content_hash_idx');
            });
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            if (Schema::hasColumn('job_posts', 'job_source_id')) {
                $table->dropConstrainedForeignId('job_source_id');
            }
            foreach (['external_id', 'content_hash'] as $col) {
                if (Schema::hasColumn('job_posts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('job_duplicates');
        Schema::dropIfExists('crawler_errors');
        Schema::dropIfExists('crawler_runs');
        Schema::dropIfExists('job_source_endpoints');
        Schema::dropIfExists('job_sources');
    }
};
