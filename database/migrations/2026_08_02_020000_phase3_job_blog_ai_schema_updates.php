<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->makeJobPostCreatedByNullable();

        Schema::table('blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('blog_posts', 'category')) {
                $table->string('category')->nullable()->after('featured_image');
            }
            if (! Schema::hasColumn('blog_posts', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('category');
            }
            if (! Schema::hasColumn('blog_posts', 'meta_description')) {
                $table->string('meta_description', 500)->nullable()->after('meta_title');
            }
            if (! Schema::hasColumn('blog_posts', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! Schema::hasColumn('pdf_products', 'job_post_id')) {
            Schema::table('pdf_products', function (Blueprint $table) {
                $table->foreignId('job_post_id')->nullable()->after('id')->constrained('job_posts')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');

        if (Schema::hasColumn('pdf_products', 'job_post_id')) {
            Schema::table('pdf_products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('job_post_id');
            });
        }

        Schema::table('blog_posts', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['category', 'meta_title', 'meta_description'],
                fn ($c) => Schema::hasColumn('blog_posts', $c)
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
            if (Schema::hasColumn('blog_posts', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }

    protected function makeJobPostCreatedByNullable(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildJobPostsSqlite();

            return;
        }

        // Skip if already nullable (MySQL)
        $column = collect(DB::select("SHOW COLUMNS FROM job_posts LIKE 'created_by'"))->first();
        if ($column && strtoupper((string) $column->Null) === 'YES') {
            return;
        }

        Schema::table('job_posts', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        DB::statement('ALTER TABLE job_posts MODIFY created_by BIGINT UNSIGNED NULL');

        Schema::table('job_posts', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    protected function rebuildJobPostsSqlite(): void
    {
        // Prefer skipping rebuild when created_by is already nullable.
        // Renaming job_posts breaks other tables' SQLite FK refs (e.g. exams.job_post_id).
        if ($this->sqliteColumnIsNullable('job_posts', 'created_by')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        // Recover from a previous partial migration attempt
        $source = Schema::hasTable('job_posts_old') ? 'job_posts_old' : 'job_posts';

        if (Schema::hasTable('job_posts_old') && Schema::hasTable('job_posts')) {
            // Prefer whichever has rows; otherwise keep old as source
            if (DB::table('job_posts')->count() > DB::table('job_posts_old')->count()) {
                $source = 'job_posts';
                Schema::drop('job_posts_old');
                Schema::rename('job_posts', 'job_posts_old');
                $source = 'job_posts_old';
            } else {
                Schema::drop('job_posts');
            }
        } elseif ($source === 'job_posts') {
            Schema::rename('job_posts', 'job_posts_old');
            $source = 'job_posts_old';
        }

        foreach (['job_posts_status_index', 'job_posts_old_status_index'] as $index) {
            try {
                DB::statement("DROP INDEX IF EXISTS \"{$index}\"");
            } catch (\Throwable) {
                // ignore
            }
        }

        Schema::dropIfExists('job_posts');

        Schema::create('job_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('company_name');
            $table->longText('description')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('job_category')->nullable();
            $table->timestamp('registration_deadline')->nullable();
            $table->timestamp('exam_date')->nullable();
            $table->string('registration_link')->nullable();
            $table->string('source_url')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS job_posts_status_index ON job_posts (status)');

        $columns = 'id, title, company_name, description, province, city, job_category, registration_deadline, exam_date, registration_link, source_url, status, is_featured, view_count, created_by, approved_by, created_at, updated_at';
        if (Schema::hasTable($source)) {
            DB::statement("INSERT INTO job_posts ({$columns}) SELECT {$columns} FROM {$source}");
            Schema::dropIfExists($source);
        }

        // Recreate exams so job_post_id FK points at the new job_posts table
        $this->rebuildExamsJobPostFkSqlite();

        Schema::enableForeignKeyConstraints();
    }

    protected function sqliteColumnIsNullable(string $table, string $column): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $info = DB::select("PRAGMA table_info({$table})");
        foreach ($info as $col) {
            if (($col->name ?? null) === $column) {
                return (int) ($col->notnull ?? 1) === 0;
            }
        }

        return false;
    }

    protected function rebuildExamsJobPostFkSqlite(): void
    {
        if (! Schema::hasTable('exams') || Schema::hasTable('exams_old')) {
            return;
        }

        Schema::rename('exams', 'exams_old');

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('job_post_id')->nullable();
            $table->longText('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedInteger('passing_score')->default(50);
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('total_marks')->default(0);
            $table->boolean('is_free')->default(true);
            $table->decimal('price', 15, 0)->default(0);
            $table->string('subscription_required')->default('any');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index('slug');
            $table->index('status');
        });

        $examColumns = 'id, title, slug, category_id, job_post_id, description, duration_minutes, passing_score, total_questions, total_marks, is_free, price, subscription_required, status, created_by, created_at, updated_at';
        DB::statement("INSERT INTO exams ({$examColumns}) SELECT {$examColumns} FROM exams_old");
        Schema::drop('exams_old');
    }
};
