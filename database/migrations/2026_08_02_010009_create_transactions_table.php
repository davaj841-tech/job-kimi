<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 0);
            $table->enum('type', ['deposit', 'withdrawal', 'purchase', 'refund']);
            // درگاه‌ها از تنظیمات ادمین قابل پیکربندی هستند
            $table->enum('gateway', ['zarinpal', 'wallet'])->default('wallet');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending')->index();
            $table->string('reference_id')->nullable()->index();
            $table->string('description')->nullable();
            $table->nullableMorphs('payable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
