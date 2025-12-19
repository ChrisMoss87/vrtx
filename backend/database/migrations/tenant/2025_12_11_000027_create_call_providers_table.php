<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider'); // twilio, vonage, ringcentral, aircall
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('account_sid')->nullable(); // Twilio
            $table->string('phone_number')->nullable(); // Main outbound number
            $table->string('webhook_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->boolean('recording_enabled')->default(true);
            $table->boolean('transcription_enabled')->default(false);
            $table->jsonb('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_providers');
    }
};
