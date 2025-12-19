<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Module;
use App\Models\ModulePermission;
use Spatie\Permission\Models\Role;

/**
 * Observer for Module model to handle permission setup when modules are created/deleted.
 */
class ModuleObserver
{
    /**
     * Handle the Module "created" event.
     * Creates default ModulePermission entries for all existing roles.
     */
    public function created(Module $module): void
    {
        $roles = Role::all();

        foreach ($roles as $role) {
            $this->createDefaultPermissionForRole($module, $role);
        }
    }

    /**
     * Handle the Module "deleted" event.
     * Removes all ModulePermission entries for the deleted module.
     */
    public function deleted(Module $module): void
    {
        ModulePermission::where('module_id', $module->id)->delete();
    }

    /**
     * Create default permission for a role on a module.
     */
    protected function createDefaultPermissionForRole(Module $module, Role $role): void
    {
        // Define default permissions based on role
        $defaults = $this->getDefaultPermissionsForRole($role->name);

        ModulePermission::firstOrCreate(
            [
                'role_id' => $role->id,
                'module_id' => $module->id,
            ],
            $defaults
        );
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
                // Custom roles get view-only access to own records by default
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
