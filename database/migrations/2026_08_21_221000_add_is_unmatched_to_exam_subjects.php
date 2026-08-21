<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->boolean('is_unmatched')->default(false)->after('is_active');
            $table->index('is_unmatched');
        });
    }

    public function down(): void
    {
        Schema::table('exam_subjects', function (Blueprint $table) {
            $table->dropIndex(['is_unmatched']);
            $table->dropColumn('is_unmatched');
        });
    }
};
