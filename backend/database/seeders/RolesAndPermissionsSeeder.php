<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * All system permissions grouped by category.
     *
     * CORE FEATURES: Always available
     * ADVANCED FEATURES: Require higher tier plans
     * PLUGIN FEATURES: Require separate plugin license
     */
    public const PERMISSIONS = [
        // ========================================
        // CORE CRM PERMISSIONS
        // ========================================

        // Module management (records, fields, layouts)
        'modules.view',
        'modules.create',
        'modules.edit',
        'modules.delete',

        // Pipeline management
        'pipelines.view',
        'pipelines.create',
        'pipelines.edit',
        'pipelines.delete',

        // Dashboard management
        'dashboards.view',
        'dashboards.create',
        'dashboards.edit',
        'dashboards.delete',

        // Report management
        'reports.view',
        'reports.create',
        'reports.edit',
        'reports.delete',

        // User management
        'users.view',
        'users.create',
        'users.edit',
        'users.delete',

        // Role management
        'roles.view',
        'roles.create',
        'roles.edit',
        'roles.delete',

        // Settings
        'settings.view',
        'settings.edit',

        // Email templates
        'email_templates.view',
        'email_templates.create',
        'email_templates.edit',
        'email_templates.delete',

        // Import/Export
        'data.import',
        'data.export',

        // Activity logs / Audit
        'activity.view',
        'audit_logs.view',

        // ========================================
        // ADVANCED FEATURES (Higher Tiers)
        // ========================================

        // Blueprints (workflows with SLAs)
        'blueprints.view',
        'blueprints.create',
        'blueprints.edit',
        'blueprints.delete',

        // Workflows (automation)
        'workflows.view',
        'workflows.create',
        'workflows.edit',
        'workflows.delete',

        // Approval rules
        'approvals.view',
        'approvals.create',
        'approvals.edit',
        'approvals.delete',

        // API Keys
        'api_keys.view',
        'api_keys.create',
        'api_keys.edit',
        'api_keys.delete',

        // Webhooks
        'webhooks.view',
        'webhooks.create',
        'webhooks.edit',
        'webhooks.delete',

        // Forecasting
        'forecasts.view',
        'forecasts.create',
        'forecasts.edit',
        'forecasts.delete',

        // Quotas & Goals
        'quotas.view',
        'quotas.create',
        'quotas.edit',
        'quotas.delete',

        // Email Integration
        'email.view',
        'email.send',
        'email.sync',

        // Calendar / Meetings
        'meetings.view',
        'meetings.create',
        'meetings.edit',
        'meetings.delete',

        // Playbooks
        'playbooks.view',
        'playbooks.create',
        'playbooks.edit',
        'playbooks.delete',

        // Cadences / Sequences
        'cadences.view',
        'cadences.create',
        'cadences.edit',
        'cadences.delete',

        // Campaigns
        'campaigns.view',
        'campaigns.create',
        'campaigns.edit',
        'campaigns.delete',

        // ========================================
        // ENTERPRISE / PLUGIN FEATURES
        // ========================================

        // Portal management
        'portal.view',
        'portal.manage',

        // Documents & E-Signatures
        'documents.view',
        'documents.create',
        'documents.edit',
        'documents.delete',
        'signatures.view',
        'signatures.create',
        'signatures.manage',

        // Proposals
        'proposals.view',
        'proposals.create',
        'proposals.edit',
        'proposals.delete',

        // Deal Rooms
        'deal_rooms.view',
        'deal_rooms.create',
        'deal_rooms.edit',
        'deal_rooms.delete',

        // AI Features
        'ai.view',
        'ai.use',
        'ai.configure',

        // Competitor Intelligence
        'competitors.view',
        'competitors.create',
        'competitors.edit',
        'competitors.delete',

        // Knowledge Base
        'knowledge_base.view',
        'knowledge_base.create',
        'knowledge_base.edit',
        'knowledge_base.delete',

        // Landing Pages & Web Forms
        'landing_pages.view',
        'landing_pages.create',
        'landing_pages.edit',
        'landing_pages.delete',
        'web_forms.view',
        'web_forms.create',
        'web_forms.edit',
        'web_forms.delete',

        // A/B Testing
        'ab_tests.view',
        'ab_tests.create',
        'ab_tests.edit',
        'ab_tests.delete',

        // CMS
        'cms.view',
        'cms.create',
        'cms.edit',
        'cms.delete',
        'cms.publish',

        // Billing / Invoicing
        'billing.view',
        'billing.create',
        'billing.edit',
        'billing.delete',

        // Support / Ticketing
        'support.view',
        'support.create',
        'support.edit',
        'support.delete',

        // Live Chat
        'live_chat.view',
        'live_chat.manage',

        // Integrations
        'integrations.view',
        'integrations.manage',

        // Video / Recordings
        'recordings.view',
        'recordings.create',
        'recordings.delete',
    ];

    /**
     * Default role definitions.
     */
    public const ROLES = [
        'admin' => '*', // All permissions
        'manager' => [
            // Core CRM
            'modules.view',
            'modules.create',
            'modules.edit',
            'pipelines.view',
            'pipelines.create',
            'pipelines.edit',
            'dashboards.view',
            'dashboards.create',
            'dashboards.edit',
            'dashboards.delete',
            'reports.view',
            'reports.create',
            'reports.edit',
            'reports.delete',
            'users.view',
            'email_templates.view',
            'email_templates.create',
            'email_templates.edit',
            'data.import',
            'data.export',
            'activity.view',
            'audit_logs.view',
            // Advanced
            'blueprints.view',
            'blueprints.create',
            'blueprints.edit',
            'workflows.view',
            'workflows.create',
            'workflows.edit',
            'approvals.view',
            'approvals.create',
            'approvals.edit',
            'forecasts.view',
            'forecasts.create',
            'forecasts.edit',
            'quotas.view',
            'quotas.create',
            'quotas.edit',
            'email.view',
            'email.send',
            'meetings.view',
            'meetings.create',
            'meetings.edit',
            'playbooks.view',
            'playbooks.create',
            'playbooks.edit',
            'cadences.view',
            'cadences.create',
            'cadences.edit',
            'campaigns.view',
            'campaigns.create',
            'campaigns.edit',
            // Enterprise (view only for managers)
            'portal.view',
            'documents.view',
            'documents.create',
            'proposals.view',
            'proposals.create',
            'deal_rooms.view',
            'deal_rooms.create',
            'ai.view',
            'ai.use',
            'competitors.view',
            'competitors.create',
            'billing.view',
            'billing.create',
            'recordings.view',
        ],
        'sales_rep' => [
            // Core CRM
            'modules.view',
            'pipelines.view',
            'dashboards.view',
            'dashboards.create',
            'dashboards.edit',
            'reports.view',
            'reports.create',
            'email_templates.view',
            'data.export',
            'activity.view',
            // Advanced
            'forecasts.view',
            'quotas.view',
            'email.view',
            'email.send',
            'meetings.view',
            'meetings.create',
            'meetings.edit',
            'meetings.delete',
            'playbooks.view',
            'cadences.view',
            'cadences.create',
            'campaigns.view',
            // Enterprise (limited)
            'documents.view',
            'proposals.view',
            'proposals.create',
            'deal_rooms.view',
            'ai.view',
            'ai.use',
            'competitors.view',
            'billing.view',
            'recordings.view',
            'recordings.create',
        ],
        'read_only' => [
            'modules.view',
            'pipelines.view',
            'dashboards.view',
            'reports.view',
            'activity.view',
            'forecasts.view',
            'quotas.view',
            'email.view',
            'meetings.view',
            'documents.view',
            'proposals.view',
            'deal_rooms.view',
            'competitors.view',
            'billing.view',
        ],
    ];

    /**
     * System roles that cannot be deleted.
     */
    public const SYSTEM_ROLES = ['admin', 'manager', 'sales_rep', 'read_only'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles with their permissions
        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($permissions === '*') {
                $role->givePermissionTo(Permission::all());
            } else {
                $role->syncPermissions($permissions);
            }
        }
    }
}
