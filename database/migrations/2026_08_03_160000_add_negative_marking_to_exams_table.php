<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('has_negative_marking')->default(false)->after('total_marks');
            $table->decimal('negative_mark_ratio', 5, 4)->default(0.3333)->after('has_negative_marking');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['has_negative_marking', 'negative_mark_ratio']);
        });
    }
};
