<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ModulePermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeds default module permissions for all existing roles and modules.
 *
 * This seeder should be run WITHIN tenant context using:
 * php artisan tenants:seed --class=ModulePermissionsSeeder
 */
class ModulePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = tenant('id');

        if (!$tenantId) {
            $this->command->error('This seeder must be run in tenant context!');
            $this->command->line('Use: php artisan tenants:seed --class=ModulePermissionsSeeder');
            return;
        }

        $this->command->info("Seeding module permissions for tenant: {$tenantId}");

        $roles = Role::all();
        $modules = Module::all();

        if ($roles->isEmpty()) {
            $this->command->warn('No roles found. Please run RolesAndPermissionsSeeder first.');
            return;
        }

        if ($modules->isEmpty()) {
            $this->command->warn('No modules found. Please create some modules first.');
            return;
        }

        $count = 0;

        foreach ($roles as $role) {
            foreach ($modules as $module) {
                $defaults = $this->getDefaultPermissionsForRole($role->name);

                ModulePermission::firstOrCreate(
                    [
                        'role_id' => $role->id,
                        'module_id' => $module->id,
                    ],
                    $defaults
                );

                $count++;
            }
        }

        $this->command->info("✓ Created/verified {$count} module permission entries");
    }

    /**
     * Get default permissions based on role name.
     */
    protected function getDefaultPermissionsForRole(string $roleName): array
    {
        return match ($roleName) {
            'admin' => [
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true,
                'can_export' => true,
                'can_import' => true,
                'record_access_level' => ModulePermission::ACCESS_ALL,
                'field_restrictions' => [],
            ],
            'manager' => [
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true,
                'can_export' => true,
                'can_import' => true,
                'record_access_level' => ModulePermission::ACCESS_ALL,
                'field_restrictions' => [],
            ],
            'sales_rep' => [
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => false,
                'can_export' => true,
                'can_import' => false,
                'record_access_level' => ModulePermission::ACCESS_OWN,
                'field_restrictions' => [],
            ],
            'read_only' => [
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
                'can_import' => false,
                'record_access_level' => ModulePermission::ACCESS_ALL,
                'field_restrictions' => [],
            ],
            default => [
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
                'can_import' => false,
                'record_access_level' => ModulePermission::ACCESS_OWN,
                'field_restrictions' => [],
            ],
        };
    }
}
