<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'is_random')) {
                $table->boolean('is_random')->default(false)->after('status');
            }
            if (! Schema::hasColumn('exams', 'random_config')) {
                $table->json('random_config')->nullable()->after('is_random');
            }
        });

        Schema::table('questions', function (Blueprint $table) {
            if (! Schema::hasColumn('questions', 'times_served')) {
                $table->unsignedInteger('times_served')->default(0)->after('subject');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'random_config')) {
                $table->dropColumn('random_config');
            }
            if (Schema::hasColumn('exams', 'is_random')) {
                $table->dropColumn('is_random');
            }
        });

        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'times_served')) {
                $table->dropColumn('times_served');
            }
        });
    }
};
