<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'wallet_frozen_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('wallet_frozen_at')->nullable()->after('wallet_balance');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'wallet_frozen_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wallet_frozen_at');
        });
    }
};
