<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main campaigns table
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // email, drip, event, product_launch, newsletter, re_engagement
            $table->string('status')->default('draft'); // draft, scheduled, active, paused, completed, cancelled
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->decimal('spent', 12, 2)->default(0);
            $table->jsonb('settings')->default('{}'); // Campaign-specific settings
            $table->jsonb('goals')->default('[]'); // Campaign goals/KPIs
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'start_date']);
            $table->index('type');
        });

        // Campaign audiences - segment rules for targeting
        Schema::create('campaign_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->jsonb('segment_rules')->default('[]'); // Filter rules for selecting contacts
            $table->integer('contact_count')->default(0); // Cached count
            $table->boolean('is_dynamic')->default(true); // Re-evaluate on send vs. static list
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'module_id']);
        });

        // Static audience members (for non-dynamic audiences)
        Schema::create('campaign_audience_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_audience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('record_id')->constrained('module_records')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, sent, opened, clicked, converted, unsubscribed, bounced
            $table->timestamps();

            $table->unique(['campaign_audience_id', 'record_id']);
            $table->index('status');
        });

        // Campaign assets (emails, images, content)
        Schema::create('campaign_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // email, image, document, landing_page
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('subject')->nullable(); // For emails
            $table->longText('content')->nullable(); // HTML content or file path
            $table->jsonb('metadata')->default('{}'); // Additional metadata
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['campaign_id', 'type']);
        });

        // Individual sends/deliveries
        Schema::create('campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('record_id')->constrained('module_records')->cascadeOnDelete();
            $table->string('channel'); // email, sms, push
            $table->string('recipient'); // Email address, phone number, etc.
            $table->string('status')->default('pending'); // pending, sent, delivered, opened, clicked, bounced, failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->default('{}'); // Open/click tracking data
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['record_id', 'campaign_id']);
            $table->index('scheduled_at');
        });

        // Campaign link clicks tracking
        Schema::create('campaign_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_send_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('link_name')->nullable();
            $table->timestamp('clicked_at');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['campaign_send_id', 'url']);
        });

        // Aggregate metrics per day
        Schema::create('campaign_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('sends')->default(0);
            $table->integer('delivered')->default(0);
            $table->integer('opens')->default(0);
            $table->integer('unique_opens')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('unique_clicks')->default(0);
            $table->integer('bounces')->default(0);
            $table->integer('unsubscribes')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'date']);
        });

        // Campaign conversion tracking
        Schema::create('campaign_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_send_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('record_id')->constrained('module_records')->cascadeOnDelete();
            $table->string('conversion_type'); // lead, opportunity, deal_won, custom
            $table->decimal('value', 12, 2)->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('converted_at');
            $table->timestamps();

            $table->index(['campaign_id', 'conversion_type']);
        });

        // Email templates library
        Schema::create('email_campaign_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // newsletter, promotional, transactional, etc.
            $table->text('subject')->nullable();
            $table->longText('html_content');
            $table->longText('text_content')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->jsonb('variables')->default('[]'); // Available merge variables
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('category');
        });

        // Unsubscribe list
        Schema::create('campaign_unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('record_id')->nullable()->constrained('module_records')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('unsubscribed_at');
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_unsubscribes');
        Schema::dropIfExists('email_campaign_templates');
        Schema::dropIfExists('campaign_conversions');
        Schema::dropIfExists('campaign_metrics');
        Schema::dropIfExists('campaign_clicks');
        Schema::dropIfExists('campaign_sends');
        Schema::dropIfExists('campaign_assets');
        Schema::dropIfExists('campaign_audience_members');
        Schema::dropIfExists('campaign_audiences');
        Schema::dropIfExists('campaigns');
    }
};
