<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Module;
use App\Models\ModulePermission;
use Spatie\Permission\Models\Role;

/**
 * Observer for Role model to handle permission setup when roles are created/deleted.
 */
class RoleObserver
{
    /**
     * Handle the Role "created" event.
     * Creates default ModulePermission entries for all existing modules.
     */
    public function created(Role $role): void
    {
        $modules = Module::all();

        foreach ($modules as $module) {
            $this->createDefaultPermissionForModule($role, $module);
        }
    }

    /**
     * Handle the Role "deleted" event.
     * Removes all ModulePermission entries for the deleted role.
     */
    public function deleted(Role $role): void
    {
        ModulePermission::where('role_id', $role->id)->delete();
    }

    /**
     * Create default permission for a module on a role.
     */
    protected function createDefaultPermissionForModule(Role $role, Module $module): void
    {
        // New custom roles get minimal permissions by default
        ModulePermission::firstOrCreate(
            [
                'role_id' => $role->id,
                'module_id' => $module->id,
            ],
            [
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
                'can_import' => false,
                'record_access_level' => ModulePermission::ACCESS_OWN,
                'field_restrictions' => [],
            ]
        );
    }
}
