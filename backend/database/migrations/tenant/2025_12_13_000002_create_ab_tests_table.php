<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // email_subject, email_content, cta_button, send_time, form_layout
            $table->string('entity_type'); // email_template, campaign, web_form
            $table->unsignedBigInteger('entity_id');
            $table->string('status')->default('draft'); // draft, running, paused, completed
            $table->string('goal')->default('conversion'); // conversion, click_rate, open_rate
            $table->integer('min_sample_size')->default(100);
            $table->decimal('confidence_level', 5, 2)->default(95.00); // 95% default
            $table->boolean('auto_select_winner')->default(true);
            $table->unsignedBigInteger('winner_variant_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('ab_test_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('ab_tests')->cascadeOnDelete();
            $table->string('name');
            $table->char('variant_code', 1); // A, B, C, D
            $table->jsonb('content')->default('{}'); // Variant-specific content/config
            $table->integer('traffic_percentage')->default(50);
            $table->boolean('is_control')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_winner')->default(false);
            $table->timestamps();

            $table->unique(['test_id', 'variant_code']);
            $table->index(['test_id', 'is_active']);
        });

        Schema::create('ab_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('ab_test_variants')->cascadeOnDelete();
            $table->date('date');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->integer('opens')->default(0); // For emails
            $table->decimal('revenue', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['variant_id', 'date']);
            $table->index(['variant_id', 'date']);
        });

        Schema::create('ab_test_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('ab_test_variants')->cascadeOnDelete();
            $table->string('visitor_id')->nullable();
            $table->string('event_type'); // impression, click, conversion, open
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->index(['variant_id', 'event_type', 'created_at']);
            $table->index(['visitor_id', 'variant_id']);
        });

        // Add foreign key for winner_variant_id after variants table exists
        Schema::table('ab_tests', function (Blueprint $table) {
            $table->foreign('winner_variant_id')->references('id')->on('ab_test_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_test_events');
        Schema::dropIfExists('ab_test_results');
        Schema::dropIfExists('ab_test_variants');
        Schema::dropIfExists('ab_tests');
    }
};
