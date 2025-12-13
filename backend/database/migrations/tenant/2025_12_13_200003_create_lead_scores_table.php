<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scoring models configuration
        Schema::create('scoring_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('target_module')->default('leads'); // Which module to score
            $table->string('status')->default('draft'); // draft, training, active, archived
            $table->jsonb('features')->default('[]'); // Feature definitions
            $table->jsonb('weights')->default('{}'); // Trained weights or rule weights
            $table->string('model_type')->default('rule_based'); // rule_based, ml
            $table->decimal('accuracy', 5, 2)->nullable();
            $table->integer('training_records')->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['target_module', 'status']);
        });

        // Individual scoring factors
        Schema::create('scoring_factors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('scoring_models')->cascadeOnDelete();
            $table->string('name');
            $table->string('category'); // demographic, behavioral, engagement, firmographic
            $table->string('factor_type'); // field_value, activity_count, recency, custom
            $table->jsonb('config')->default('{}'); // Factor-specific configuration
            $table->integer('weight')->default(10); // Points contribution
            $table->integer('max_points')->default(10);
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['model_id', 'is_active']);
        });

        // Calculated lead scores
        Schema::create('lead_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('scoring_models')->cascadeOnDelete();
            $table->string('record_module');
            $table->unsignedBigInteger('record_id');
            $table->integer('score')->default(0); // 0-100
            $table->string('grade')->nullable(); // A, B, C, D, F
            $table->jsonb('factor_breakdown')->default('{}'); // Points per factor
            $table->jsonb('explanation')->default('[]'); // Human-readable explanations
            $table->decimal('conversion_probability', 5, 2)->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['model_id', 'record_module', 'record_id']);
            $table->index(['record_module', 'record_id']);
            $table->index(['score']);
            $table->index(['grade']);
        });

        // Score history for trending
        Schema::create('lead_score_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_score_id')->constrained('lead_scores')->cascadeOnDelete();
            $table->integer('score');
            $table->string('grade')->nullable();
            $table->string('change_reason')->nullable();
            $table->timestamps();

            $table->index(['lead_score_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_score_history');
        Schema::dropIfExists('lead_scores');
        Schema::dropIfExists('scoring_factors');
        Schema::dropIfExists('scoring_models');
    }
};
