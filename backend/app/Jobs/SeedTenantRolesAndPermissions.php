<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Seeds default roles and permissions for a newly created tenant.
 *
 * This job is executed as part of the tenant creation pipeline
 * and runs within the tenant's database context.
 */
class SeedTenantRolesAndPermissions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create system-wide permissions
        $systemPermissions = [
            // Module management
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

            // Activity logs
            'activity.view',

            // Blueprints (workflows)
            'blueprints.view',
            'blueprints.create',
            'blueprints.edit',
            'blueprints.delete',

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
        ];

        foreach ($systemPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create default roles with their permissions

        // Admin - has all permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(Permission::all());

        // Manager - can manage most things except roles, settings, and technical features
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo([
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
            'blueprints.view',
            'blueprints.create',
            'blueprints.edit',
        ]);

        // Sales Rep - standard user permissions
        $salesRepRole = Role::firstOrCreate(['name' => 'sales_rep', 'guard_name' => 'web']);
        $salesRepRole->givePermissionTo([
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
        ]);

        // Read-only - can only view
        $readOnlyRole = Role::firstOrCreate(['name' => 'read_only', 'guard_name' => 'web']);
        $readOnlyRole->givePermissionTo([
            'modules.view',
            'pipelines.view',
            'dashboards.view',
            'reports.view',
            'activity.view',
        ]);
    }
}
