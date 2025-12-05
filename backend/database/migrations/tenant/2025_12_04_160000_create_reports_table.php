<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Report configuration
            $table->string('type')->default('table'); // table, chart, summary, matrix, pivot
            $table->string('chart_type')->nullable(); // bar, line, pie, doughnut, area, funnel, scatter, gauge, kpi
            $table->boolean('is_public')->default(false);
            $table->boolean('is_favorite')->default(false);

            // Query configuration
            $table->jsonb('config')->default('{}'); // Additional config options
            $table->jsonb('filters')->default('[]'); // Filter conditions
            $table->jsonb('grouping')->default('[]'); // Group by fields
            $table->jsonb('aggregations')->default('[]'); // Aggregation config
            $table->jsonb('sorting')->default('[]'); // Sort configuration
            $table->jsonb('date_range')->default('{}'); // Date range filter

            // Scheduling
            $table->jsonb('schedule')->nullable(); // Cron schedule, recipients, format

            // Caching
            $table->timestamp('last_run_at')->nullable();
            $table->jsonb('cached_result')->nullable();
            $table->timestamp('cache_expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'is_public']);
            $table->index(['module_id', 'type']);
            $table->index('is_favorite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
