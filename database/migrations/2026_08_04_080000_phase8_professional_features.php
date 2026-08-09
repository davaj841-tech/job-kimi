<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->text('message');
            $table->string('category')->default('support'); // support, pre_sale, bug, suggestion
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });

        Schema::create('exam_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->timestamps();
            $table->unique(['exam_id', 'user_id']);
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->enum('position', ['home_top', 'home_middle', 'exam_sidebar'])->default('home_top');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('earned_at')->useCurrent();
            $table->unique(['user_id', 'achievement_id']);
        });

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('notification_preferences')->nullable()->after('status');
                $table->string('locale', 5)->default('fa')->after('notification_preferences');
            });
        }

        if (Schema::hasTable('exams') && ! Schema::hasColumn('exams', 'avg_rating')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->decimal('avg_rating', 3, 2)->default(0)->after('status');
                $table->unsignedInteger('ratings_count')->default(0)->after('avg_rating');
                $table->unsignedInteger('attempts_count')->default(0)->after('ratings_count');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('exam_ratings');
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('tickets');

        if (Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['notification_preferences', 'locale']);
            });
        }

        if (Schema::hasColumn('exams', 'avg_rating')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropColumn(['avg_rating', 'ratings_count', 'attempts_count']);
            });
        }
    }
};
