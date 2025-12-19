<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->string('wa_message_id')->nullable()->unique(); // WhatsApp message ID
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('type', ['text', 'template', 'image', 'video', 'audio', 'document', 'sticker', 'location', 'contacts', 'interactive', 'reaction', 'button']);
            $table->text('content')->nullable(); // Text content or caption
            $table->json('media')->nullable(); // Media info (url, mime_type, sha256, etc.)
            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->json('template_params')->nullable(); // Template variable values
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('context_message_id')->nullable(); // Reply to message
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
