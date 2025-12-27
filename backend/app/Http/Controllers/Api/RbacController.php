<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\Authorization\AuthorizationApplicationService;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Authorization\CachedAuthorizationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RbacController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AuthorizationApplicationService $authService,
        private readonly CachedAuthorizationService $cachedAuthService,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly ModuleRepositoryInterface $moduleRepository,
    ) {}

    /**
     * Get all roles with their permissions.
     */
    public function getRoles(): JsonResponse
    {
        $roles = $this->authService->getRoles();

        $data = collect($roles)->map(function ($role) {
            return [
                'id' => $role['id'],
                'name' => $role['name'],
                'display_name' => $role['display_name'] ?? null,
                'permissions' => $role['permissions'] ?? [],
                'users_count' => $this->roleRepository->getUserCount($role['id']),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Get a single role with details.
     */
    public function getRole(int $id): JsonResponse
    {
        $role = $this->authService->getRole($id);

        if ($role === null) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        return response()->json(['data' => $role]);
    }

    /**
     * Create a new role.
     */
    public function createRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            $role = $this->authService->createRole($validated);

            return response()->json([
                'message' => 'Role created successfully',
                'data' => $role,
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update a role.
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $id,
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            $role = $this->authService->updateRole($id, $validated);

            return response()->json([
                'message' => 'Role updated successfully',
                'data' => $role,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete a role.
     */
    public function deleteRole(int $id): JsonResponse
    {
        try {
            $this->authService->deleteRole($id);

            return response()->json([
                'message' => 'Role deleted successfully',
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get all permissions.
     */
    public function getPermissions(): JsonResponse
    {
        $permissions = $this->authService->getAllPermissions();

        $grouped = collect($permissions)->groupBy(function ($permission) {
            return explode('.', $permission['name'])[0];
        })->map(function ($group, $category) {
            return [
                'category' => $category,
                'permissions' => $group->map(fn ($p) => [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'action' => explode('.', $p['name'])[1] ?? $p['name'],
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => $grouped]);
    }

    /**
     * Get module permissions for a role.
     */
    public function getModulePermissions(int $roleId): JsonResponse
    {
        $modulePermissions = $this->authService->getModulePermissions($roleId);

        // Get all modules and merge with permissions
        $modules = $this->moduleRepository->findAll();
        $permissionsByModule = collect($modulePermissions)->keyBy('module_id');

        $data = collect($modules)->map(function ($module) use ($permissionsByModule) {
            $permission = $permissionsByModule->get($module->getId());

            return [
                'module_id' => $module->getId(),
                'module_name' => $module->getName(),
                'module_api_name' => $module->getApiName(),
                'can_view' => $permission['can_view'] ?? false,
                'can_create' => $permission['can_create'] ?? false,
                'can_edit' => $permission['can_edit'] ?? false,
                'can_delete' => $permission['can_delete'] ?? false,
                'can_export' => $permission['can_export'] ?? false,
                'can_import' => $permission['can_import'] ?? false,
                'record_access_level' => $permission['record_access_level'] ?? 'own',
                'field_restrictions' => $permission['restricted_fields'] ?? [],
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Update module permissions for a role.
     */
    public function updateModulePermissions(Request $request, int $roleId): JsonResponse
    {
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

        $permission = $this->authService->updateModulePermission(
            $roleId,
            $validated['module_id'],
            $validated
        );

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

        $permissionsById = [];
        foreach ($validated['permissions'] as $perm) {
            $permissionsById[$perm['module_id']] = $perm;
        }

        $this->authService->bulkUpdateModulePermissions($roleId, $permissionsById);

        return response()->json([
            'message' => 'Module permissions updated successfully',
        ]);
    }

    /**
     * Get users for a role.
     */
    public function getRoleUsers(int $roleId): JsonResponse
    {
        $users = $this->authService->getRoleUsers($roleId);

        return response()->json(['data' => $users]);
    }

    /**
     * Check if current user can assign a specific role (privilege escalation prevention).
     * Admins can assign any role. Non-admins cannot assign admin role.
     */
    private function canAssignRole(int $currentUserId, int $roleId): bool
    {
        $isAdmin = $this->cachedAuthService->isAdmin($currentUserId);

        // Admins can assign any role
        if ($isAdmin) {
            return true;
        }

        // Get the role being assigned
        $role = $this->authService->getRole($roleId);
        if (!$role) {
            return false;
        }

        // Non-admins cannot assign admin role
        if ($role['name'] === 'admin') {
            return false;
        }

        return true;
    }

    /**
     * Assign role to user.
     * Security: Prevents privilege escalation - non-admins cannot assign admin role.
     */
    public function assignRoleToUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $currentUserId = $request->user()->id;

        // Privilege escalation prevention
        if (!$this->canAssignRole($currentUserId, $validated['role_id'])) {
            return response()->json([
                'message' => 'You do not have permission to assign this role.',
            ], 403);
        }

        try {
            $this->authService->assignRoleToUser($validated['user_id'], $validated['role_id']);

            $roles = $this->authService->getUserRoles($validated['user_id']);

            return response()->json([
                'message' => 'Role assigned successfully',
                'data' => [
                    'user_id' => $validated['user_id'],
                    'roles' => collect($roles)->pluck('name'),
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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

        $this->authService->removeRoleFromUser($validated['user_id'], $validated['role_id']);

        $roles = $this->authService->getUserRoles($validated['user_id']);

        return response()->json([
            'message' => 'Role removed successfully',
            'data' => [
                'user_id' => $validated['user_id'],
                'roles' => collect($roles)->pluck('name'),
            ],
        ]);
    }

    /**
     * Get user permissions summary.
     * Security: Only admins can view other users' permissions.
     */
    public function getUserPermissions(Request $request, int $userId): JsonResponse
    {
        $currentUser = $request->user();
        $currentUserId = $currentUser->id;
        $isAdmin = $this->cachedAuthService->isAdmin($currentUserId);

        // Security check: Users can only view their own permissions unless they are admin
        if ($userId !== $currentUserId && !$isAdmin) {
            return response()->json([
                'message' => 'You do not have permission to view other users\' permissions.',
            ], 403);
        }

        $roles = $this->authService->getUserRoles($userId);
        $permissions = $this->cachedAuthService->getUserPermissions($userId);

        return response()->json([
            'data' => [
                'user_id' => $userId,
                'roles' => collect($roles)->pluck('name'),
                'permissions' => $permissions,
            ],
        ]);
    }

    /**
     * Sync user roles (replace all roles).
     * Security: Prevents privilege escalation - validates all roles being assigned.
     */
    public function syncUserRoles(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'integer|exists:roles,id',
        ]);

        $currentUserId = $request->user()->id;

        // Privilege escalation prevention - check each role being assigned
        foreach ($validated['roles'] as $roleId) {
            if (!$this->canAssignRole($currentUserId, $roleId)) {
                return response()->json([
                    'message' => 'You do not have permission to assign one or more of these roles.',
                ], 403);
            }
        }

        try {
            $this->authService->syncUserRoles($userId, $validated['roles']);

            $roles = $this->authService->getUserRoles($userId);

            return response()->json([
                'message' => 'User roles updated successfully',
                'data' => [
                    'user_id' => $userId,
                    'roles' => collect($roles)->map(fn ($r) => [
                        'id' => $r['id'],
                        'name' => $r['name'],
                    ]),
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get current user's permissions.
     */
    public function getCurrentUserPermissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $isAdmin = $this->cachedAuthService->isAdmin($userId);

        // Get module-level permissions
        $modules = $this->moduleRepository->findAll();
        $modulePermissions = [];

        foreach ($modules as $module) {
            $access = $this->cachedAuthService->getModuleAccess($userId, $module->getId());

            $modulePermissions[$module->getApiName()] = [
                'can_view' => $access->canView,
                'can_create' => $access->canCreate,
                'can_edit' => $access->canEdit,
                'can_delete' => $access->canDelete,
                'can_export' => $access->canExport,
                'can_import' => $access->canImport,
                'record_access_level' => $access->recordAccessLevel->value,
                'hidden_fields' => $access->restrictedFields,
            ];
        }

        $roles = $this->authService->getUserRoles($userId);
        $permissions = $this->cachedAuthService->getUserPermissions($userId);

        return response()->json([
            'data' => [
                'user_id' => $userId,
                'is_admin' => $isAdmin,
                'roles' => collect($roles)->pluck('name'),
                'system_permissions' => $permissions,
                'module_permissions' => $modulePermissions,
            ],
        ]);
    }
}
