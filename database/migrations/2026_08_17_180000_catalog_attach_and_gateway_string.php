<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('job_posts', 'auto_catalog')) {
                $table->boolean('auto_catalog')->default(true)->after('job_classification_id');
            }
            if (! Schema::hasColumn('job_posts', 'exam_ids')) {
                $table->json('exam_ids')->nullable()->after('auto_catalog');
            }
            if (! Schema::hasColumn('job_posts', 'pdf_ids')) {
                $table->json('pdf_ids')->nullable()->after('exam_ids');
            }
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_posts', 'job_classification_id')) {
                $table->foreignId('job_classification_id')->nullable()->after('category')->constrained('job_classifications')->nullOnDelete();
            }
            if (! Schema::hasColumn('blog_posts', 'auto_catalog')) {
                $table->boolean('auto_catalog')->default(true)->after('job_classification_id');
            }
            if (! Schema::hasColumn('blog_posts', 'exam_ids')) {
                $table->json('exam_ids')->nullable()->after('auto_catalog');
            }
            if (! Schema::hasColumn('blog_posts', 'pdf_ids')) {
                $table->json('pdf_ids')->nullable()->after('exam_ids');
            }
        });

        Schema::table('generated_contents', function (Blueprint $table) {
            if (! Schema::hasColumn('generated_contents', 'job_classification_id')) {
                $table->unsignedBigInteger('job_classification_id')->nullable()->after('job_post_id');
            }
            if (! Schema::hasColumn('generated_contents', 'auto_catalog')) {
                $table->boolean('auto_catalog')->default(true)->after('job_classification_id');
            }
            if (! Schema::hasColumn('generated_contents', 'exam_ids')) {
                $table->json('exam_ids')->nullable()->after('auto_catalog');
            }
            if (! Schema::hasColumn('generated_contents', 'pdf_ids')) {
                $table->json('pdf_ids')->nullable()->after('exam_ids');
            }
        });

        if (Schema::hasColumn('transactions', 'gateway')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE transactions MODIFY gateway VARCHAR(32) NOT NULL DEFAULT 'wallet'");
            }
        }
    }

    public function down(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            $table->dropColumn(['auto_catalog', 'exam_ids', 'pdf_ids']);
        });
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_classification_id');
            $table->dropColumn(['auto_catalog', 'exam_ids', 'pdf_ids']);
        });
        Schema::table('generated_contents', function (Blueprint $table) {
            $table->dropColumn(['job_classification_id', 'auto_catalog', 'exam_ids', 'pdf_ids']);
        });
    }
};
