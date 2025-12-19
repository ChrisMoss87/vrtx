<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks each workflow execution (a single run of a workflow).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();

            // What triggered this execution
            $table->string('trigger_type'); // record_event, scheduled, manual, webhook
            $table->unsignedBigInteger('trigger_record_id')->nullable(); // The record that triggered it
            $table->string('trigger_record_type')->nullable(); // Polymorphic type

            // Execution status: pending, queued, running, completed, failed, cancelled
            $table->string('status')->default('pending');

            // Timing
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable();

            // Context data available to all steps
            $table->json('context_data')->nullable();

            // Results summary
            $table->integer('steps_completed')->default(0);
            $table->integer('steps_failed')->default(0);
            $table->integer('steps_skipped')->default(0);

            // Error tracking
            $table->text('error_message')->nullable();

            // Who triggered (for manual triggers)
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index(['workflow_id', 'status']);
            $table->index(['trigger_record_type', 'trigger_record_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_executions');
    }
};
