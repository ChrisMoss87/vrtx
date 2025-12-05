<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create search history table for recent searches
        Schema::create('search_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query');
            $table->string('type')->nullable(); // 'global', 'module', etc.
            $table->string('module_api_name')->nullable();
            $table->json('filters')->nullable();
            $table->integer('results_count')->default(0);
            $table->timestamp('created_at');
        });

        // Create saved searches table
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('query');
            $table->string('type')->default('global');
            $table->string('module_api_name')->nullable();
            $table->json('filters')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->integer('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // Create search index table for faster full-text search
        Schema::create('search_index', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('module_api_name')->index();
            $table->foreignId('record_id')->constrained('module_records')->cascadeOnDelete();
            $table->text('searchable_content'); // Combined searchable fields
            $table->string('primary_value')->nullable(); // Primary display field value
            $table->string('secondary_value')->nullable(); // Secondary display field value
            $table->json('metadata')->nullable(); // Additional data for display
            $table->timestamp('indexed_at');

            $table->unique(['module_id', 'record_id']);
        });

        // Add full-text search index on PostgreSQL
        DB::statement('CREATE INDEX search_index_content_idx ON search_index USING gin(to_tsvector(\'english\', searchable_content))');
        DB::statement('CREATE INDEX search_index_primary_value_idx ON search_index USING gin(to_tsvector(\'english\', primary_value))');
    }

    public function down(): void
    {
        Schema::dropIfExists('search_index');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('search_history');
    }
};
