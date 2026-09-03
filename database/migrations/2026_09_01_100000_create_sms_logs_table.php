<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_logs')) {
            return;
        }

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_masked', 20);
            $table->string('message_type', 32);
            $table->string('provider', 32);
            $table->string('status', 16);
            $table->string('message_id')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('duration_ms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['message_type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
