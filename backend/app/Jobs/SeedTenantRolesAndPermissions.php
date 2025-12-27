<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Tenancy\Entities\Tenant;
use App\Infrastructure\Authorization\CachedAuthorizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Seeds default roles and permissions for a newly created tenant.
 *
 * This job is executed as part of the tenant creation pipeline
 * and runs within the tenant's database context.
 */
class SeedTenantRolesAndPermissions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Tenant $tenant,
    ) {}

    public function handle(?Tenant $tenant = null): void
    {
        // Invalidate cached permissions
        try {
            app(CachedAuthorizationService::class)->invalidateAll();
        } catch (\Exception $e) {
            // Cache service may not be available during seeding
        }

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

        $now = now();
        $permissionIds = [];

        foreach ($systemPermissions as $permission) {
            $existing = DB::table('permissions')
                ->where('name', $permission)
                ->where('guard_name', 'web')
                ->first();

            if ($existing) {
                $permissionIds[$permission] = $existing->id;
            } else {
                $id = DB::table('permissions')->insertGetId([
                    'name' => $permission,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $permissionIds[$permission] = $id;
            }
        }

        // Create default roles with their permissions

        // Admin - has all permissions (system role)
        $adminRoleId = $this->createRoleIfNotExists('admin', 'Administrator', true);
        $this->syncRolePermissions($adminRoleId, array_values($permissionIds));

        // Manager - can manage most things except roles, settings, and technical features
        $managerRoleId = $this->createRoleIfNotExists('manager', 'Manager', false);
        $this->syncRolePermissions($managerRoleId, array_map(
            fn ($name) => $permissionIds[$name],
            [
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
            ]
        ));

        // Sales Rep - standard user permissions
        $salesRepRoleId = $this->createRoleIfNotExists('sales_rep', 'Sales Rep', false);
        $this->syncRolePermissions($salesRepRoleId, array_map(
            fn ($name) => $permissionIds[$name],
            [
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
            ]
        ));

        // Read-only - can only view
        $readOnlyRoleId = $this->createRoleIfNotExists('read_only', 'Read Only', false);
        $this->syncRolePermissions($readOnlyRoleId, array_map(
            fn ($name) => $permissionIds[$name],
            [
                'modules.view',
                'pipelines.view',
                'dashboards.view',
                'reports.view',
                'activity.view',
            ]
        ));
    }

    /**
     * Create a role if it doesn't exist.
     */
    private function createRoleIfNotExists(string $name, string $displayName, bool $isSystem): int
    {
        $existing = DB::table('roles')
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $now = now();

        return DB::table('roles')->insertGetId([
            'name' => $name,
            'display_name' => $displayName,
            'guard_name' => 'web',
            'is_system' => $isSystem,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Sync role permissions (delete existing and insert new).
     *
     * @param array<int> $permissionIds
     */
    private function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        // Remove existing permissions for this role
        DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->delete();

        // Insert new permissions
        $inserts = [];
        foreach ($permissionIds as $permissionId) {
            $inserts[] = [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ];
        }

        if (!empty($inserts)) {
            DB::table('role_has_permissions')->insert($inserts);
        }
    }
}
