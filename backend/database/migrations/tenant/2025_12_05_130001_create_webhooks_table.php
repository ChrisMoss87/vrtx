<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url', 2000);
            $table->string('secret', 64)->nullable(); // For HMAC signing
            $table->json('events'); // ["record.created", "record.updated", "deal.stage_changed"]
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete(); // Specific module or all
            $table->json('headers')->nullable(); // Custom headers
            $table->boolean('is_active')->default(true);
            $table->boolean('verify_ssl')->default(true);
            $table->integer('timeout')->default(30); // Seconds
            $table->integer('retry_count')->default(3);
            $table->integer('retry_delay')->default(60); // Seconds between retries
            $table->timestamp('last_triggered_at')->nullable();
            $table->string('last_status')->nullable(); // success, failed
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);
            $table->index('module_id');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('payload');
            $table->string('status'); // pending, success, failed
            $table->integer('attempts')->default(0);
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'status']);
            $table->index(['status', 'next_retry_at']);
        });

        // Incoming webhooks (for receiving data from external services)
        Schema::create('incoming_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('token', 64)->unique(); // URL token for identification
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->json('field_mapping')->nullable(); // Maps incoming fields to module fields
            $table->boolean('is_active')->default(true);
            $table->enum('action', ['create', 'update', 'upsert'])->default('create');
            $table->string('upsert_field')->nullable(); // Field to match for upsert
            $table->timestamp('last_received_at')->nullable();
            $table->integer('received_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('token');
            $table->index(['is_active', 'deleted_at']);
        });

        Schema::create('incoming_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_webhook_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->string('status'); // success, failed, invalid
            $table->foreignId('record_id')->nullable()->constrained('module_records')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45);
            $table->timestamp('created_at');

            $table->index(['incoming_webhook_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_webhook_logs');
        Schema::dropIfExists('incoming_webhooks');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
