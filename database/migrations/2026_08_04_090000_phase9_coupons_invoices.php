<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 15, 0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->decimal('min_purchase', 15, 0)->nullable();
            $table->enum('applicable_to', ['subscription', 'pdf', 'both'])->default('both');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->unique()->after('description');
            $table->string('invoice_pdf')->nullable()->after('invoice_number');
            $table->foreignId('coupon_id')->nullable()->after('invoice_pdf')->constrained('coupons')->nullOnDelete();
            $table->decimal('discount_amount', 15, 0)->default(0)->after('coupon_id');
            $table->decimal('original_amount', 15, 0)->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['invoice_number', 'invoice_pdf', 'discount_amount', 'original_amount']);
        });
        Schema::dropIfExists('coupons');
    }
};
