<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pdf_product_id')->constrained('pdf_products')->cascadeOnDelete();
            $table->decimal('price_paid', 15, 0)->default(0);
            $table->timestamp('purchased_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'pdf_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_purchases');
    }
};
