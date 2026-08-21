<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_purchases', function (Blueprint $table) {
            $table->unique(['user_id', 'pdf_product_id'], 'pdf_purchases_user_pdf_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_purchases', function (Blueprint $table) {
            $table->dropUnique('pdf_purchases_user_pdf_unique');
        });
    }
};
