<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('sms_connections')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('sms_templates')->nullOnDelete();
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->string('from_number');
            $table->string('to_number');
            $table->text('content');
            $table->enum('status', ['pending', 'queued', 'sent', 'delivered', 'failed', 'undelivered'])->default('pending');
            $table->string('provider_message_id')->nullable(); // Twilio SID, etc.
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->integer('segment_count')->default(1);
            $table->decimal('cost', 10, 4)->nullable(); // Cost per message

            // Link to CRM records
            $table->foreignId('module_record_id')->nullable();
            $table->string('module_api_name')->nullable();

            // Campaign tracking
            $table->foreignId('campaign_id')->nullable();

            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('provider_message_id');
            $table->index(['to_number', 'created_at']);
            $table->index(['from_number', 'created_at']);
            $table->index('status');
            $table->index(['module_api_name', 'module_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
