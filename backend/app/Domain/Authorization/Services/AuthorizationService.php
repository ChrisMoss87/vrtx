<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

use App\Domain\Authorization\Repositories\ModulePermissionRepositoryInterface;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Authorization\ValueObjects\ModuleAccess;
use App\Domain\Authorization\ValueObjects\RecordAccessLevel;

/**
 * Domain service for authorization logic.
 *
 * This service contains pure domain logic for permission and access checks.
 * It operates on domain concepts and delegates data access to repositories.
 */
class AuthorizationService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly ModulePermissionRepositoryInterface $modulePermissionRepository,
    ) {}

    /**
     * Check if user has a specific system permission.
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        // Admin users have all permissions
        if ($this->isAdmin($userId)) {
            return true;
        }

        $permissions = $this->roleRepository->getUserPermissions($userId);

        return in_array($permission, $permissions, true);
    }

    /**
     * Check if user has any of the given permissions.
     *
     * @param array<string> $permissions
     */
    public function hasAnyPermission(int $userId, array $permissions): bool
    {
        if ($this->isAdmin($userId)) {
            return true;
        }

        $userPermissions = $this->roleRepository->getUserPermissions($userId);

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions.
     *
     * @param array<string> $permissions
     */
    public function hasAllPermissions(int $userId, array $permissions): bool
    {
        if ($this->isAdmin($userId)) {
            return true;
        }

        $userPermissions = $this->roleRepository->getUserPermissions($userId);

        foreach ($permissions as $permission) {
            if (!in_array($permission, $userPermissions, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(int $userId): bool
    {
        return $this->roleRepository->userIsAdmin($userId);
    }

    /**
     * Check if user can perform an action on a module.
     */
    public function canAccessModule(int $userId, int $moduleId, string $action): bool
    {
        if ($this->isAdmin($userId)) {
            return true;
        }

        return $this->modulePermissionRepository->userCanAccessModule($userId, $moduleId, $action);
    }

    /**
     * Get the effective module access for a user.
     */
    public function getModuleAccess(int $userId, int $moduleId): ModuleAccess
    {
        if ($this->isAdmin($userId)) {
            return ModuleAccess::fullAccess();
        }

        return $this->modulePermissionRepository->getUserModuleAccess($userId, $moduleId)
            ?? ModuleAccess::none();
    }

    /**
     * Get the record access level for a user on a module.
     */
    public function getRecordAccessLevel(int $userId, int $moduleId): RecordAccessLevel
    {
        if ($this->isAdmin($userId)) {
            return RecordAccessLevel::ALL;
        }

        $access = $this->modulePermissionRepository->getUserModuleAccess($userId, $moduleId);

        return $access?->recordAccessLevel ?? RecordAccessLevel::NONE;
    }

    /**
     * Check if user can view a specific record.
     */
    public function canViewRecord(int $userId, int $moduleId, int $recordOwnerId, ?int $teamId = null): bool
    {
        if ($this->isAdmin($userId)) {
            return true;
        }

        $access = $this->getModuleAccess($userId, $moduleId);

        if (!$access->canView) {
            return false;
        }

        return $this->checkRecordAccess($userId, $access->recordAccessLevel, $recordOwnerId, $teamId);
    }

    /**
     * Check if user can edit a specific record.
     */
    public function canEditRecord(int $userId, int $moduleId, int $recordOwnerId, ?int $teamId = null): bool
    {
        if ($this->isAdmin($userId)) {
            return true;
        }

        $access = $this->getModuleAccess($userId, $moduleId);

        if (!$access->canEdit) {
            return false;
        }

        return $this->checkRecordAccess($userId, $access->recordAccessLevel, $recordOwnerId, $teamId);
    }

    /**
     * Check if user can delete a specific record.
     */
    public function canDeleteRecord(int $userId, int $moduleId, int $recordOwnerId, ?int $teamId = null): bool
    {
        if ($this->isAdmin($userId)) {
            return true;
        }

        $access = $this->getModuleAccess($userId, $moduleId);

        if (!$access->canDelete) {
            return false;
        }

        return $this->checkRecordAccess($userId, $access->recordAccessLevel, $recordOwnerId, $teamId);
    }

    /**
     * Get the restricted fields for a user on a module.
     *
     * @return array<string>
     */
    public function getRestrictedFields(int $userId, int $moduleId): array
    {
        if ($this->isAdmin($userId)) {
            return [];
        }

        return $this->modulePermissionRepository->getRestrictedFields($userId, $moduleId);
    }

    /**
     * Filter record data by removing restricted fields.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function filterRecordData(int $userId, int $moduleId, array $data): array
    {
        $restrictedFields = $this->getRestrictedFields($userId, $moduleId);

        if (empty($restrictedFields)) {
            return $data;
        }

        return array_diff_key($data, array_flip($restrictedFields));
    }

    /**
     * Get all permissions for a user.
     *
     * @return array<string>
     */
    public function getUserPermissions(int $userId): array
    {
        return $this->roleRepository->getUserPermissions($userId);
    }

    /**
     * Get all role IDs for a user.
     *
     * @return array<int>
     */
    public function getUserRoleIds(int $userId): array
    {
        return $this->roleRepository->getUserRoleIds($userId);
    }

    /**
     * Get all roles for a user.
     *
     * @return array<int, array>
     */
    public function getUserRoles(int $userId): array
    {
        return $this->roleRepository->getUserRoles($userId);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(int $userId, string $roleName): bool
    {
        return $this->roleRepository->userHasRoleByName($userId, $roleName);
    }

    /**
     * Get the complete permission matrix for a user.
     *
     * @return array{
     *     user_id: int,
     *     is_admin: bool,
     *     roles: array<string>,
     *     system_permissions: array<string>,
     *     module_permissions: array<int, array>,
     * }
     */
    public function getPermissionMatrix(int $userId): array
    {
        $isAdmin = $this->isAdmin($userId);
        $roles = $this->roleRepository->getUserRoles($userId);
        $permissions = $this->roleRepository->getUserPermissions($userId);
        $modulePermissions = $this->modulePermissionRepository->findByUserId($userId);

        return [
            'user_id' => $userId,
            'is_admin' => $isAdmin,
            'roles' => array_column($roles, 'name'),
            'system_permissions' => $permissions,
            'module_permissions' => $modulePermissions,
        ];
    }

    /**
     * Check if user can access a record based on access level.
     */
    private function checkRecordAccess(
        int $userId,
        RecordAccessLevel $accessLevel,
        int $recordOwnerId,
        ?int $teamId = null,
    ): bool {
        return match ($accessLevel) {
            RecordAccessLevel::ALL => true,
            RecordAccessLevel::TEAM => $this->isInSameTeam($userId, $recordOwnerId, $teamId),
            RecordAccessLevel::OWN => $userId === $recordOwnerId,
            RecordAccessLevel::NONE => false,
        };
    }

    /**
     * Check if two users are in the same team.
     * TODO: Implement team logic when teams are added.
     */
    private function isInSameTeam(int $userId, int $otherUserId, ?int $teamId = null): bool
    {
        // For now, team access falls back to own access
        // This should be implemented when team functionality is added
        return $userId === $otherUserId;
    }
}
