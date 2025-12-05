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
        // Core blueprints table
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->foreignId('field_id')->constrained()->onDelete('cascade');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('layout_data')->nullable(); // Node positions for visual editor
            $table->timestamps();

            $table->unique(['module_id', 'field_id']); // One blueprint per field
        });

        // Blueprint states (possible values)
        Schema::create('blueprint_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('field_option_value')->nullable(); // Links to field option value
            $table->string('color', 7)->nullable();
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->integer('position_x')->nullable(); // Canvas position
            $table->integer('position_y')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        // Blueprint transitions (allowed state changes)
        Schema::create('blueprint_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_state_id')->nullable()->constrained('blueprint_states')->onDelete('cascade');
            $table->foreignId('to_state_id')->constrained('blueprint_states')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('button_label', 100)->nullable(); // Custom button text
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Before-phase: Conditions that must be true to start transition
        Schema::create('blueprint_transition_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_id')->constrained('blueprint_transitions')->onDelete('cascade');
            $table->foreignId('field_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('operator', 50); // eq, ne, gt, lt, contains, etc.
            $table->text('value')->nullable();
            $table->string('logical_group', 50)->default('AND'); // For grouping conditions
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // During-phase: Requirements user must provide
        Schema::create('blueprint_transition_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_id')->constrained('blueprint_transitions')->onDelete('cascade');
            $table->string('type', 50); // mandatory_field, attachment, note, checklist
            $table->foreignId('field_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->jsonb('config')->nullable(); // Type-specific config (e.g., checklist items)
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // After-phase: Actions to execute after transition
        Schema::create('blueprint_transition_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_id')->constrained('blueprint_transitions')->onDelete('cascade');
            $table->string('type', 50); // send_email, update_field, create_task, webhook, etc.
            $table->jsonb('config'); // Action-specific configuration
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Approval configuration per transition
        Schema::create('blueprint_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_id')->constrained('blueprint_transitions')->onDelete('cascade');
            $table->string('approval_type', 50); // specific_users, role_based, manager, field_value
            $table->jsonb('config'); // Approver IDs, role names, etc.
            $table->boolean('require_all')->default(false); // All must approve vs any one
            $table->integer('auto_reject_days')->nullable(); // Auto-reject after N days
            $table->boolean('notify_on_pending')->default(true);
            $table->boolean('notify_on_complete')->default(true);
            $table->timestamps();
        });

        // SLA configuration per state
        Schema::create('blueprint_slas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->onDelete('cascade');
            $table->foreignId('state_id')->constrained('blueprint_states')->onDelete('cascade');
            $table->string('name');
            $table->integer('duration_hours');
            $table->boolean('business_hours_only')->default(false);
            $table->boolean('exclude_weekends')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // SLA escalations (what happens when SLA approaches/breaches)
        Schema::create('blueprint_sla_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_id')->constrained('blueprint_slas')->onDelete('cascade');
            $table->string('trigger_type', 50); // approaching, breached
            $table->integer('trigger_value')->nullable(); // e.g., 80 for 80% of time elapsed
            $table->string('action_type', 50); // send_email, update_field, create_task, notify_user
            $table->jsonb('config');
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // Runtime: Current state per record
        Schema::create('blueprint_record_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('record_id');
            $table->foreignId('current_state_id')->constrained('blueprint_states')->onDelete('cascade');
            $table->timestamp('state_entered_at')->nullable();
            $table->timestamps();

            $table->unique(['blueprint_id', 'record_id']);
        });

        // Runtime: Transition executions (history)
        Schema::create('blueprint_transition_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_id')->constrained('blueprint_transitions')->onDelete('cascade');
            $table->unsignedBigInteger('record_id');
            $table->foreignId('from_state_id')->nullable()->constrained('blueprint_states')->onDelete('set null');
            $table->foreignId('to_state_id')->constrained('blueprint_states')->onDelete('cascade');
            $table->foreignId('executed_by')->constrained('users')->onDelete('cascade');
            $table->string('status', 50)->default('pending'); // pending_requirements, pending_approval, completed, failed
            $table->jsonb('requirements_data')->nullable(); // User-provided data
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // Runtime: Approval requests
        Schema::create('blueprint_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('blueprint_approvals')->onDelete('cascade');
            $table->unsignedBigInteger('record_id');
            $table->foreignId('execution_id')->constrained('blueprint_transition_executions')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->string('status', 50)->default('pending'); // pending, approved, rejected, expired
            $table->foreignId('responded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('responded_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        // Runtime: SLA instances (tracking per record)
        Schema::create('blueprint_sla_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_id')->constrained('blueprint_slas')->onDelete('cascade');
            $table->unsignedBigInteger('record_id');
            $table->timestamp('state_entered_at');
            $table->timestamp('due_at');
            $table->string('status', 50)->default('active'); // active, completed, breached
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Runtime: Action execution logs
        Schema::create('blueprint_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('blueprint_transition_executions')->onDelete('cascade');
            $table->foreignId('action_id')->constrained('blueprint_transition_actions')->onDelete('cascade');
            $table->string('status', 50); // success, failed
            $table->jsonb('result')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();
        });

        // Runtime: SLA escalation logs (track which escalations fired)
        Schema::create('blueprint_sla_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_instance_id')->constrained('blueprint_sla_instances')->onDelete('cascade');
            $table->foreignId('escalation_id')->constrained('blueprint_sla_escalations')->onDelete('cascade');
            $table->timestamp('executed_at');
            $table->string('status', 50); // success, failed
            $table->jsonb('result')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprint_sla_escalation_logs');
        Schema::dropIfExists('blueprint_action_logs');
        Schema::dropIfExists('blueprint_sla_instances');
        Schema::dropIfExists('blueprint_approval_requests');
        Schema::dropIfExists('blueprint_transition_executions');
        Schema::dropIfExists('blueprint_record_states');
        Schema::dropIfExists('blueprint_sla_escalations');
        Schema::dropIfExists('blueprint_slas');
        Schema::dropIfExists('blueprint_approvals');
        Schema::dropIfExists('blueprint_transition_actions');
        Schema::dropIfExists('blueprint_transition_requirements');
        Schema::dropIfExists('blueprint_transition_conditions');
        Schema::dropIfExists('blueprint_transitions');
        Schema::dropIfExists('blueprint_states');
        Schema::dropIfExists('blueprints');
    }
};
