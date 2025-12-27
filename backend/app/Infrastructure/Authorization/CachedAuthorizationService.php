<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization;

use App\Domain\Authorization\Repositories\ModulePermissionRepositoryInterface;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Authorization\Services\AuthorizationService;
use App\Domain\Authorization\ValueObjects\ModuleAccess;
use App\Domain\Authorization\ValueObjects\RecordAccessLevel;
use Illuminate\Support\Facades\Redis;

/**
 * Cached decorator for AuthorizationService.
 * Uses Redis for caching permission checks.
 */
class CachedAuthorizationService
{
    // Security: Reduced cache TTL for faster permission revocation
    private const USER_PERMISSIONS_TTL = 60; // 1 minute (reduced from 5 for security)
    private const USER_ROLES_TTL = 60; // 1 minute
    private const MODULE_ACCESS_TTL = 60; // 1 minute
    private const ADMIN_CHECK_TTL = 60; // 1 minute

    private AuthorizationService $authService;

    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly ModulePermissionRepositoryInterface $modulePermissionRepository,
    ) {
        $this->authService = new AuthorizationService($roleRepository, $modulePermissionRepository);
    }

    /**
     * Check if user has a specific system permission.
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        // Check admin status first (cached)
        if ($this->isAdmin($userId)) {
            return true;
        }

        $permissions = $this->getUserPermissions($userId);

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

        $userPermissions = $this->getUserPermissions($userId);

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

        $userPermissions = $this->getUserPermissions($userId);

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
        $key = "auth:user:{$userId}:is_admin";

        $cached = Redis::get($key);
        if ($cached !== null) {
            return $cached === '1';
        }

        $isAdmin = $this->roleRepository->userIsAdmin($userId);
        Redis::setex($key, self::ADMIN_CHECK_TTL, $isAdmin ? '1' : '0');

        return $isAdmin;
    }

    /**
     * Check if user can perform an action on a module.
     */
    public function canAccessModule(int $userId, int $moduleId, string $action): bool
    {
        if ($this->isAdmin($userId)) {
            return true;
        }

        $access = $this->getModuleAccess($userId, $moduleId);

        return $access->can($action);
    }

    /**
     * Get the effective module access for a user.
     */
    public function getModuleAccess(int $userId, int $moduleId): ModuleAccess
    {
        if ($this->isAdmin($userId)) {
            return ModuleAccess::fullAccess();
        }

        $key = "auth:user:{$userId}:module:{$moduleId}";

        $cached = Redis::get($key);
        if ($cached !== null) {
            $data = json_decode($cached, true);

            return ModuleAccess::fromArray($data);
        }

        $access = $this->modulePermissionRepository->getUserModuleAccess($userId, $moduleId)
            ?? ModuleAccess::none();

        Redis::setex($key, self::MODULE_ACCESS_TTL, json_encode($access->toArray()));

        return $access;
    }

    /**
     * Get the record access level for a user on a module.
     */
    public function getRecordAccessLevel(int $userId, int $moduleId): RecordAccessLevel
    {
        if ($this->isAdmin($userId)) {
            return RecordAccessLevel::ALL;
        }

        $access = $this->getModuleAccess($userId, $moduleId);

        return $access->recordAccessLevel;
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

        $access = $this->getModuleAccess($userId, $moduleId);

        return $access->restrictedFields;
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
     * Get all permissions for a user (cached).
     *
     * @return array<string>
     */
    public function getUserPermissions(int $userId): array
    {
        $key = "auth:user:{$userId}:permissions";

        $cached = Redis::get($key);
        if ($cached !== null) {
            return json_decode($cached, true);
        }

        $permissions = $this->roleRepository->getUserPermissions($userId);
        Redis::setex($key, self::USER_PERMISSIONS_TTL, json_encode($permissions));

        return $permissions;
    }

    /**
     * Get all role IDs for a user (cached).
     *
     * @return array<int>
     */
    public function getUserRoleIds(int $userId): array
    {
        $key = "auth:user:{$userId}:roles";

        $cached = Redis::get($key);
        if ($cached !== null) {
            return json_decode($cached, true);
        }

        $roleIds = $this->roleRepository->getUserRoleIds($userId);
        Redis::setex($key, self::USER_ROLES_TTL, json_encode($roleIds));

        return $roleIds;
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
     */
    public function getPermissionMatrix(int $userId): array
    {
        return $this->authService->getPermissionMatrix($userId);
    }

    // ========== CACHE INVALIDATION ==========

    /**
     * Invalidate all cached data for a user.
     */
    public function invalidateUser(int $userId): void
    {
        $keys = Redis::keys("auth:user:{$userId}:*");
        if (!empty($keys)) {
            Redis::del(...$keys);
        }
    }

    /**
     * Invalidate cached module access for a user.
     */
    public function invalidateUserModuleAccess(int $userId, int $moduleId): void
    {
        Redis::del("auth:user:{$userId}:module:{$moduleId}");
    }

    /**
     * Invalidate cached data for all users with a specific role.
     */
    public function invalidateRole(int $roleId): void
    {
        // Get all users with this role and invalidate their caches
        $userIds = $this->roleRepository->getUserRoleIds($roleId);
        foreach ($userIds as $userId) {
            $this->invalidateUser($userId);
        }
    }

    /**
     * Invalidate all cached authorization data.
     */
    public function invalidateAll(): void
    {
        $keys = Redis::keys('auth:*');
        if (!empty($keys)) {
            Redis::del(...$keys);
        }
    }

    // ========== PRIVATE HELPERS ==========

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

    private function isInSameTeam(int $userId, int $otherUserId, ?int $teamId = null): bool
    {
        // For now, team access falls back to own access
        // TODO: Implement team logic when teams are added
        return $userId === $otherUserId;
    }
}
