<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('email_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Message identification
            $table->string('message_id')->nullable()->index();
            $table->string('thread_id')->nullable()->index();
            $table->foreignId('parent_id')->nullable()->constrained('email_messages')->nullOnDelete();

            // Direction and status
            $table->string('direction')->default('outbound');
            $table->string('status')->default('draft');

            // Sender/recipient
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->json('to_emails')->default('[]');
            $table->json('cc_emails')->default('[]');
            $table->json('bcc_emails')->default('[]');
            $table->string('reply_to')->nullable();

            // Content
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->json('headers')->nullable();

            // Organization
            $table->string('folder')->default('INBOX');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->boolean('is_important')->default(false);

            // Attachments
            $table->boolean('has_attachments')->default(false);
            $table->json('attachments')->default('[]');

            // Tracking
            $table->string('tracking_id')->nullable()->unique();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);

            // Linked record (polymorphic)
            $table->string('linked_record_type')->nullable();
            $table->unsignedBigInteger('linked_record_id')->nullable();

            // Template
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->nullOnDelete();

            // Timestamps
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('failed_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['account_id', 'direction', 'status']);
            $table->index(['account_id', 'folder', 'is_read']);
            $table->index(['linked_record_type', 'linked_record_id']);
            $table->index('sent_at');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
