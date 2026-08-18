<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'home_phone')) {
                $table->string('home_phone', 11)->nullable()->after('national_code');
            }
            if (! Schema::hasColumn('users', 'military_status')) {
                $table->string('military_status', 40)->nullable();
            }
            if (! Schema::hasColumn('users', 'insurance_history')) {
                $table->string('insurance_history', 80)->nullable();
            }
            if (! Schema::hasColumn('users', 'birth_date')) {
                $table->string('birth_date', 10)->nullable();
            }
            if (! Schema::hasColumn('users', 'birth_province')) {
                $table->string('birth_province', 80)->nullable();
            }
            if (! Schema::hasColumn('users', 'birth_city')) {
                $table->string('birth_city', 80)->nullable();
            }
            if (! Schema::hasColumn('users', 'marital_status')) {
                $table->string('marital_status', 30)->nullable();
            }
            if (! Schema::hasColumn('users', 'field_of_study')) {
                $table->string('field_of_study', 120)->nullable();
            }
            if (! Schema::hasColumn('users', 'address')) {
                $table->string('address', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = [
                'home_phone', 'military_status', 'insurance_history', 'birth_date',
                'birth_province', 'birth_city', 'marital_status', 'field_of_study', 'address',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
