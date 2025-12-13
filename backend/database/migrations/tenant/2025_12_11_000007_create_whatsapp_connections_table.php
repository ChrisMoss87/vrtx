<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone_number_id'); // Meta phone number ID
            $table->string('waba_id')->nullable(); // WhatsApp Business Account ID
            $table->text('access_token'); // Encrypted access token
            $table->string('display_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('quality_rating')->nullable(); // GREEN, YELLOW, RED
            $table->string('messaging_limit')->nullable(); // TIER_1, TIER_2, etc.
            $table->string('webhook_verify_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->json('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connections');
    }
};
