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
     * Run the database seeds.
     */
    public function run(): void
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
        ];

        foreach ($systemPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create default roles with their permissions

        // Admin - has all permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        // Manager - can manage most things except roles and settings
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
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
        ]);

        // Sales Rep - standard user permissions
        $salesRepRole = Role::firstOrCreate(['name' => 'sales_rep']);
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
        $readOnlyRole = Role::firstOrCreate(['name' => 'read_only']);
        $readOnlyRole->givePermissionTo([
            'modules.view',
            'pipelines.view',
            'dashboards.view',
            'reports.view',
            'activity.view',
        ]);
    }
}
