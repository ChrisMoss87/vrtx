<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quota Periods
        Schema::create('quota_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('period_type', 20); // 'month', 'quarter', 'year', 'custom'
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
            $table->index('is_active');
        });

        // Quotas
        Schema::create('quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('quota_periods')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('team_id')->nullable(); // For future team support
            $table->string('metric_type', 50); // 'revenue', 'deals', 'leads', 'calls', 'meetings', 'activities', 'custom'
            $table->string('metric_field', 100)->nullable(); // For custom metrics: field API name
            $table->string('module_api_name', 100)->nullable(); // For module-specific quotas
            $table->decimal('target_value', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('current_value', 15, 2)->default(0);
            $table->decimal('attainment_percent', 8, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period_id', 'user_id']);
            $table->index('metric_type');
            $table->unique(['period_id', 'user_id', 'metric_type', 'metric_field'], 'unique_user_period_metric');
        });

        // Quota Snapshots (for historical tracking)
        Schema::create('quota_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quota_id')->constrained()->onDelete('cascade');
            $table->date('snapshot_date');
            $table->decimal('current_value', 15, 2);
            $table->decimal('attainment_percent', 8, 2);
            $table->timestamps();

            $table->unique(['quota_id', 'snapshot_date']);
            $table->index('snapshot_date');
        });

        // Goals
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('goal_type', 20); // 'individual', 'team', 'company'
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('metric_type', 50); // 'revenue', 'deals', 'leads', 'custom'
            $table->string('metric_field', 100)->nullable();
            $table->string('module_api_name', 100)->nullable();
            $table->decimal('target_value', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('current_value', 15, 2)->default(0);
            $table->decimal('attainment_percent', 8, 2)->default(0);
            $table->string('status', 20)->default('in_progress'); // 'in_progress', 'achieved', 'missed', 'paused'
            $table->timestamp('achieved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['goal_type', 'user_id']);
            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });

        // Goal Milestones
        Schema::create('goal_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('target_value', 15, 2);
            $table->date('target_date')->nullable();
            $table->boolean('is_achieved')->default(false);
            $table->timestamp('achieved_at')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['goal_id', 'display_order']);
        });

        // Goal Progress History
        Schema::create('goal_progress_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->onDelete('cascade');
            $table->date('log_date');
            $table->decimal('value', 15, 2);
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->string('change_source', 100)->nullable(); // 'deal_closed', 'lead_created', 'activity_completed', etc.
            $table->unsignedBigInteger('source_record_id')->nullable();
            $table->timestamps();

            $table->index(['goal_id', 'log_date']);
        });

        // Leaderboard Cache (for performance)
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('quota_periods')->onDelete('cascade');
            $table->string('metric_type', 50);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('rank');
            $table->decimal('value', 15, 2);
            $table->decimal('target', 15, 2);
            $table->decimal('attainment_percent', 8, 2);
            $table->decimal('trend', 8, 2)->default(0); // Week-over-week change
            $table->timestamps();

            $table->unique(['period_id', 'metric_type', 'user_id']);
            $table->index(['period_id', 'metric_type', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
        Schema::dropIfExists('goal_progress_logs');
        Schema::dropIfExists('goal_milestones');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('quota_snapshots');
        Schema::dropIfExists('quotas');
        Schema::dropIfExists('quota_periods');
    }
};
