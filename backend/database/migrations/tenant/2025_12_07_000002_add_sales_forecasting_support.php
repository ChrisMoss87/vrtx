<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add sales forecasting support.
 *
 * This migration adds:
 * - forecast_category and forecast_override to module_records
 * - forecast_snapshots table for historical tracking
 * - sales_quotas table for targets
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add forecast fields to module records
        Schema::table('module_records', function (Blueprint $table) {
            // Forecast category: commit, best_case, pipeline, omitted
            $table->string('forecast_category', 20)->nullable()->after('last_activity_at');
            // Manual override for forecast amount
            $table->decimal('forecast_override', 15, 2)->nullable()->after('forecast_category');
            // Expected close date for forecasting
            $table->date('expected_close_date')->nullable()->after('forecast_override');

            $table->index('forecast_category', 'idx_records_forecast_category');
            $table->index('expected_close_date', 'idx_records_close_date');
        });

        // Create forecast snapshots table for historical tracking
        Schema::create('forecast_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pipeline_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period_type', 20); // week, month, quarter, year
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('commit_amount', 15, 2)->default(0);
            $table->decimal('best_case_amount', 15, 2)->default(0);
            $table->decimal('pipeline_amount', 15, 2)->default(0);
            $table->decimal('weighted_amount', 15, 2)->default(0);
            $table->decimal('closed_won_amount', 15, 2)->default(0);
            $table->integer('deal_count')->default(0);
            $table->date('snapshot_date');
            $table->json('metadata')->nullable(); // Additional breakdown data
            $table->timestamps();

            // Unique constraint for one snapshot per user/pipeline/period/date
            $table->unique(
                ['user_id', 'pipeline_id', 'period_type', 'period_start', 'snapshot_date'],
                'idx_forecast_snapshot_unique'
            );
            $table->index(['period_type', 'period_start', 'period_end'], 'idx_forecast_period');
            $table->index('snapshot_date', 'idx_forecast_snapshot_date');
        });

        // Create sales quotas table
        Schema::create('sales_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('team_id')->nullable(); // For team-level quotas
            $table->string('period_type', 20); // month, quarter, year
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('quota_amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique constraint for one quota per user/team/pipeline/period
            $table->unique(
                ['user_id', 'team_id', 'pipeline_id', 'period_type', 'period_start'],
                'idx_quota_unique'
            );
            $table->index(['period_type', 'period_start', 'period_end'], 'idx_quota_period');
        });

        // Create forecast adjustments table for manual corrections
        Schema::create('forecast_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_record_id')->constrained()->cascadeOnDelete();
            $table->string('adjustment_type', 30); // category_change, amount_override, close_date_change
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['module_record_id', 'created_at'], 'idx_adjustment_record');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_adjustments');
        Schema::dropIfExists('sales_quotas');
        Schema::dropIfExists('forecast_snapshots');

        Schema::table('module_records', function (Blueprint $table) {
            $table->dropIndex('idx_records_forecast_category');
            $table->dropIndex('idx_records_close_date');
            $table->dropColumn(['forecast_category', 'forecast_override', 'expected_close_date']);
        });
    }
};
