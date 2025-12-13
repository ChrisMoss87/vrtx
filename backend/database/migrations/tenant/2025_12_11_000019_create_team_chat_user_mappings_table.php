<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_chat_user_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('team_chat_connections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('external_user_id'); // Slack user ID or Teams user ID
            $table->string('external_username')->nullable();
            $table->string('external_email')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->unique(['connection_id', 'user_id']);
            $table->unique(['connection_id', 'external_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_chat_user_mappings');
    }
};
