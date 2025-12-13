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
     */
    public const PERMISSIONS = [
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

    /**
     * Default role definitions.
     */
    public const ROLES = [
        'admin' => '*', // All permissions
        'manager' => [
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
        ],
        'sales_rep' => [
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
        ],
        'read_only' => [
            'modules.view',
            'pipelines.view',
            'dashboards.view',
            'reports.view',
            'activity.view',
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
