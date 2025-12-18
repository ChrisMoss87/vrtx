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
        // Approval delegations - users can delegate approval authority to others
        if (Schema::hasTable('approval_delegations')) {
            return; // Already exists
        }

        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('delegate_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable(); // Null = indefinite
            $table->string('reason')->nullable(); // e.g., "Vacation", "Leave of absence"
            $table->boolean('is_active')->default(true);
            $table->boolean('notify_delegator')->default(true); // Notify original approver when delegate acts
            $table->jsonb('scope')->nullable(); // Optional: limit to specific modules/blueprints
            $table->timestamps();

            $table->index(['delegator_id', 'is_active']);
            $table->index(['delegate_id', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });

        // Add escalation configuration to blueprint_approvals
        Schema::table('blueprint_approvals', function (Blueprint $table) {
            $table->integer('escalation_hours')->nullable()->after('auto_reject_days'); // Hours before escalation
            $table->string('escalation_type')->nullable()->after('escalation_hours'); // manager, specific_user, role
            $table->jsonb('escalation_config')->nullable()->after('escalation_type'); // Target user/role for escalation
            $table->integer('reminder_hours')->nullable()->after('escalation_config'); // Send reminder after N hours
            $table->integer('max_reminders')->default(3)->after('reminder_hours'); // Max reminders to send
        });

        // Track approval escalations
        Schema::create('approval_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('blueprint_approval_requests')->onDelete('cascade');
            $table->string('escalation_type'); // reminder, escalate, auto_reject
            $table->foreignId('from_user_id')->nullable()->constrained('users')->onDelete('set null'); // Original approver
            $table->foreignId('to_user_id')->nullable()->constrained('users')->onDelete('set null'); // Escalated to
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('approval_request_id');
        });

        // Add fields to track delegation and escalation on approval requests
        Schema::table('blueprint_approval_requests', function (Blueprint $table) {
            $table->foreignId('original_approver_id')->nullable()->after('requested_by')->constrained('users')->onDelete('set null');
            $table->foreignId('delegation_id')->nullable()->after('original_approver_id')->constrained('approval_delegations')->onDelete('set null');
            $table->integer('reminder_count')->default(0)->after('comments');
            $table->timestamp('last_reminder_at')->nullable()->after('reminder_count');
            $table->timestamp('escalated_at')->nullable()->after('last_reminder_at');
            $table->foreignId('escalated_from_id')->nullable()->after('escalated_at')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blueprint_approval_requests', function (Blueprint $table) {
            $table->dropForeign(['original_approver_id']);
            $table->dropForeign(['delegation_id']);
            $table->dropForeign(['escalated_from_id']);
            $table->dropColumn([
                'original_approver_id',
                'delegation_id',
                'reminder_count',
                'last_reminder_at',
                'escalated_at',
                'escalated_from_id',
            ]);
        });

        Schema::dropIfExists('approval_escalation_logs');

        Schema::table('blueprint_approvals', function (Blueprint $table) {
            $table->dropColumn([
                'escalation_hours',
                'escalation_type',
                'escalation_config',
                'reminder_hours',
                'max_reminders',
            ]);
        });

        Schema::dropIfExists('approval_delegations');
    }
};
