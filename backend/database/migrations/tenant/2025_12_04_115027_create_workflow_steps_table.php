<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Note: This migration was renamed from workflow_triggers to workflow_steps.
 * Triggers are stored in the workflow itself. Steps represent actions in sequence.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();

            // Step ordering
            $table->integer('order')->default(0);
            $table->string('name')->nullable();

            // Action type: send_email, create_record, update_record, delete_record,
            // webhook, assign_user, add_tag, remove_tag, send_notification, delay, condition_branch
            $table->string('action_type');
            $table->json('action_config'); // Action-specific configuration

            // Conditional execution within workflow
            $table->json('conditions')->nullable(); // Skip this step if conditions not met

            // For branching/parallel execution
            $table->string('branch_id')->nullable(); // Group steps into branches
            $table->boolean('is_parallel')->default(false); // Run in parallel with same-order steps

            // Error handling
            $table->boolean('continue_on_error')->default(false);
            $table->integer('retry_count')->default(0);
            $table->integer('retry_delay_seconds')->default(60);

            $table->timestamps();

            // Indexes
            $table->index(['workflow_id', 'order']);
            $table->index('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
