<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Permissions;

use App\Domain\User\Events\RoleCreated;
use Illuminate\Support\Facades\DB;

/**
 * Sets up default permissions for all modules when a role is created.
 */
class SetupRolePermissionsListener
{
    private const ACCESS_OWN = 'own';

    public function handle(RoleCreated $event): void
    {
        $roleId = $event->roleId();
        $modules = DB::table('modules')->get();

        foreach ($modules as $module) {
            $this->createDefaultPermissionForModule($roleId, $module->id);
        }
    }

    private function createDefaultPermissionForModule(int $roleId, int $moduleId): void
    {
        // Check if permission already exists
        $exists = DB::table('module_permissions')
            ->where('role_id', $roleId)
            ->where('module_id', $moduleId)
            ->exists();

        if (!$exists) {
            // New custom roles get minimal permissions by default
            DB::table('module_permissions')->insert([
                'role_id' => $roleId,
                'module_id' => $moduleId,
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
                'can_import' => false,
                'record_access_level' => self::ACCESS_OWN,
                'field_restrictions' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
