<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->string('contact_wa_id'); // WhatsApp ID of the contact
            $table->string('contact_phone');
            $table->string('contact_name')->nullable();
            $table->foreignId('module_record_id')->nullable(); // Link to CRM record
            $table->string('module_api_name')->nullable();
            $table->enum('status', ['open', 'pending', 'closed'])->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_incoming_at')->nullable();
            $table->timestamp('last_outgoing_at')->nullable();
            $table->integer('unread_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['connection_id', 'contact_wa_id']);
            $table->index(['module_record_id', 'module_api_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
