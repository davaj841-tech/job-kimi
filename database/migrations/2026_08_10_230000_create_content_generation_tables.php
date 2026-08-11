<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('content_type', 64)->index();
            $table->string('title_template', 500);
            $table->longText('content_template');
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedSmallInteger('priority')->default(50)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('generated_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('content_type', 64)->index();
            $table->string('status', 32)->default('draft')->index();
            $table->string('source_type', 64)->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->foreignId('job_post_id')->nullable()->constrained('job_posts')->nullOnDelete();
            $table->unsignedBigInteger('blog_post_id')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->string('content_hash', 64)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('generation_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['job_post_id', 'content_type'], 'generated_contents_job_type_unique');
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_contents');
        Schema::dropIfExists('content_templates');
    }
};
