<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_chat_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('provider', ['slack', 'teams'])->default('slack');
            $table->string('workspace_id')->nullable(); // Slack workspace ID or Teams tenant ID
            $table->string('workspace_name')->nullable();
            $table->text('access_token'); // Encrypted OAuth token
            $table->text('bot_token')->nullable(); // Encrypted bot token for Slack
            $table->string('bot_user_id')->nullable();
            $table->text('refresh_token')->nullable(); // Encrypted refresh token
            $table->timestamp('token_expires_at')->nullable();
            $table->string('webhook_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->json('scopes')->nullable(); // OAuth scopes granted
            $table->json('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('provider');
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_chat_connections');
    }
};
