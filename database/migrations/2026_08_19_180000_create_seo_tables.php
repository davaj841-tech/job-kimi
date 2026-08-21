<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable'); // seoable_type, seoable_id
            $table->string('title', 160)->nullable();
            $table->string('description', 320)->nullable();
            $table->string('canonical')->nullable();
            $table->string('robots', 50)->default('index, follow');
            $table->string('og_title', 160)->nullable();
            $table->string('og_description', 320)->nullable();
            $table->string('og_image')->nullable();
            $table->string('twitter_card', 50)->default('summary_large_image');
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });

        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->morphs('keywordable');
            $table->string('focus_keyword', 100);
            $table->json('related_keywords')->nullable();
            $table->string('search_intent', 30)->nullable(); // informational, transactional, navigational
            $table->timestamps();

            $table->unique(['keywordable_type', 'keywordable_id']);
        });

        Schema::create('seo_analyses', function (Blueprint $table) {
            $table->id();
            $table->morphs('analyzable');
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('status', 20)->default('pending'); // excellent, good, needs_improvement, poor, pending
            $table->json('checks')->nullable(); // detailed check results
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['analyzable_type', 'analyzable_id']);
            $table->index('score');
            $table->index('status');
        });

        Schema::create('seo_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained('seo_analyses')->cascadeOnDelete();
            $table->string('type', 50); // title, description, keyword, content, image, link, schema, technical
            $table->string('severity', 20); // critical, warning, info
            $table->string('message', 500);
            $table->string('field', 100)->nullable();
            $table->timestamps();

            $table->index(['analysis_id', 'severity']);
        });

        Schema::create('seo_links', function (Blueprint $table) {
            $table->id();
            $table->morphs('linkable'); // source content
            $table->string('target_url', 500);
            $table->string('target_type', 50)->nullable(); // internal, external
            $table->string('anchor_text', 200)->nullable();
            $table->string('rel', 50)->nullable(); // nofollow, sponsored, ugc
            $table->boolean('is_broken')->default(false);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index('is_broken');
            $table->index('target_type');
        });

        Schema::create('seo_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 500)->unique();
            $table->string('target_url', 500);
            $table->unsignedSmallInteger('status_code')->default(301); // 301, 302, 410
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('seo_faqs', function (Blueprint $table) {
            $table->id();
            $table->morphs('faqable');
            $table->string('question', 500);
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['faqable_type', 'faqable_id', 'sort_order']);
        });

        Schema::create('seo_audits', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // full, duplicate, cannibalization, broken_links
            $table->json('results')->nullable();
            $table->unsignedInteger('pages_checked')->default(0);
            $table->unsignedInteger('issues_found')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('type');
        });

        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_suggestions');
        Schema::dropIfExists('seo_links');
        Schema::dropIfExists('seo_faqs');
        Schema::dropIfExists('seo_audits');
        Schema::dropIfExists('seo_analyses');
        Schema::dropIfExists('seo_keywords');
        Schema::dropIfExists('seo_meta');
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('seo_settings');
    }
};
