<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Playbook templates
        Schema::create('playbooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('trigger_module')->nullable(); // e.g., 'deals', 'accounts'
            $table->string('trigger_condition')->nullable(); // e.g., 'stage_change', 'created'
            $table->json('trigger_config')->nullable(); // Additional trigger configuration
            $table->integer('estimated_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_assign')->default(false);
            $table->foreignId('default_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->integer('display_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Playbook phases/sections
        Schema::create('playbook_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playbook_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('target_days')->nullable(); // Days from start
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // Playbook tasks (within phases)
        Schema::create('playbook_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained('playbook_phases')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('task_type')->default('manual'); // manual, automated, milestone
            $table->json('task_config')->nullable(); // For automated tasks
            $table->integer('due_days')->nullable(); // Days from playbook start
            $table->integer('duration_estimate')->nullable(); // Minutes
            $table->boolean('is_required')->default(false);
            $table->boolean('is_milestone')->default(false);
            $table->string('assignee_type')->default('owner'); // owner, specific_user, role
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignee_role')->nullable();
            $table->json('dependencies')->nullable(); // Array of task IDs that must complete first
            $table->json('checklist')->nullable(); // Sub-items for the task
            $table->json('resources')->nullable(); // Links, documents, etc.
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // Playbook instances (active playbooks for specific records)
        Schema::create('playbook_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playbook_id')->constrained()->cascadeOnDelete();
            $table->string('related_module'); // e.g., 'accounts', 'deals'
            $table->unsignedBigInteger('related_id');
            $table->string('status')->default('active'); // active, paused, completed, cancelled
            $table->timestamp('started_at');
            $table->timestamp('target_completion_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('progress_percent')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['related_module', 'related_id']);
        });

        // Task instances (active tasks for playbook instances)
        Schema::create('playbook_task_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('playbook_instances')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('playbook_tasks')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, in_progress, completed, skipped, blocked
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('checklist_status')->nullable(); // Status of each checklist item
            $table->integer('time_spent')->nullable(); // Minutes
            $table->timestamps();

            $table->index(['instance_id', 'status']);
        });

        // Playbook activity log
        Schema::create('playbook_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('playbook_instances')->cascadeOnDelete();
            $table->foreignId('task_instance_id')->nullable()->constrained('playbook_task_instances')->nullOnDelete();
            $table->string('action'); // task_completed, task_started, playbook_started, etc.
            $table->json('details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('instance_id');
        });

        // Playbook metrics/goals
        Schema::create('playbook_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playbook_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('metric_type'); // field_value, task_completion, time_to_complete
            $table->string('target_module')->nullable();
            $table->string('target_field')->nullable();
            $table->string('comparison_operator')->default('>='); // >=, <=, =, >, <
            $table->decimal('target_value', 15, 2)->nullable();
            $table->integer('target_days')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Playbook goal tracking per instance
        Schema::create('playbook_goal_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('playbook_instances')->cascadeOnDelete();
            $table->foreignId('goal_id')->constrained('playbook_goals')->cascadeOnDelete();
            $table->decimal('actual_value', 15, 2)->nullable();
            $table->boolean('achieved')->default(false);
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();
        });

        // Playbook templates library (shareable templates)
        Schema::create('playbook_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playbook_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->boolean('is_public')->default(false);
            $table->integer('usage_count')->default(0);
            $table->decimal('avg_rating', 3, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playbook_templates');
        Schema::dropIfExists('playbook_goal_results');
        Schema::dropIfExists('playbook_goals');
        Schema::dropIfExists('playbook_activities');
        Schema::dropIfExists('playbook_task_instances');
        Schema::dropIfExists('playbook_instances');
        Schema::dropIfExists('playbook_tasks');
        Schema::dropIfExists('playbook_phases');
        Schema::dropIfExists('playbooks');
    }
};
