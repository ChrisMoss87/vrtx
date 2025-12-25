<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Permissions;

use App\Domain\Modules\Events\ModuleCreated;
use Illuminate\Support\Facades\DB;

/**
 * Sets up default permissions for all roles when a module is created.
 */
class SetupModulePermissionsListener
{
    private const ACCESS_ALL = 'all';
    private const ACCESS_OWN = 'own';

    public function handle(ModuleCreated $event): void
    {
        $moduleId = $event->moduleId();
        $roles = DB::table('roles')->get();

        foreach ($roles as $role) {
            $this->createDefaultPermissionForRole($moduleId, $role);
        }
    }

    private function createDefaultPermissionForRole(int $moduleId, $role): void
    {
        $defaults = $this->getDefaultPermissionsForRole($role->name);

        // Check if permission already exists
        $exists = DB::table('module_permissions')
            ->where('role_id', $role->id)
            ->where('module_id', $moduleId)
            ->exists();

        if (!$exists) {
            DB::table('module_permissions')->insert(array_merge($defaults, [
                'role_id' => $role->id,
                'module_id' => $moduleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
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
                'record_access_level' => self::ACCESS_ALL,
                'field_restrictions' => json_encode([]),
            ],
            'manager' => [
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true,
                'can_export' => true,
                'can_import' => true,
                'record_access_level' => self::ACCESS_ALL,
                'field_restrictions' => json_encode([]),
            ],
            'sales_rep' => [
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => false,
                'can_export' => true,
                'can_import' => false,
                'record_access_level' => self::ACCESS_OWN,
                'field_restrictions' => json_encode([]),
            ],
            'read_only' => [
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
                'can_import' => false,
                'record_access_level' => self::ACCESS_ALL,
                'field_restrictions' => json_encode([]),
            ],
            default => [
                'can_view' => true,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
                'can_import' => false,
                'record_access_level' => self::ACCESS_OWN,
                'field_restrictions' => json_encode([]),
            ],
        };
    }
}
