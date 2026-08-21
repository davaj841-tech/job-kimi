<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('contact_type', 10);
            $table->string('contact_value', 191);
            $table->string('contact_hash', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('contact_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscriptions');
    }
};
