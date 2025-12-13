<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lookalike Audiences
        Schema::create('lookalike_audiences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // Source audience definition
            $table->string('source_type'); // saved_search, manual, segment
            $table->unsignedBigInteger('source_id')->nullable(); // ID of saved search or segment
            $table->jsonb('source_criteria')->default('{}'); // Manual criteria if not using saved source

            // Match configuration
            $table->jsonb('match_criteria')->default('{}'); // Which factors to match on
            $table->jsonb('weights')->default('{}'); // Weight for each criterion
            $table->decimal('min_similarity_score', 5, 2)->default(70.00); // 0-100 threshold
            $table->integer('size_limit')->nullable(); // Max audience size

            // Status
            $table->string('status')->default('draft'); // draft, building, ready, expired
            $table->timestamp('last_built_at')->nullable();
            $table->integer('build_duration_seconds')->nullable();
            $table->integer('source_count')->default(0); // Number of records in source
            $table->integer('match_count')->default(0); // Number of matches found

            // Refresh settings
            $table->boolean('auto_refresh')->default(false);
            $table->string('refresh_frequency')->nullable(); // daily, weekly, monthly
            $table->timestamp('next_refresh_at')->nullable();

            // Export settings
            $table->jsonb('export_destinations')->default('[]'); // Ad platform export configs
            $table->timestamp('last_exported_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Lookalike Matches - individual match records
        Schema::create('lookalike_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audience_id')->constrained('lookalike_audiences')->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id'); // ID of matched record
            $table->string('contact_module')->default('contacts'); // Module the contact is in

            // Scoring
            $table->decimal('similarity_score', 5, 2); // 0-100
            $table->jsonb('match_factors')->default('{}'); // Breakdown of score by criterion

            // Enrichment data
            $table->jsonb('enrichment_data')->default('{}'); // Additional data about match
            $table->timestamp('enriched_at')->nullable();

            // Export tracking
            $table->boolean('exported')->default(false);
            $table->timestamp('exported_at')->nullable();
            $table->string('export_destination')->nullable();

            $table->timestamps();

            $table->unique(['audience_id', 'contact_id', 'contact_module']);
            $table->index(['audience_id', 'similarity_score']);
        });

        // Audience Build Jobs - track building progress
        Schema::create('lookalike_build_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audience_id')->constrained('lookalike_audiences')->cascadeOnDelete();

            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('progress')->default(0); // 0-100
            $table->integer('records_processed')->default(0);
            $table->integer('matches_found')->default(0);

            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });

        // Audience Export Logs
        Schema::create('lookalike_export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audience_id')->constrained('lookalike_audiences')->cascadeOnDelete();

            $table->string('destination'); // google_ads, facebook, linkedin, csv
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('records_exported')->default(0);
            $table->jsonb('export_config')->default('{}');
            $table->text('error_message')->nullable();

            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookalike_export_logs');
        Schema::dropIfExists('lookalike_build_jobs');
        Schema::dropIfExists('lookalike_matches');
        Schema::dropIfExists('lookalike_audiences');
    }
};
