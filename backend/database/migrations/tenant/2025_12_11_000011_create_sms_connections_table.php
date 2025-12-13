<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('provider', ['twilio', 'vonage', 'messagebird', 'plivo'])->default('twilio');
            $table->string('phone_number'); // The provisioned number
            $table->string('account_sid')->nullable(); // Provider account ID
            $table->text('auth_token'); // Encrypted auth token
            $table->string('messaging_service_sid')->nullable(); // For Twilio
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->json('capabilities')->nullable(); // sms, mms, voice
            $table->json('settings')->nullable();
            $table->integer('daily_limit')->default(1000);
            $table->integer('monthly_limit')->default(30000);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_connections');
    }
};
