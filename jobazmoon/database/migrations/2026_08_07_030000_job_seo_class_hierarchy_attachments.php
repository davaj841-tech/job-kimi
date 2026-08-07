<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('job_posts', 'seo_tag')) {
                $table->string('seo_tag', 191)->nullable()->unique()->after('title');
            }
        });

        Schema::table('job_classifications', function (Blueprint $table) {
            if (! Schema::hasColumn('job_classifications', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('job_classifications')
                    ->nullOnDelete();
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'job_classification_id')) {
                $table->foreignId('job_classification_id')
                    ->nullable()
                    ->after('job_post_id')
                    ->constrained('job_classifications')
                    ->nullOnDelete();
            }
        });

        Schema::table('pdf_products', function (Blueprint $table) {
            if (! Schema::hasColumn('pdf_products', 'job_classification_id')) {
                $table->foreignId('job_classification_id')
                    ->nullable()
                    ->after('job_post_id')
                    ->constrained('job_classifications')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasTable('job_post_attachments')) {
            Schema::create('job_post_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_post_id')->constrained('job_posts')->cascadeOnDelete();
                $table->string('path');
                $table->string('title')->nullable();
                $table->string('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_post_attachments');

        Schema::table('pdf_products', function (Blueprint $table) {
            if (Schema::hasColumn('pdf_products', 'job_classification_id')) {
                $table->dropConstrainedForeignId('job_classification_id');
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'job_classification_id')) {
                $table->dropConstrainedForeignId('job_classification_id');
            }
        });

        Schema::table('job_classifications', function (Blueprint $table) {
            if (Schema::hasColumn('job_classifications', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
        });

        Schema::table('job_posts', function (Blueprint $table) {
            if (Schema::hasColumn('job_posts', 'seo_tag')) {
                $table->dropUnique(['seo_tag']);
                $table->dropColumn('seo_tag');
            }
        });
    }
};
