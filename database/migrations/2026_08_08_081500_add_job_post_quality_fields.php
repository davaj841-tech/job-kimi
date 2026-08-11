<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('job_posts', 'education')) {
                $table->string('education', 190)->nullable();
            }
            if (! Schema::hasColumn('job_posts', 'field_of_study')) {
                $table->string('field_of_study', 190)->nullable();
            }
            if (! Schema::hasColumn('job_posts', 'experience')) {
                $table->string('experience', 190)->nullable();
            }
            if (! Schema::hasColumn('job_posts', 'employment_type')) {
                $table->string('employment_type', 80)->nullable();
            }
            if (! Schema::hasColumn('job_posts', 'requirements')) {
                $table->text('requirements')->nullable();
            }
            if (! Schema::hasColumn('job_posts', 'registration_starts_at')) {
                $table->timestamp('registration_starts_at')->nullable();
            }
            if (! Schema::hasColumn('job_posts', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }
        });

        try {
            Schema::table('job_posts', function (Blueprint $table) {
                $table->index('registration_starts_at', 'job_posts_reg_start_idx');
            });
        } catch (Throwable) {
        }
    }

    public function down(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            try {
                $table->dropIndex('job_posts_reg_start_idx');
            } catch (Throwable) {
            }
            foreach ([
                'education', 'field_of_study', 'experience', 'employment_type',
                'requirements', 'registration_starts_at', 'published_at',
            ] as $col) {
                if (Schema::hasColumn('job_posts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
