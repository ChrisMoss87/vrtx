<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sentiment_scores', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // email_message, activity, note
            $table->unsignedBigInteger('entity_id');
            $table->string('record_module')->nullable(); // Related CRM record module
            $table->unsignedBigInteger('record_id')->nullable(); // Related CRM record
            $table->decimal('score', 4, 3); // -1.0 to 1.0
            $table->string('category'); // positive, neutral, negative
            $table->string('emotion')->nullable(); // happy, frustrated, confused, urgent, etc.
            $table->decimal('confidence', 4, 3)->default(0.0);
            $table->jsonb('details')->default('{}'); // Additional analysis details
            $table->string('model_used');
            $table->timestamp('analyzed_at');
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id']);
            $table->index(['record_module', 'record_id']);
            $table->index(['category']);
            $table->index(['analyzed_at']);
        });

        Schema::create('sentiment_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('record_module');
            $table->unsignedBigInteger('record_id');
            $table->foreignId('sentiment_id')->constrained('sentiment_scores')->cascadeOnDelete();
            $table->string('alert_type'); // negative_detected, sentiment_drop, urgent_detected
            $table->text('message');
            $table->string('severity')->default('medium'); // low, medium, high
            $table->boolean('is_read')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['record_module', 'record_id', 'is_read']);
            $table->index(['is_read', 'created_at']);
        });

        // Aggregate sentiment per record
        Schema::create('sentiment_aggregates', function (Blueprint $table) {
            $table->id();
            $table->string('record_module');
            $table->unsignedBigInteger('record_id');
            $table->decimal('average_score', 4, 3)->default(0.0);
            $table->string('overall_sentiment')->default('neutral');
            $table->integer('positive_count')->default(0);
            $table->integer('neutral_count')->default(0);
            $table->integer('negative_count')->default(0);
            $table->decimal('trend', 4, 3)->default(0.0); // Change over time
            $table->timestamp('last_analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['record_module', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sentiment_aggregates');
        Schema::dropIfExists('sentiment_alerts');
        Schema::dropIfExists('sentiment_scores');
    }
};
