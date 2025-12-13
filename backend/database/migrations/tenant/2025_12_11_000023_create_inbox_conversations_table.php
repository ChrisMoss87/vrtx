<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inbox_id');
            $table->string('subject');
            $table->string('status')->default('open'); // open, pending, resolved, closed
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('channel')->default('email'); // email, live_chat, whatsapp, sms
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('snippet')->nullable(); // Preview of last message
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->integer('message_count')->default(0);
            $table->integer('response_time_seconds')->nullable();
            $table->boolean('is_spam')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->jsonb('tags')->nullable();
            $table->jsonb('custom_fields')->nullable();
            $table->string('external_thread_id')->nullable(); // Original email thread ID
            $table->timestamps();

            $table->foreign('inbox_id')->references('id')->on('shared_inboxes')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('module_records')->nullOnDelete();
            $table->index(['inbox_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['contact_email']);
            $table->index(['last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_conversations');
    }
};
