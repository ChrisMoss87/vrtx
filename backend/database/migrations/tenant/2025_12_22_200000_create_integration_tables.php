<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Integration connections - stores OAuth tokens and API keys per tenant
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->string('integration_slug', 50);
            $table->string('name')->nullable();
            $table->enum('status', ['active', 'inactive', 'error', 'expired'])->default('active');
            $table->json('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->enum('sync_status', ['idle', 'syncing', 'error'])->default('idle');
            $table->text('error_message')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->unique('integration_slug');
            $table->index('status');
            $table->index('sync_status');
        });

        // Sync logs - track sync history and errors
        Schema::create('integration_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('integration_connections')->cascadeOnDelete();
            $table->enum('direction', ['push', 'pull', 'both'])->default('both');
            $table->string('entity_type', 50);
            $table->string('action', 50)->nullable();
            $table->integer('records_processed')->default(0);
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->integer('records_failed')->default(0);
            $table->json('errors')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->enum('status', ['running', 'completed', 'failed', 'cancelled'])->default('running');
            $table->timestamps();

            $table->index(['connection_id', 'created_at']);
            $table->index(['entity_type', 'status']);
        });

        // Field mappings - custom field mappings between CRM and external systems
        Schema::create('integration_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('integration_connections')->cascadeOnDelete();
            $table->string('crm_entity', 50);
            $table->string('crm_field', 100);
            $table->string('external_entity', 50)->nullable();
            $table->string('external_field', 100);
            $table->enum('direction', ['push', 'pull', 'both'])->default('both');
            $table->string('transform')->nullable();
            $table->json('transform_options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['connection_id', 'crm_entity', 'crm_field', 'external_field'], 'integration_field_mappings_unique');
            $table->index(['connection_id', 'crm_entity']);
        });

        // External entity mappings - track which CRM records map to which external records
        Schema::create('integration_entity_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('integration_connections')->cascadeOnDelete();
            $table->string('crm_entity', 50);
            $table->unsignedBigInteger('crm_record_id');
            $table->string('external_entity', 50);
            $table->string('external_id', 255);
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'crm_entity', 'crm_record_id'], 'int_entity_map_crm_unique');
            $table->unique(['connection_id', 'external_entity', 'external_id'], 'int_entity_map_ext_unique');
            $table->index(['crm_entity', 'crm_record_id']);
        });

        // Incoming webhooks - webhook endpoint configurations
        Schema::create('integration_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('integration_connections')->cascadeOnDelete();
            $table->string('webhook_id', 100)->nullable();
            $table->string('endpoint_url')->nullable();
            $table->string('endpoint_secret', 255)->nullable();
            $table->json('subscribed_events')->nullable();
            $table->enum('status', ['active', 'inactive', 'pending'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->integer('received_count')->default(0);
            $table->timestamps();

            $table->index(['connection_id', 'status']);
        });

        // Webhook event logs - track incoming webhook events
        Schema::create('integration_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('integration_webhooks')->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->string('event_id', 255)->nullable();
            $table->json('payload')->nullable();
            $table->json('headers')->nullable();
            $table->enum('status', ['received', 'processing', 'processed', 'failed', 'ignored'])->default('received');
            $table->text('error_message')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();

            $table->index(['webhook_id', 'received_at']);
            $table->index(['event_type', 'status']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_webhook_logs');
        Schema::dropIfExists('integration_webhooks');
        Schema::dropIfExists('integration_entity_mappings');
        Schema::dropIfExists('integration_field_mappings');
        Schema::dropIfExists('integration_sync_logs');
        Schema::dropIfExists('integration_connections');
    }
};
