<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Module;
use App\Models\ModulePermission;
use App\Models\ModuleRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RbacService
{
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
    public function canAccessModule(User $user, Module $module, string $action): bool
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

        return $permission->canPerform($action);
    }

    /**
     * Get the module permission for a user's role.
     */
    public function getModulePermission(User $user, Module $module): ?ModulePermission
    {
        $cacheKey = "module_permission_{$user->id}_{$module->id}";

        return Cache::remember($cacheKey, 300, function () use ($user, $module) {
            $roleIds = $user->roles->pluck('id');

            // Get the most permissive permission from all user's roles
            return ModulePermission::where('module_id', $module->id)
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
    public function canViewRecord(User $user, ModuleRecord $record): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $module = $record->module;
        $permission = $this->getModulePermission($user, $module);

        if (!$permission || !$permission->can_view) {
            return false;
        }

        return $this->checkRecordAccess($user, $record, $permission);
    }

    /**
     * Check if user can edit a specific record.
     */
    public function canEditRecord(User $user, ModuleRecord $record): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $module = $record->module;
        $permission = $this->getModulePermission($user, $module);

        if (!$permission || !$permission->can_edit) {
            return false;
        }

        return $this->checkRecordAccess($user, $record, $permission);
    }

    /**
     * Check if user can delete a specific record.
     */
    public function canDeleteRecord(User $user, ModuleRecord $record): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $module = $record->module;
        $permission = $this->getModulePermission($user, $module);

        if (!$permission || !$permission->can_delete) {
            return false;
        }

        return $this->checkRecordAccess($user, $record, $permission);
    }

    /**
     * Check record access based on access level.
     */
    protected function checkRecordAccess(User $user, ModuleRecord $record, ModulePermission $permission): bool
    {
        return match ($permission->record_access_level) {
            ModulePermission::ACCESS_ALL => true,
            ModulePermission::ACCESS_TEAM => $this->isTeamRecord($user, $record),
            ModulePermission::ACCESS_OWN => $record->owner_id === $user->id,
            default => false,
        };
    }

    /**
     * Check if record belongs to user's team.
     */
    protected function isTeamRecord(User $user, ModuleRecord $record): bool
    {
        // If record is owned by user, it's accessible
        if ($record->owner_id === $user->id) {
            return true;
        }

        // TODO: Implement team logic when teams are added
        // For now, team access means all records
        return true;
    }

    /**
     * Apply record access scope to query.
     */
    public function applyRecordAccessScope(Builder $query, User $user, Module $module): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        $permission = $this->getModulePermission($user, $module);

        if (!$permission || $permission->record_access_level === ModulePermission::ACCESS_NONE) {
            // No access - return empty result
            return $query->whereRaw('1 = 0');
        }

        return match ($permission->record_access_level) {
            ModulePermission::ACCESS_ALL => $query,
            ModulePermission::ACCESS_TEAM => $this->applyTeamScope($query, $user),
            ModulePermission::ACCESS_OWN => $query->where('owner_id', $user->id),
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
    public function getHiddenFields(User $user, Module $module): array
    {
        if ($user->hasRole('admin')) {
            return [];
        }

        $permission = $this->getModulePermission($user, $module);

        if (!$permission) {
            return [];
        }

        return $permission->getHiddenFields();
    }

    /**
     * Filter record data to remove restricted fields.
     */
    public function filterRecordData(User $user, Module $module, array $data): array
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
        Module $module,
        array $permissions
    ): ModulePermission {
        $modulePermission = ModulePermission::updateOrCreate(
            [
                'role_id' => $role->id,
                'module_id' => $module->id,
            ],
            [
                'can_view' => $permissions['can_view'] ?? false,
                'can_create' => $permissions['can_create'] ?? false,
                'can_edit' => $permissions['can_edit'] ?? false,
                'can_delete' => $permissions['can_delete'] ?? false,
                'can_export' => $permissions['can_export'] ?? false,
                'can_import' => $permissions['can_import'] ?? false,
                'record_access_level' => $permissions['record_access_level'] ?? 'own',
                'field_restrictions' => $permissions['field_restrictions'] ?? [],
            ]
        );

        $this->clearPermissionCache();

        return $modulePermission;
    }

    /**
     * Get module permissions for a role.
     */
    public function getModulePermissionsForRole(Role $role): \Illuminate\Database\Eloquent\Collection
    {
        return ModulePermission::where('role_id', $role->id)
            ->with('module')
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
