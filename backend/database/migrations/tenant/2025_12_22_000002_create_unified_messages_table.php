<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unified_messages', function (Blueprint $table) {
            $table->id();

            // Conversation reference
            $table->foreignId('conversation_id')
                ->constrained('unified_conversations')
                ->cascadeOnDelete();

            // Channel and direction
            $table->string('channel', 20); // email, chat, whatsapp, sms, call, video
            $table->string('direction', 10); // inbound, outbound

            // Content
            $table->text('content')->nullable();
            $table->text('html_content')->nullable();

            // Sender information
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('sender_phone')->nullable();
            $table->json('recipients')->default('[]');

            // Attachments
            $table->json('attachments')->default('[]');

            // Source reference
            $table->string('source_message_id')->nullable()->index();
            $table->string('external_message_id')->nullable()->index();

            // Status tracking
            $table->string('status', 20)->default('pending'); // pending, sending, sent, delivered, read, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->json('metadata')->default('{}');
            $table->timestamps();

            // Indexes
            $table->index(['conversation_id', 'created_at']);
            $table->index(['source_message_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unified_messages');
    }
};
