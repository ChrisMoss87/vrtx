<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModulePermission;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RbacController extends Controller
{
    public function __construct(
        private RbacService $rbacService
    ) {}

    /**
     * Get all roles with their permissions.
     */
    public function getRoles(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => $role->users()->count(),
            ];
        });

        return response()->json(['data' => $roles]);
    }

    /**
     * Get a single role with details.
     */
    public function getRole(int $id): JsonResponse
    {
        $role = Role::with('permissions')->findOrFail($id);
        $modulePermissions = ModulePermission::where('role_id', $id)
            ->with('module:id,name,api_name')
            ->get();

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'module_permissions' => $modulePermissions,
                'users_count' => $role->users()->count(),
            ],
        ]);
    }

    /**
     * Create a new role.
     */
    public function createRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = $this->rbacService->createRole(
            $validated['name'],
            $validated['permissions'] ?? []
        );

        return response()->json([
            'message' => 'Role created successfully',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ], 201);
    }

    /**
     * Update a role.
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $id,
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }

        if (isset($validated['permissions'])) {
            $this->rbacService->updateRolePermissions($role, $validated['permissions']);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ]);
    }

    /**
     * Delete a role.
     */
    public function deleteRole(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        // Prevent deletion of system roles
        if (in_array($role->name, ['admin', 'manager', 'sales_rep', 'read_only'])) {
            return response()->json([
                'message' => 'Cannot delete system roles',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Get all permissions.
     */
    public function getPermissions(): JsonResponse
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        })->map(function ($group, $category) {
            return [
                'category' => $category,
                'permissions' => $group->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'action' => explode('.', $p->name)[1] ?? $p->name,
                ]),
            ];
        })->values();

        return response()->json(['data' => $permissions]);
    }

    /**
     * Get module permissions for a role.
     */
    public function getModulePermissions(int $roleId): JsonResponse
    {
        $role = Role::findOrFail($roleId);
        $modules = Module::all();
        $existingPermissions = ModulePermission::where('role_id', $roleId)
            ->get()
            ->keyBy('module_id');

        $modulePermissions = $modules->map(function ($module) use ($existingPermissions) {
            $permission = $existingPermissions->get($module->id);

            return [
                'module_id' => $module->id,
                'module_name' => $module->name,
                'module_api_name' => $module->api_name,
                'can_view' => $permission?->can_view ?? false,
                'can_create' => $permission?->can_create ?? false,
                'can_edit' => $permission?->can_edit ?? false,
                'can_delete' => $permission?->can_delete ?? false,
                'can_export' => $permission?->can_export ?? false,
                'can_import' => $permission?->can_import ?? false,
                'record_access_level' => $permission?->record_access_level ?? 'own',
                'field_restrictions' => $permission?->field_restrictions ?? [],
            ];
        });

        return response()->json(['data' => $modulePermissions]);
    }

    /**
     * Update module permissions for a role.
     */
    public function updateModulePermissions(Request $request, int $roleId): JsonResponse
    {
        $role = Role::findOrFail($roleId);

        $validated = $request->validate([
            'module_id' => 'required|integer|exists:modules,id',
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_edit' => 'boolean',
            'can_delete' => 'boolean',
            'can_export' => 'boolean',
            'can_import' => 'boolean',
            'record_access_level' => 'string|in:own,team,all,none',
            'field_restrictions' => 'array',
            'field_restrictions.*' => 'string',
        ]);

        $module = Module::findOrFail($validated['module_id']);

        $permission = $this->rbacService->setModulePermission($role, $module, $validated);

        return response()->json([
            'message' => 'Module permissions updated successfully',
            'data' => $permission,
        ]);
    }

    /**
     * Bulk update module permissions for a role.
     */
    public function bulkUpdateModulePermissions(Request $request, int $roleId): JsonResponse
    {
        $role = Role::findOrFail($roleId);

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.module_id' => 'required|integer|exists:modules,id',
            'permissions.*.can_view' => 'boolean',
            'permissions.*.can_create' => 'boolean',
            'permissions.*.can_edit' => 'boolean',
            'permissions.*.can_delete' => 'boolean',
            'permissions.*.can_export' => 'boolean',
            'permissions.*.can_import' => 'boolean',
            'permissions.*.record_access_level' => 'string|in:own,team,all,none',
            'permissions.*.field_restrictions' => 'array',
        ]);

        foreach ($validated['permissions'] as $permissionData) {
            $module = Module::findOrFail($permissionData['module_id']);
            $this->rbacService->setModulePermission($role, $module, $permissionData);
        }

        return response()->json([
            'message' => 'Module permissions updated successfully',
        ]);
    }

    /**
     * Get users for a role.
     */
    public function getRoleUsers(int $roleId): JsonResponse
    {
        $role = Role::findOrFail($roleId);
        $users = $role->users()->select('id', 'name', 'email')->get();

        return response()->json(['data' => $users]);
    }

    /**
     * Assign role to user.
     */
    public function assignRoleToUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);

        $this->rbacService->assignRole($user, $role);

        return response()->json([
            'message' => 'Role assigned successfully',
            'data' => [
                'user_id' => $user->id,
                'roles' => $user->roles->pluck('name'),
            ],
        ]);
    }

    /**
     * Remove role from user.
     */
    public function removeRoleFromUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);

        $this->rbacService->removeRole($user, $role);

        return response()->json([
            'message' => 'Role removed successfully',
            'data' => [
                'user_id' => $user->id,
                'roles' => $user->roles->pluck('name'),
            ],
        ]);
    }

    /**
     * Get user permissions summary.
     */
    public function getUserPermissions(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Sync user roles (replace all roles).
     */
    public function syncUserRoles(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'integer|exists:roles,id',
        ]);

        $roleNames = Role::whereIn('id', $validated['roles'])->pluck('name')->toArray();
        $this->rbacService->syncRoles($user, $roleNames);

        return response()->json([
            'message' => 'User roles updated successfully',
            'data' => [
                'user_id' => $user->id,
                'roles' => $user->fresh()->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                ]),
            ],
        ]);
    }

    /**
     * Get current user's permissions.
     */
    public function getCurrentUserPermissions(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get module-level permissions
        $modules = Module::all();
        $modulePermissions = [];

        foreach ($modules as $module) {
            $permission = $this->rbacService->getModulePermission($user, $module);

            $modulePermissions[$module->api_name] = [
                'can_view' => $user->hasRole('admin') || ($permission?->can_view ?? false),
                'can_create' => $user->hasRole('admin') || ($permission?->can_create ?? false),
                'can_edit' => $user->hasRole('admin') || ($permission?->can_edit ?? false),
                'can_delete' => $user->hasRole('admin') || ($permission?->can_delete ?? false),
                'can_export' => $user->hasRole('admin') || ($permission?->can_export ?? false),
                'can_import' => $user->hasRole('admin') || ($permission?->can_import ?? false),
                'record_access_level' => $user->hasRole('admin') ? 'all' : ($permission?->record_access_level ?? 'none'),
                'hidden_fields' => $user->hasRole('admin') ? [] : ($permission?->field_restrictions ?? []),
            ];
        }

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'is_admin' => $user->hasRole('admin'),
                'roles' => $user->roles->pluck('name'),
                'system_permissions' => $user->getAllPermissions()->pluck('name'),
                'module_permissions' => $modulePermissions,
            ],
        ]);
    }
}
