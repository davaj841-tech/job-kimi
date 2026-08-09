<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_contents', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['exam_question', 'job_tip', 'resume_tip', 'blog_post', 'job_crawl']);
            $table->longText('prompt')->nullable();
            $table->longText('generated_content')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_contents');
    }
};
