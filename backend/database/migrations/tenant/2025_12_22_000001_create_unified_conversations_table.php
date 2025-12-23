<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unified_conversations', function (Blueprint $table) {
            $table->id();

            // Channel identification
            $table->string('channel', 20)->index(); // email, chat, whatsapp, sms, call, video
            $table->string('status', 20)->default('open')->index(); // open, pending, resolved, closed
            $table->string('subject')->nullable();

            // Contact information
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->unsignedBigInteger('contact_record_id')->nullable();
            $table->string('contact_module_api_name')->nullable();

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // CRM Record linking (unified pattern)
            $table->string('linked_module_api_name')->nullable();
            $table->unsignedBigInteger('linked_record_id')->nullable();

            // Source reference (to channel-specific table)
            $table->string('source_conversation_id')->nullable()->index();
            $table->string('external_thread_id')->nullable()->index();

            // Metadata
            $table->json('tags')->default('[]');
            $table->json('metadata')->default('{}');
            $table->integer('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('first_response_at')->nullable();
            $table->integer('response_time_seconds')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->index(['channel', 'status']);
            $table->index(['linked_module_api_name', 'linked_record_id']);
            $table->index(['assigned_to', 'status']);
            $table->index(['source_conversation_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unified_conversations');
    }
};
