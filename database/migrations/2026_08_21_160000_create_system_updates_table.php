<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_updates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('version', 32);
            $table->string('previous_version', 32)->nullable();
            $table->string('status', 32)->index();
            $table->string('release_type', 32)->nullable();
            $table->text('description')->nullable();
            $table->json('manifest')->nullable();
            $table->json('preflight')->nullable();
            $table->json('log')->nullable();
            $table->string('pack_path')->nullable();
            $table->string('backup_id')->nullable();
            $table->string('full_backup_path')->nullable();
            $table->string('files_backup_path')->nullable();
            $table->string('database_backup_path')->nullable();
            $table->boolean('migrations_ran')->default(false);
            $table->boolean('migrations_reversible')->nullable();
            $table->boolean('rollback_complete')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['version', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};
