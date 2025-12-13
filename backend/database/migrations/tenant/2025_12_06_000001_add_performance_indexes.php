<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing performance indexes identified during optimization analysis.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Module records - compound indexes for common query patterns
        Schema::table('module_records', function (Blueprint $table) {
            // Index for filtering by creator
            $table->index(['module_id', 'created_by'], 'idx_records_module_creator');

            // Index for sorting by updated_at (recent records)
            $table->index(['module_id', 'updated_at'], 'idx_records_module_updated');

            // Index for sorting by created_at
            $table->index(['module_id', 'created_at'], 'idx_records_module_created');
        });

        // Activities - polymorphic index for subject lookups
        Schema::table('activities', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id'], 'idx_activities_subject');
            $table->index(['related_type', 'related_id'], 'idx_activities_related');
            $table->index(['user_id', 'created_at'], 'idx_activities_user_date');
            $table->index(['scheduled_at', 'completed_at'], 'idx_activities_schedule');
        });

        // Audit logs - polymorphic index for auditable lookups
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_auditable');
            $table->index(['user_id', 'created_at'], 'idx_audit_user_date');
            $table->index('batch_id', 'idx_audit_batch');
        });

        // Email messages - thread and account queries
        // Note: thread_id already has an index from the create migration
        Schema::table('email_messages', function (Blueprint $table) {
            $table->index(['account_id', 'status'], 'idx_emails_account_status');
            $table->index(['account_id', 'folder'], 'idx_emails_account_folder');
            $table->index(['account_id', 'is_read'], 'idx_emails_account_read');
        });

        // Workflow executions - already has workflow_id+status index from migration
        // Only add trigger_record index if useful
        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->index(['trigger_record_type', 'trigger_record_id', 'status'], 'idx_wf_exec_trigger_status');
        });

        // Blueprint record states - record lookups
        Schema::table('blueprint_record_states', function (Blueprint $table) {
            // record_id is the module_record_id, no record_type column
            $table->index(['blueprint_id', 'record_id'], 'idx_bp_record');
            $table->index(['blueprint_id', 'current_state_id'], 'idx_bp_current_state');
        });

        // Note: stages already has pipeline_id+display_order index from create migration

        // Stage history - record history lookups
        Schema::table('stage_history', function (Blueprint $table) {
            $table->index(['module_record_id', 'created_at'], 'idx_stage_history_record');
        });

        // Reports - access patterns
        Schema::table('reports', function (Blueprint $table) {
            $table->index(['user_id', 'is_public'], 'idx_reports_access');
            $table->index(['module_id', 'type'], 'idx_reports_module_type');
        });

        // Dashboards - access patterns
        Schema::table('dashboards', function (Blueprint $table) {
            $table->index(['user_id', 'is_public'], 'idx_dashboards_access');
            $table->index(['user_id', 'is_default'], 'idx_dashboards_default');
        });

        // Imports - status queries
        Schema::table('imports', function (Blueprint $table) {
            $table->index(['module_id', 'status'], 'idx_imports_module_status');
            $table->index(['user_id', 'created_at'], 'idx_imports_user_date');
        });

        // API keys - lookup and validation
        Schema::table('api_keys', function (Blueprint $table) {
            $table->index(['user_id', 'is_active'], 'idx_apikeys_user_active');
        });

        // Webhooks - event triggers
        Schema::table('webhooks', function (Blueprint $table) {
            $table->index(['module_id', 'is_active'], 'idx_webhooks_module_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_records', function (Blueprint $table) {
            $table->dropIndex('idx_records_module_creator');
            $table->dropIndex('idx_records_module_updated');
            $table->dropIndex('idx_records_module_created');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('idx_activities_subject');
            $table->dropIndex('idx_activities_related');
            $table->dropIndex('idx_activities_user_date');
            $table->dropIndex('idx_activities_schedule');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_auditable');
            $table->dropIndex('idx_audit_user_date');
            $table->dropIndex('idx_audit_batch');
        });

        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropIndex('idx_emails_account_status');
            $table->dropIndex('idx_emails_account_folder');
            $table->dropIndex('idx_emails_account_read');
        });

        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->dropIndex('idx_wf_exec_trigger_status');
        });

        Schema::table('blueprint_record_states', function (Blueprint $table) {
            $table->dropIndex('idx_bp_record');
            $table->dropIndex('idx_bp_current_state');
        });

        Schema::table('stage_history', function (Blueprint $table) {
            $table->dropIndex('idx_stage_history_record');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('idx_reports_access');
            $table->dropIndex('idx_reports_module_type');
        });

        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropIndex('idx_dashboards_access');
            $table->dropIndex('idx_dashboards_default');
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->dropIndex('idx_imports_module_status');
            $table->dropIndex('idx_imports_user_date');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropIndex('idx_apikeys_user_active');
        });

        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropIndex('idx_webhooks_module_active');
        });
    }
};
