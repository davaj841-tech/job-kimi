<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $defaults = [
            'استخدام آموزش و پرورش',
            'بانک‌ها',
            'نیروهای مسلح',
            'وزارتخانه‌ها و سازمان‌های دولتی',
            'شهرداری‌ها',
            'شرکت‌های خصوصی',
            'سایر',
        ];

        foreach ($defaults as $i => $name) {
            DB::table('job_classifications')->insert([
                'name' => $name,
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('job_posts', function (Blueprint $table) {
            $table->foreignId('job_classification_id')
                ->nullable()
                ->after('company_name')
                ->constrained('job_classifications')
                ->nullOnDelete();
            $table->json('provinces')->nullable()->after('province');
            $table->string('attachment_path')->nullable()->after('source_url');
        });

        // Migrate existing province string → provinces JSON + sync classification from company_name when possible
        $posts = DB::table('job_posts')->select('id', 'province', 'company_name')->get();
        foreach ($posts as $post) {
            $provinces = [];
            if (! empty($post->province)) {
                $provinces = [$post->province];
            }

            $classificationId = null;
            if (! empty($post->company_name)) {
                $classificationId = DB::table('job_classifications')
                    ->where('name', $post->company_name)
                    ->value('id');

                if (! $classificationId) {
                    $classificationId = DB::table('job_classifications')->insertGetId([
                        'name' => $post->company_name,
                        'sort_order' => 100,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('job_posts')->where('id', $post->id)->update([
                'provinces' => json_encode($provinces, JSON_UNESCAPED_UNICODE),
                'job_classification_id' => $classificationId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_classification_id');
            $table->dropColumn(['provinces', 'attachment_path']);
        });

        Schema::dropIfExists('job_classifications');
    }
};
