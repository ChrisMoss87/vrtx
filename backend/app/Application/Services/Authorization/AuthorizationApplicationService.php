<?php

declare(strict_types=1);

namespace App\Application\Services\Authorization;

use App\Domain\Authorization\Entities\ModulePermission;
use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\Repositories\ModulePermissionRepositoryInterface;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Authorization\ValueObjects\ModuleAccess;
use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Infrastructure\Authorization\CachedAuthorizationService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Application service for authorization management.
 * Orchestrates role, permission, and user role assignment operations.
 */
class AuthorizationApplicationService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly ModulePermissionRepositoryInterface $modulePermissionRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly CachedAuthorizationService $cachedAuthService,
    ) {}

    // ========== ROLE MANAGEMENT ==========

    /**
     * Get all roles.
     *
     * @return array<int, array>
     */
    public function getRoles(): array
    {
        return $this->roleRepository->findAll();
    }

    /**
     * Get roles with pagination.
     */
    public function getRolesPaginated(array $filters, int $perPage = 25, int $page = 1): PaginatedResult
    {
        return $this->roleRepository->findWithFilters($filters, $perPage, $page);
    }

    /**
     * Get a single role with permissions.
     */
    public function getRole(int $id): ?array
    {
        $role = $this->roleRepository->findByIdWithPermissions($id);

        if ($role === null) {
            return null;
        }

        $role['user_count'] = $this->roleRepository->getUserCount($id);
        $role['module_permissions'] = $this->modulePermissionRepository->findByRoleId($id);

        return $role;
    }

    /**
     * Create a new role.
     *
     * @param array{
     *     name: string,
     *     display_name?: string,
     *     description?: string,
     *     permissions?: array<string>,
     * } $data
     */
    public function createRole(array $data): array
    {
        // Validate name is unique
        if ($this->roleRepository->nameExists($data['name'])) {
            throw new DomainException("Role name '{$data['name']}' already exists");
        }

        $role = Role::create(
            name: $data['name'],
            displayName: $data['display_name'] ?? null,
            description: $data['description'] ?? null,
            isSystem: false,
            permissions: $data['permissions'] ?? [],
        );

        $savedRole = $this->roleRepository->saveEntity($role);

        return $savedRole->toArray();
    }

    /**
     * Update a role.
     *
     * @param array{
     *     name?: string,
     *     display_name?: string,
     *     description?: string,
     *     permissions?: array<string>,
     * } $data
     */
    public function updateRole(int $id, array $data): array
    {
        $role = $this->roleRepository->findEntityById(RoleId::fromInt($id));

        if ($role === null) {
            throw new DomainException('Role not found');
        }

        // Check name uniqueness if changing
        if (isset($data['name']) && $data['name'] !== $role->getName()) {
            if ($this->roleRepository->nameExists($data['name'], $id)) {
                throw new DomainException("Role name '{$data['name']}' already exists");
            }
        }

        // Update role properties
        if (isset($data['display_name'])) {
            $role = $role->withDisplayName($data['display_name']);
        }
        if (isset($data['description'])) {
            $role = $role->withDescription($data['description']);
        }
        if (isset($data['permissions'])) {
            $role = $role->withPermissions($data['permissions']);
        }

        $savedRole = $this->roleRepository->saveEntity($role);

        // Invalidate cache for affected users
        $this->cachedAuthService->invalidateRole($id);

        return $savedRole->toArray();
    }

    /**
     * Delete a role.
     */
    public function deleteRole(int $id): bool
    {
        $role = $this->roleRepository->findEntityById(RoleId::fromInt($id));

        if ($role === null) {
            throw new DomainException('Role not found');
        }

        $role->ensureCanBeDeleted();

        // Invalidate cache for affected users before deletion
        $this->cachedAuthService->invalidateRole($id);

        return $this->roleRepository->deleteEntity(RoleId::fromInt($id));
    }

    // ========== PERMISSION MANAGEMENT ==========

    /**
     * Get all permissions in the system.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getAllPermissions(): array
    {
        return $this->roleRepository->getAllPermissions();
    }

    /**
     * Get permissions for a role.
     *
     * @return array<string>
     */
    public function getRolePermissions(int $roleId): array
    {
        return $this->roleRepository->getRolePermissions($roleId);
    }

    /**
     * Update permissions for a role.
     *
     * @param array<string> $permissions
     */
    public function updateRolePermissions(int $roleId, array $permissions): void
    {
        $this->roleRepository->syncPermissions($roleId, $permissions);

        // Invalidate cache for affected users
        $this->cachedAuthService->invalidateRole($roleId);
    }

    // ========== MODULE PERMISSION MANAGEMENT ==========

    /**
     * Get module permissions for a role.
     *
     * @return array<int, array>
     */
    public function getModulePermissions(int $roleId): array
    {
        return $this->modulePermissionRepository->findByRoleId($roleId);
    }

    /**
     * Update a single module permission for a role.
     */
    public function updateModulePermission(int $roleId, int $moduleId, array $data): array
    {
        $permission = $this->modulePermissionRepository->upsert($roleId, $moduleId, $data);

        // Invalidate cache for affected users
        $this->cachedAuthService->invalidateRole($roleId);

        return $permission;
    }

    /**
     * Update multiple module permissions for a role.
     *
     * @param array<int, array> $permissions Keyed by module_id
     */
    public function bulkUpdateModulePermissions(int $roleId, array $permissions): void
    {
        $this->modulePermissionRepository->bulkUpsertForRole($roleId, $permissions);

        // Invalidate cache for affected users
        $this->cachedAuthService->invalidateRole($roleId);
    }

    /**
     * Delete a module permission.
     */
    public function deleteModulePermission(int $roleId, int $moduleId): bool
    {
        $result = $this->modulePermissionRepository->delete($roleId, $moduleId);

        if ($result) {
            $this->cachedAuthService->invalidateRole($roleId);
        }

        return $result;
    }

    // ========== USER ROLE MANAGEMENT ==========

    /**
     * Assign a role to a user.
     */
    public function assignRoleToUser(int $userId, int $roleId): void
    {
        // Validate user and role exist
        if (!$this->userRepository->exists($userId)) {
            throw new DomainException('User not found');
        }
        if (!$this->roleRepository->exists($roleId)) {
            throw new DomainException('Role not found');
        }

        $this->userRepository->assignRole($userId, $roleId);

        // Invalidate user cache
        $this->cachedAuthService->invalidateUser($userId);
    }

    /**
     * Remove a role from a user.
     */
    public function removeRoleFromUser(int $userId, int $roleId): void
    {
        $this->userRepository->removeRole($userId, $roleId);

        // Invalidate user cache
        $this->cachedAuthService->invalidateUser($userId);
    }

    /**
     * Sync roles for a user (replace all).
     *
     * @param array<int> $roleIds
     */
    public function syncUserRoles(int $userId, array $roleIds): void
    {
        // Validate all roles exist
        foreach ($roleIds as $roleId) {
            if (!$this->roleRepository->exists($roleId)) {
                throw new DomainException("Role ID {$roleId} not found");
            }
        }

        $this->userRepository->syncRoles($userId, $roleIds);

        // Invalidate user cache
        $this->cachedAuthService->invalidateUser($userId);
    }

    /**
     * Get users with a specific role.
     *
     * @return array<int, array>
     */
    public function getRoleUsers(int $roleId): array
    {
        return $this->userRepository->findByRoleId($roleId);
    }

    /**
     * Get roles for a user.
     *
     * @return array<int, array>
     */
    public function getUserRoles(int $userId): array
    {
        return $this->userRepository->getUserRoles($userId);
    }

    // ========== PERMISSION CHECKS (delegated to cached service) ==========

    /**
     * Check if user has a permission.
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        return $this->cachedAuthService->hasPermission($userId, $permission);
    }

    /**
     * Check if user can access a module.
     */
    public function canAccessModule(int $userId, int $moduleId, string $action): bool
    {
        return $this->cachedAuthService->canAccessModule($userId, $moduleId, $action);
    }

    /**
     * Get the permission matrix for a user.
     */
    public function getUserPermissionMatrix(int $userId): array
    {
        return $this->cachedAuthService->getPermissionMatrix($userId);
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(int $userId): bool
    {
        return $this->cachedAuthService->isAdmin($userId);
    }
}
