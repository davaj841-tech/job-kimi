<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE banners MODIFY position VARCHAR(50) NOT NULL DEFAULT 'home_top'");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');
            $rows = DB::table('banners')->get();
            Schema::drop('banners');
            Schema::create('banners', function ($table) {
                $table->id();
                $table->string('title');
                $table->string('image')->nullable();
                $table->string('link')->nullable();
                $table->string('position', 50)->default('home_top');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
            foreach ($rows as $row) {
                DB::table('banners')->insert((array) $row);
            }
            DB::statement('PRAGMA foreign_keys=on');
        }
    }

    public function down(): void
    {
        //
    }
};
