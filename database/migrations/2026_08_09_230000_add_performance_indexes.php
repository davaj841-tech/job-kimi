<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes mapped to real JobAzmoon schema.
 *
 * Adaptations from the original checklist:
 * - exam_results → exam_attempts
 * - jobs (postings) → job_posts
 * - tracking_code → reference_id (already indexed; skipped)
 * - questions.content → question_text (FULLTEXT MySQL only)
 * - questions.sort_order → skipped (column does not exist)
 * - Existing uniques (exams.slug, users.mobile, transactions.idempotency_key) skipped
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('exams', 'idx_exams_filter', ['status', 'category_id', 'created_at']);
        $this->addIndexIfMissing('exams', 'idx_exams_creator', ['created_by', 'status']);

        $this->addIndexIfMissing('questions', 'idx_questions_exam_subject', ['exam_id', 'subject']);
        $this->addFullTextIfMissing('questions', 'idx_questions_content_ft', ['question_text']);

        $this->addIndexIfMissing('transactions', 'idx_transactions_user_status', ['user_id', 'status', 'created_at']);
        $this->addIndexIfMissing('transactions', 'idx_transactions_type_status', ['type', 'status']);

        $this->addIndexIfMissing('exam_attempts', 'idx_results_user_exam_score', ['user_id', 'exam_id', 'score']);
        $this->addIndexIfMissing('exam_attempts', 'idx_results_leaderboard', ['exam_id', 'score', 'created_at']);
        $this->addIndexIfMissing('exam_attempts', 'idx_results_user_history', ['user_id', 'created_at']);

        $this->addIndexIfMissing('users', 'idx_users_role', ['role', 'created_at']);

        $this->addFullTextIfMissing('job_posts', 'idx_jobs_search', ['title', 'description']);
        $this->addIndexIfMissing('job_posts', 'idx_jobs_source_date', ['job_source_id', 'published_at']);
        $this->addIndexIfMissing('job_posts', 'idx_jobs_status_date', ['status', 'published_at']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('exams', 'idx_exams_filter');
        $this->dropIndexIfExists('exams', 'idx_exams_creator');

        $this->dropIndexIfExists('questions', 'idx_questions_exam_subject');
        $this->dropIndexIfExists('questions', 'idx_questions_content_ft');

        $this->dropIndexIfExists('transactions', 'idx_transactions_user_status');
        $this->dropIndexIfExists('transactions', 'idx_transactions_type_status');

        $this->dropIndexIfExists('exam_attempts', 'idx_results_user_exam_score');
        $this->dropIndexIfExists('exam_attempts', 'idx_results_leaderboard');
        $this->dropIndexIfExists('exam_attempts', 'idx_results_user_history');

        $this->dropIndexIfExists('users', 'idx_users_role');

        $this->dropIndexIfExists('job_posts', 'idx_jobs_search');
        $this->dropIndexIfExists('job_posts', 'idx_jobs_source_date');
        $this->dropIndexIfExists('job_posts', 'idx_jobs_status_date');
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, string $name, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->isMysql()) {
            $cols = implode(', ', array_map(fn (string $c) => '`'.$c.'`', $columns));
            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD INDEX `%s` (%s), ALGORITHM=INPLACE, LOCK=NONE',
                $table,
                $name,
                $cols
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function addFullTextIfMissing(string $table, string $name, array $columns): void
    {
        if (! $this->isMysql() || ! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        $cols = implode(', ', array_map(fn (string $c) => '`'.$c.'`', $columns));
        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD FULLTEXT INDEX `%s` (%s), ALGORITHM=INPLACE, LOCK=NONE',
            $table,
            $name,
            $cols
        ));
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $name)) {
            return;
        }

        if ($this->isMysql()) {
            DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $name));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $database = Schema::getConnection()->getDatabaseName();
            $rows = DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$database, $table, $name]
            );

            return $rows !== [];
        }

        if ($driver === 'sqlite') {
            $rows = DB::select('PRAGMA index_list('.$table.')');
            foreach ($rows as $row) {
                $row = (array) $row;
                if (($row['name'] ?? '') === $name) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function isMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
