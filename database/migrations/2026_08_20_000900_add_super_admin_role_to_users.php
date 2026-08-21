<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE users MODIFY role ENUM('jobseeker','employer','operator','admin','super_admin') NOT NULL DEFAULT 'jobseeker'"
            );

            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 20)->default('jobseeker')->change();
            });

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['jobseeker', 'employer', 'operator', 'admin', 'super_admin'])
                ->default('jobseeker')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE users MODIFY role ENUM('jobseeker','employer','operator','admin') NOT NULL DEFAULT 'jobseeker'"
            );

            return;
        }

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['jobseeker', 'employer', 'operator', 'admin'])
                ->default('jobseeker')
                ->change();
        });
    }
};
