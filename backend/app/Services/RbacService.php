<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RbacService
{
    public const ACCESS_ALL = 'all';
    public const ACCESS_TEAM = 'team';
    public const ACCESS_OWN = 'own';
    public const ACCESS_NONE = 'none';

    /**
     * Check if user has system permission.
     */
    public function hasPermission(User $user, string $permission): bool
    {
        return $user->hasPermissionTo($permission);
    }

    /**
     * Check if user can perform action on a module.
     */
    public function canAccessModule(User $user, object $module, string $action): bool
    {
        // Admin can do everything
        if ($user->hasRole('admin')) {
            return true;
        }

        // Get module permission for user's role
        $permission = $this->getModulePermission($user, $module);

        if (!$permission) {
            // Default: check if user has general modules.view permission
            return $action === 'view' && $user->hasPermissionTo('modules.view');
        }

        return $this->canPerformAction($permission, $action);
    }

    /**
     * Check if permission allows action.
     */
    protected function canPerformAction(object $permission, string $action): bool
    {
        return match ($action) {
            'view' => (bool) ($permission->can_view ?? false),
            'create' => (bool) ($permission->can_create ?? false),
            'edit' => (bool) ($permission->can_edit ?? false),
            'delete' => (bool) ($permission->can_delete ?? false),
            'export' => (bool) ($permission->can_export ?? false),
            'import' => (bool) ($permission->can_import ?? false),
            default => false,
        };
    }

    /**
     * Get the module permission for a user's role.
     */
    public function getModulePermission(User $user, object $module): ?object
    {
        $moduleId = is_object($module) ? ($module->id ?? $module->getId()) : $module;
        $cacheKey = "module_permission_{$user->id}_{$moduleId}";

        return Cache::remember($cacheKey, 300, function () use ($user, $moduleId) {
            $roleIds = $user->roles->pluck('id');

            // Get the most permissive permission from all user's roles
            return DB::table('module_permissions')
                ->where('module_id', $moduleId)
                ->whereIn('role_id', $roleIds)
                ->orderByRaw("
                    CASE record_access_level
                        WHEN 'all' THEN 1
                        WHEN 'team' THEN 2
                        WHEN 'own' THEN 3
                        ELSE 4
                    END
                ")
                ->first();
        });
    }

    /**
     * Check if user can view a specific record.
     */
    public function canViewRecord(User $user, object $record): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $module = DB::table('modules')->where('id', $record->module_id)->first();
        $permission = $this->getModulePermission($user, $module);

        if (!$permission || !$permission->can_view) {
            return false;
        }

        return $this->checkRecordAccess($user, $record, $permission);
    }

    /**
     * Check if user can edit a specific record.
     */
    public function canEditRecord(User $user, object $record): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $module = DB::table('modules')->where('id', $record->module_id)->first();
        $permission = $this->getModulePermission($user, $module);

        if (!$permission || !$permission->can_edit) {
            return false;
        }

        return $this->checkRecordAccess($user, $record, $permission);
    }

    /**
     * Check if user can delete a specific record.
     */
    public function canDeleteRecord(User $user, object $record): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $module = DB::table('modules')->where('id', $record->module_id)->first();
        $permission = $this->getModulePermission($user, $module);

        if (!$permission || !$permission->can_delete) {
            return false;
        }

        return $this->checkRecordAccess($user, $record, $permission);
    }

    /**
     * Check record access based on access level.
     */
    protected function checkRecordAccess(User $user, object $record, object $permission): bool
    {
        return match ($permission->record_access_level) {
            self::ACCESS_ALL => true,
            self::ACCESS_TEAM => $this->isTeamRecord($user, $record),
            self::ACCESS_OWN => ($record->owner_id ?? $record->created_by) === $user->id,
            default => false,
        };
    }

    /**
     * Check if record belongs to user's team.
     */
    protected function isTeamRecord(User $user, object $record): bool
    {
        // If record is owned by user, it's accessible
        $ownerId = $record->owner_id ?? $record->created_by ?? null;
        if ($ownerId === $user->id) {
            return true;
        }

        // TODO: Implement team logic when teams are added
        // For now, team access means all records
        return true;
    }

    /**
     * Apply record access scope to query.
     */
    public function applyRecordAccessScope(Builder $query, User $user, object $module): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        $permission = $this->getModulePermission($user, $module);

        if (!$permission || $permission->record_access_level === self::ACCESS_NONE) {
            // No access - return empty result
            return $query->whereRaw('1 = 0');
        }

        return match ($permission->record_access_level) {
            self::ACCESS_ALL => $query,
            self::ACCESS_TEAM => $this->applyTeamScope($query, $user),
            self::ACCESS_OWN => $query->where('created_by', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Apply team scope to query.
     */
    protected function applyTeamScope(Builder $query, User $user): Builder
    {
        // TODO: Implement team logic when teams are added
        // For now, team access means all records
        return $query;
    }

    /**
     * Get hidden fields for a user on a module.
     */
    public function getHiddenFields(User $user, object $module): array
    {
        if ($user->hasRole('admin')) {
            return [];
        }

        $permission = $this->getModulePermission($user, $module);

        if (!$permission) {
            return [];
        }

        $fieldRestrictions = is_string($permission->field_restrictions ?? null)
            ? json_decode($permission->field_restrictions, true)
            : ($permission->field_restrictions ?? []);

        return $fieldRestrictions['hidden'] ?? [];
    }

    /**
     * Filter record data to remove restricted fields.
     */
    public function filterRecordData(User $user, object $module, array $data): array
    {
        $hiddenFields = $this->getHiddenFields($user, $module);

        if (empty($hiddenFields)) {
            return $data;
        }

        foreach ($hiddenFields as $fieldName) {
            unset($data[$fieldName]);
        }

        return $data;
    }

    /**
     * Get all roles.
     */
    public function getAllRoles(): \Illuminate\Database\Eloquent\Collection
    {
        return Role::with('permissions')->get();
    }

    /**
     * Get all permissions.
     */
    public function getAllPermissions(): \Illuminate\Database\Eloquent\Collection
    {
        return Permission::all();
    }

    /**
     * Create a new role with permissions.
     */
    public function createRole(string $name, array $permissions = []): Role
    {
        $role = Role::create(['name' => $name]);

        if (!empty($permissions)) {
            $role->givePermissionTo($permissions);
        }

        return $role;
    }

    /**
     * Update role permissions.
     */
    public function updateRolePermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);
        $this->clearPermissionCache();

        return $role;
    }

    /**
     * Set module permissions for a role.
     */
    public function setModulePermission(
        Role $role,
        object $module,
        array $permissions
    ): object {
        $moduleId = is_object($module) ? ($module->id ?? $module->getId()) : $module;

        $existingPermission = DB::table('module_permissions')
            ->where('role_id', $role->id)
            ->where('module_id', $moduleId)
            ->first();

        $data = [
            'can_view' => $permissions['can_view'] ?? false,
            'can_create' => $permissions['can_create'] ?? false,
            'can_edit' => $permissions['can_edit'] ?? false,
            'can_delete' => $permissions['can_delete'] ?? false,
            'can_export' => $permissions['can_export'] ?? false,
            'can_import' => $permissions['can_import'] ?? false,
            'record_access_level' => $permissions['record_access_level'] ?? 'own',
            'field_restrictions' => json_encode($permissions['field_restrictions'] ?? []),
            'updated_at' => now(),
        ];

        if ($existingPermission) {
            DB::table('module_permissions')
                ->where('id', $existingPermission->id)
                ->update($data);
            $modulePermission = DB::table('module_permissions')->find($existingPermission->id);
        } else {
            $data['role_id'] = $role->id;
            $data['module_id'] = $moduleId;
            $data['created_at'] = now();
            $id = DB::table('module_permissions')->insertGetId($data);
            $modulePermission = DB::table('module_permissions')->find($id);
        }

        $this->clearPermissionCache();

        return $modulePermission;
    }

    /**
     * Get module permissions for a role.
     */
    public function getModulePermissionsForRole(Role $role): \Illuminate\Support\Collection
    {
        return DB::table('module_permissions')
            ->where('role_id', $role->id)
            ->get();
    }

    /**
     * Clear permission cache.
     */
    public function clearPermissionCache(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Clear module permission cache
        Cache::flush();
    }

    /**
     * Assign role to user.
     */
    public function assignRole(User $user, string|Role $role): void
    {
        $user->assignRole($role);
        $this->clearPermissionCache();
    }

    /**
     * Remove role from user.
     */
    public function removeRole(User $user, string|Role $role): void
    {
        $user->removeRole($role);
        $this->clearPermissionCache();
    }

    /**
     * Sync user roles.
     */
    public function syncRoles(User $user, array $roles): void
    {
        $user->syncRoles($roles);
        $this->clearPermissionCache();
    }
}
