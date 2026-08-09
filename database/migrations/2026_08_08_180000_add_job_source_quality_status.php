<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('job_sources', 'quality_status')) {
                $table->string('quality_status', 40)->default('active')->after('is_approved');
            }
            if (! Schema::hasColumn('job_sources', 'quality_notes')) {
                $table->text('quality_notes')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_sources', function (Blueprint $table) {
            if (Schema::hasColumn('job_sources', 'quality_notes')) {
                $table->dropColumn('quality_notes');
            }
            if (Schema::hasColumn('job_sources', 'quality_status')) {
                $table->dropColumn('quality_status');
            }
        });
    }
};
