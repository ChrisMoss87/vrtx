<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('openai'); // openai, anthropic, azure
            $table->text('api_key')->nullable(); // Encrypted
            $table->string('model')->default('gpt-4o-mini');
            $table->jsonb('settings')->default('{}'); // Provider-specific settings
            $table->integer('max_tokens')->default(4096);
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->boolean('is_enabled')->default(false);
            $table->integer('monthly_budget_cents')->nullable(); // Cost limit
            $table->integer('monthly_usage_cents')->default(0);
            $table->timestamp('budget_reset_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feature'); // email_compose, lead_scoring, sentiment, etc.
            $table->string('model');
            $table->integer('input_tokens');
            $table->integer('output_tokens');
            $table->integer('cost_cents')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->index(['feature', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category'); // email, scoring, sentiment, summary
            $table->text('system_prompt');
            $table->text('user_prompt_template');
            $table->jsonb('variables')->default('[]'); // Available template variables
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_settings');
    }
};
