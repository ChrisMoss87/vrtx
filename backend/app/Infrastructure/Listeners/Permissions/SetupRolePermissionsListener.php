<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Permissions;

use App\Domain\User\Events\RoleCreated;
use App\Models\Module;
use App\Models\ModulePermission;

/**
 * Sets up default permissions for all modules when a role is created.
 */
class SetupRolePermissionsListener
{
    public function handle(RoleCreated $event): void
    {
        $roleId = $event->roleId();
        $modules = Module::all();

        foreach ($modules as $module) {
            $this->createDefaultPermissionForModule($roleId, $module->id);
        }
    }

    private function createDefaultPermissionForModule(int $roleId, int $moduleId): void
    {
        // New custom roles get minimal permissions by default
        ModulePermission::firstOrCreate(
            [
                'role_id' => $roleId,
                'module_id' => $moduleId,
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
