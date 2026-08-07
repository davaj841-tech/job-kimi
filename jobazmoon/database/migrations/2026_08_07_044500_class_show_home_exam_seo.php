<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_classifications', function (Blueprint $table) {
            if (! Schema::hasColumn('job_classifications', 'show_on_home')) {
                $table->boolean('show_on_home')->default(true)->after('is_active');
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'seo_tag')) {
                $table->string('seo_tag', 191)->nullable()->unique()->after('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_classifications', function (Blueprint $table) {
            if (Schema::hasColumn('job_classifications', 'show_on_home')) {
                $table->dropColumn('show_on_home');
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'seo_tag')) {
                $table->dropUnique(['seo_tag']);
                $table->dropColumn('seo_tag');
            }
        });
    }
};
