<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_chat_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('team_chat_connections')->cascadeOnDelete();
            $table->string('channel_id'); // Slack channel ID or Teams channel ID
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->integer('member_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'channel_id']);
            $table->index('channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_chat_channels');
    }
};
