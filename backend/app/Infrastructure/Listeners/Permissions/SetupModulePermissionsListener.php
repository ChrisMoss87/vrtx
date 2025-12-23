<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Permissions;

use App\Domain\Modules\Events\ModuleCreated;
use App\Models\ModulePermission;
use Spatie\Permission\Models\Role;

/**
 * Sets up default permissions for all roles when a module is created.
 */
class SetupModulePermissionsListener
{
    public function handle(ModuleCreated $event): void
    {
        $moduleId = $event->moduleId();
        $roles = Role::all();

        foreach ($roles as $role) {
            $this->createDefaultPermissionForRole($moduleId, $role);
        }
    }

    private function createDefaultPermissionForRole(int $moduleId, Role $role): void
    {
        $defaults = $this->getDefaultPermissionsForRole($role->name);

        ModulePermission::firstOrCreate(
            [
                'role_id' => $role->id,
                'module_id' => $moduleId,
            ],
            $defaults
        );
    }

    private function getDefaultPermissionsForRole(string $roleName): array
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
