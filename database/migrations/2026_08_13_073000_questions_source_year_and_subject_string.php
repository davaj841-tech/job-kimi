<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (! Schema::hasColumn('questions', 'source')) {
                $table->string('source', 191)->nullable()->after('subject');
            }
            if (! Schema::hasColumn('questions', 'exam_year')) {
                $table->string('exam_year', 20)->nullable()->after('source');
            }
        });

        // Expand subject beyond fixed enum so custom ExamSubject slugs work
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE questions MODIFY subject VARCHAR(64) NOT NULL DEFAULT 'general'");
        } elseif ($driver === 'pgsql') {
            // no-op if already varchar; enum was mysql-specific in original migration
        } elseif ($driver === 'sqlite') {
            // SQLite ignores enum; already flexible
        }
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'exam_year')) {
                $table->dropColumn('exam_year');
            }
            if (Schema::hasColumn('questions', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
