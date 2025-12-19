<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create templates first since landing_pages references it
        Schema::create('landing_page_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('general'); // lead-capture, event, webinar, product, promo
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->jsonb('content')->default('[]'); // Default page elements
            $table->jsonb('styles')->default('{}'); // Default styling
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft, published, archived
            $table->foreignId('template_id')->nullable()->constrained('landing_page_templates')->nullOnDelete();
            $table->jsonb('content')->default('[]'); // Page elements array
            $table->jsonb('settings')->default('{}'); // Page settings
            $table->jsonb('seo_settings')->default('{}'); // SEO metadata
            $table->jsonb('styles')->default('{}'); // Custom CSS/styling
            $table->string('custom_domain')->nullable();
            $table->boolean('custom_domain_verified')->default(false);
            $table->string('favicon_url')->nullable();
            $table->string('og_image_url')->nullable();
            $table->foreignId('web_form_id')->nullable()->constrained('web_forms')->nullOnDelete();
            $table->string('thank_you_page_type')->default('message'); // message, redirect, page
            $table->text('thank_you_message')->nullable();
            $table->string('thank_you_redirect_url')->nullable();
            $table->foreignId('thank_you_page_id')->nullable();
            $table->boolean('is_ab_testing_enabled')->default(false);
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('campaign_id');
        });

        Schema::create('landing_page_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('landing_pages')->cascadeOnDelete();
            $table->string('name');
            $table->char('variant_code', 1); // A, B, C, etc.
            $table->jsonb('content')->default('[]'); // Variant-specific content
            $table->jsonb('styles')->default('{}'); // Variant-specific styles
            $table->integer('traffic_percentage')->default(50);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_winner')->default(false);
            $table->timestamp('declared_winner_at')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'variant_code']);
            $table->index(['page_id', 'is_active']);
        });

        Schema::create('landing_page_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('landing_pages')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('landing_page_variants')->cascadeOnDelete();
            $table->date('date');
            $table->integer('views')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->integer('form_submissions')->default(0);
            $table->integer('bounces')->default(0);
            $table->decimal('avg_time_on_page', 10, 2)->default(0); // seconds
            $table->jsonb('referrer_breakdown')->default('{}');
            $table->jsonb('device_breakdown')->default('{}');
            $table->jsonb('location_breakdown')->default('{}');
            $table->timestamps();

            $table->unique(['page_id', 'variant_id', 'date']);
            $table->index(['page_id', 'date']);
        });

        Schema::create('landing_page_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('landing_pages')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('landing_page_variants')->cascadeOnDelete();
            $table->string('visitor_id'); // Cookie-based identifier
            $table->string('session_id');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('device_type')->nullable(); // desktop, mobile, tablet
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->boolean('converted')->default(false);
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('submission_id')->nullable()->constrained('web_form_submissions')->nullOnDelete();
            $table->integer('time_on_page')->default(0); // seconds
            $table->integer('scroll_depth')->default(0); // percentage
            $table->timestamps();

            $table->index(['page_id', 'created_at']);
            $table->index(['page_id', 'variant_id']);
            $table->index(['visitor_id', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_visits');
        Schema::dropIfExists('landing_page_analytics');
        Schema::dropIfExists('landing_page_variants');
        Schema::dropIfExists('landing_page_templates');
        Schema::dropIfExists('landing_pages');
    }
};
