<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_errors')) {
            Schema::create('site_errors', function (Blueprint $table) {
                $table->id();
                $table->string('level', 20)->default('error');
                $table->string('message', 1000);
                $table->string('message_fa', 1000)->nullable();
                $table->string('exception_class', 255)->nullable();
                $table->string('file', 500)->nullable();
                $table->unsignedInteger('line')->nullable();
                $table->string('url', 1000)->nullable();
                $table->string('method', 10)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('trace')->nullable();
                $table->json('context')->nullable();
                $table->unsignedInteger('occurrences')->default(1);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['resolved_at', 'last_seen_at']);
                $table->index('level');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_errors');
    }
};
