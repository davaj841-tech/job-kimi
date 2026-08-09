<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'province')) {
                $table->string('province', 100)->nullable()->after('name');
            }
        });

        if (! Schema::hasTable('exam_subjects')) {
            Schema::create('exam_subjects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug', 80)->unique();
                $table->string('icon', 20)->nullable()->default('📘');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $defaults = [
            ['name' => 'معارف', 'slug' => 'islamic', 'icon' => '🕌', 'sort_order' => 1],
            ['name' => 'ادبیات', 'slug' => 'literature', 'icon' => '📖', 'sort_order' => 2],
            ['name' => 'ریاضی', 'slug' => 'math', 'icon' => '➗', 'sort_order' => 3],
            ['name' => 'شیمی', 'slug' => 'chemistry', 'icon' => '🧪', 'sort_order' => 4],
            ['name' => 'فیزیک', 'slug' => 'physics', 'icon' => '⚛️', 'sort_order' => 5],
            ['name' => 'هوش', 'slug' => 'iq', 'icon' => '🧠', 'sort_order' => 6],
            ['name' => 'انگلیسی', 'slug' => 'english', 'icon' => '🔤', 'sort_order' => 7],
            ['name' => 'عمومی', 'slug' => 'general', 'icon' => '📝', 'sort_order' => 8],
        ];

        foreach ($defaults as $row) {
            $exists = DB::table('exam_subjects')->where('slug', $row['slug'])->exists();
            if (! $exists) {
                DB::table('exam_subjects')->insert(array_merge($row, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // تغییر enum به string برای انعطاف دروس جدید (SQLite/MySQL)
        if (Schema::hasColumn('questions', 'subject')) {
            try {
                Schema::table('questions', function (Blueprint $table) {
                    $table->string('subject', 80)->nullable()->default('general')->change();
                });
            } catch (\Throwable) {
                // بدون doctrine/dbal؛ در sqlite معمولاً string است
            }
        }

        Schema::table('exam_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_attempts', 'subject')) {
                $table->string('subject', 80)->nullable()->after('exam_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('exam_attempts', 'subject')) {
                $table->dropColumn('subject');
            }
        });
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'province')) {
                $table->dropColumn('province');
            }
        });
        Schema::dropIfExists('exam_subjects');
    }
};
