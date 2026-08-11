<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('duration_days')->default(0);
            $table->decimal('price', 15, 0)->default(0);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // ورود اصلی با موبایل (ایران)
            $table->string('mobile', 11)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('national_code', 10)->nullable();
            $table->string('avatar')->nullable();
            $table->enum('role', ['jobseeker', 'employer', 'operator', 'admin'])->default('jobseeker');
            $table->decimal('wallet_balance', 15, 0)->default(0);
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->string('otp_code', 64)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('mobile');
            $table->index('role');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('subscription_plans');
    }
};
