<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('job_sources', 'schedule_mode')) {
                $table->string('schedule_mode', 20)->default('global')->after('crawl_frequency');
            }
            if (! Schema::hasColumn('job_sources', 'custom_schedule_times')) {
                $table->json('custom_schedule_times')->nullable()->after('schedule_mode');
            }
        });

        $defaults = [
            'enabled' => false,
            'timezone' => 'Asia/Tehran',
            'max_concurrent' => 5,
            'dispatch_delay_seconds' => 0,
            'retry_tries' => 2,
            'times' => [],
        ];

        $exists = DB::table('settings')->where('key', 'aggregation_schedule')->exists();
        if (! $exists) {
            DB::table('settings')->insert([
                'key' => 'aggregation_schedule',
                'value' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
                'group' => 'aggregation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('job_sources', function (Blueprint $table) {
            if (Schema::hasColumn('job_sources', 'custom_schedule_times')) {
                $table->dropColumn('custom_schedule_times');
            }
            if (Schema::hasColumn('job_sources', 'schedule_mode')) {
                $table->dropColumn('schedule_mode');
            }
        });

        DB::table('settings')->where('key', 'aggregation_schedule')->delete();
    }
};
