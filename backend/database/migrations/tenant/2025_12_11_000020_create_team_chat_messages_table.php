<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('team_chat_connections')->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('team_chat_channels')->nullOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained('team_chat_notifications')->nullOnDelete();
            $table->string('message_id')->nullable(); // External message ID
            $table->text('content');
            $table->json('attachments')->nullable(); // Message attachments/blocks
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();

            // Link to CRM records
            $table->foreignId('module_record_id')->nullable();
            $table->string('module_api_name')->nullable();

            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index(['module_api_name', 'module_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_chat_messages');
    }
};
