<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_classifications', function (Blueprint $table) {
            if (! Schema::hasColumn('job_classifications', 'icon')) {
                $table->string('icon', 50)->nullable()->after('name');
            }
            if (! Schema::hasColumn('job_classifications', 'color')) {
                $table->string('color', 30)->nullable()->after('icon');
            }
            if (! Schema::hasColumn('job_classifications', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('color');
            }
        });

        $map = [
            'استخدام آموزش و پرورش' => ['icon' => 'school', 'color' => '#0369a1'],
            'بانک‌ها' => ['icon' => 'bank', 'color' => '#0f766e'],
            'نیروهای مسلح' => ['icon' => 'shield', 'color' => '#1e3a5f'],
            'وزارتخانه‌ها و سازمان‌های دولتی' => ['icon' => 'building', 'color' => '#7c3aed'],
            'شهرداری‌ها' => ['icon' => 'city', 'color' => '#ea580c'],
            'شرکت‌های خصوصی' => ['icon' => 'briefcase', 'color' => '#be123c'],
            'سایر' => ['icon' => 'grid', 'color' => '#64748b'],
            'دستگاه‌های اجرایی' => ['icon' => 'building', 'color' => '#7c3aed'],
        ];

        foreach ($map as $name => $meta) {
            DB::table('job_classifications')->where('name', $name)->update($meta);
        }

        // Ensure دستگاه‌های اجرایی exists as alias-friendly classification
        $exists = DB::table('job_classifications')->where('name', 'دستگاه‌های اجرایی')->exists();
        if (! $exists) {
            DB::table('job_classifications')->insert([
                'name' => 'دستگاه‌های اجرایی',
                'icon' => 'building',
                'color' => '#7c3aed',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('job_classifications', function (Blueprint $table) {
            foreach (['icon', 'color', 'logo_path'] as $col) {
                if (Schema::hasColumn('job_classifications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
