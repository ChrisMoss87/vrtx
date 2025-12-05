<?php

declare(strict_types=1);

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
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // Workflow configuration
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->boolean('is_active')->default(false);
            $table->integer('priority')->default(0); // Higher = runs first

            // Trigger type: record_created, record_updated, record_deleted, field_changed, time_based, webhook
            $table->string('trigger_type');
            $table->json('trigger_config')->nullable(); // Specific trigger configuration

            // Conditions for when workflow should run (JSON array of condition groups)
            $table->json('conditions')->nullable();

            // Execution settings
            $table->boolean('run_once_per_record')->default(false);
            $table->boolean('allow_manual_trigger')->default(true);
            $table->integer('delay_seconds')->default(0); // Delay before execution

            // Scheduling for time-based triggers
            $table->string('schedule_cron')->nullable(); // Cron expression
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();

            // Stats
            $table->unsignedBigInteger('execution_count')->default(0);
            $table->unsignedBigInteger('success_count')->default(0);
            $table->unsignedBigInteger('failure_count')->default(0);

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['module_id', 'is_active']);
            $table->index(['trigger_type', 'is_active']);
            $table->index('next_run_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
