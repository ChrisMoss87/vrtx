<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('sender_type'); // visitor, agent, system
            $table->foreignId('sender_id')->nullable(); // user_id for agents
            $table->text('content');
            $table->string('content_type')->default('text'); // text, html, image, file
            $table->json('attachments')->nullable(); // array of file URLs
            $table->json('metadata')->nullable(); // any additional data
            $table->boolean('is_internal')->default(false); // internal notes not visible to visitor
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
