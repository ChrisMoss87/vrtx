<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('direction'); // inbound, outbound
            $table->string('type')->default('reply'); // original, reply, forward, note
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->jsonb('to_emails')->nullable();
            $table->jsonb('cc_emails')->nullable();
            $table->jsonb('bcc_emails')->nullable();
            $table->text('subject')->nullable();
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->string('status')->default('sent'); // draft, queued, sent, delivered, failed
            $table->unsignedBigInteger('sent_by')->nullable(); // User who sent (for outbound)
            $table->string('external_message_id')->nullable(); // Original email message ID
            $table->string('in_reply_to')->nullable();
            $table->text('raw_headers')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('inbox_conversations')->cascadeOnDelete();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['conversation_id', 'created_at']);
            $table->index(['external_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
