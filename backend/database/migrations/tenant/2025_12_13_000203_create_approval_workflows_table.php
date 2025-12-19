<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Approval Rules
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('entity_type'); // quote, proposal, discount, contract, expense, custom
            $table->unsignedBigInteger('module_id')->nullable(); // For module-specific approvals
            $table->json('conditions')->nullable(); // When to trigger: {"field": "discount_percent", "operator": ">", "value": 20}
            $table->json('approver_chain')->nullable(); // Array of approval steps
            $table->string('approval_type')->default('sequential'); // sequential, parallel, any
            $table->boolean('allow_self_approval')->default(false);
            $table->boolean('require_comments')->default(false);
            $table->unsignedInteger('sla_hours')->nullable(); // Hours to respond
            $table->json('escalation_rules')->nullable(); // What to do on timeout
            $table->json('notification_settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0); // Rule evaluation order
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_type', 'is_active']);
            $table->index('module_id');
        });

        // Approval Requests
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('rule_id')->nullable()->constrained('approval_rules')->nullOnDelete();
            $table->string('entity_type'); // quote, proposal, discount, etc.
            $table->unsignedBigInteger('entity_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, approved, rejected, cancelled, expired
            $table->json('snapshot_data')->nullable(); // Snapshot of entity at request time
            $table->decimal('value', 15, 2)->nullable(); // For tracking approval thresholds
            $table->string('currency')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('final_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('final_comments')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('status');
            $table->index('requested_by');
            $table->index('expires_at');
        });

        // Approval Steps
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('role_id')->nullable(); // Approve by role
            $table->string('approver_type')->default('user'); // user, role, manager, custom
            $table->unsignedInteger('step_order')->default(1);
            $table->string('status')->default('pending'); // pending, approved, rejected, skipped, delegated
            $table->text('comments')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->foreignId('delegated_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['request_id', 'step_order']);
            $table->index(['approver_id', 'status']);
            $table->index('is_current');
        });

        // Approval Delegations
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->string('delegation_type')->default('all'); // all, specific_rules
            $table->json('rule_ids')->nullable(); // Specific rules to delegate
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['delegator_id', 'is_active']);
            $table->index(['delegate_id', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });

        // Approval History Log
        Schema::create('approval_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('approval_steps')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // submitted, approved, rejected, delegated, escalated, commented, recalled
            $table->text('comments')->nullable();
            $table->json('changes')->nullable(); // Any field changes
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at');

            $table->index(['request_id', 'created_at']);
            $table->index('action');
        });

        // Approval Notifications Queue
        Schema::create('approval_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('approval_steps')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('notification_type'); // pending, reminder, escalation, completed
            $table->string('channel')->default('email'); // email, in_app, push
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('user_id');
        });

        // Approval Quick Actions (saved responses)
        Schema::create('approval_quick_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('action_type'); // approve, reject
            $table->text('default_comment')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_quick_actions');
        Schema::dropIfExists('approval_notifications');
        Schema::dropIfExists('approval_history');
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_rules');
    }
};
