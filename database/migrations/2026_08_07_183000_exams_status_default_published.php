<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // پیش‌فرض ستون status را به published نزدیک می‌کنیم (MySQL)
        try {
            DB::statement("ALTER TABLE exams MODIFY status ENUM('draft','published','archived') NOT NULL DEFAULT 'published'");
        } catch (\Throwable) {
            // SQLite و درایورهای بدون ALTER ENUM — نادیده
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE exams MODIFY status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft'");
        } catch (\Throwable) {
        }
    }
};
