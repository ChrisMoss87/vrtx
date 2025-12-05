<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks individual step executions within a workflow run.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_step_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('workflow_executions')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('workflow_steps')->cascadeOnDelete();

            // Execution status: pending, running, completed, failed, skipped
            $table->string('status')->default('pending');

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable();

            // Input/output for debugging
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();

            // Error tracking
            $table->text('error_message')->nullable();
            $table->text('error_trace')->nullable();
            $table->integer('retry_attempt')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['execution_id', 'step_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_step_logs');
    }
};
