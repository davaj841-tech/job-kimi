<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('direction', 8);
            $table->decimal('amount', 15, 0);
            $table->decimal('balance_after', 15, 0);
            $table->string('type', 32);
            $table->string('reference', 64);
            $table->string('source_key', 191);
            $table->string('description')->nullable();
            $table->char('prev_hash', 64);
            $table->char('hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique('reference');
            $table->unique('source_key');
            $table->index(['user_id', 'id']);
        });

        if (Schema::hasTable('features')) {
            $exists = DB::table('features')->where('name', 'wallet_allow_negative')->exists();
            if (! $exists) {
                DB::table('features')->insert([
                    'name' => 'wallet_allow_negative',
                    'enabled' => false,
                    'config' => null,
                    'description' => 'اجازه موجودی منفی کیف پول (فقط با فعال‌سازی صریح)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledgers');
    }
};
