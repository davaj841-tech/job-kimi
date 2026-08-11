<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('generated_contents')) {
            return;
        }
        if (! Schema::hasColumn('generated_contents', 'wordpress_post_id')) {
            return;
        }

        Schema::table('generated_contents', function (Blueprint $table) {
            $table->dropColumn('wordpress_post_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('generated_contents')) {
            return;
        }
        if (Schema::hasColumn('generated_contents', 'wordpress_post_id')) {
            return;
        }

        Schema::table('generated_contents', function (Blueprint $table) {
            $table->unsignedBigInteger('wordpress_post_id')->nullable()->index();
        });
    }
};
