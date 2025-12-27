<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Repositories;

use App\Domain\Authorization\Entities\ModulePermission;
use App\Domain\Authorization\ValueObjects\ModuleAccess;
use App\Domain\Authorization\ValueObjects\RoleId;

interface ModulePermissionRepositoryInterface
{
    // ========== ENTITY-BASED (Domain operations) ==========

    public function findEntity(RoleId $roleId, int $moduleId): ?ModulePermission;

    public function saveEntity(ModulePermission $permission): ModulePermission;

    public function deleteEntity(RoleId $roleId, int $moduleId): bool;

    // ========== ARRAY-BASED (Application layer queries) ==========

    public function find(int $roleId, int $moduleId): ?array;

    /**
     * Get all module permissions for a role.
     *
     * @return array<int, array> Keyed by module_id
     */
    public function findByRoleId(int $roleId): array;

    /**
     * Get all module permissions for a user (aggregated from all roles).
     *
     * @return array<int, array> Keyed by module_id
     */
    public function findByUserId(int $userId): array;

    /**
     * Get the effective module access for a user (merged from all their roles).
     */
    public function getUserModuleAccess(int $userId, int $moduleId): ?ModuleAccess;

    /**
     * Get the effective module access for multiple modules at once.
     *
     * @param array<int> $moduleIds
     *
     * @return array<int, ModuleAccess> Keyed by module_id
     */
    public function getUserModuleAccessBulk(int $userId, array $moduleIds): array;

    /**
     * Create or update a module permission.
     */
    public function upsert(int $roleId, int $moduleId, array $data): array;

    /**
     * Delete a module permission.
     */
    public function delete(int $roleId, int $moduleId): bool;

    /**
     * Delete all module permissions for a role.
     */
    public function deleteByRoleId(int $roleId): int;

    /**
     * Delete all module permissions for a module.
     */
    public function deleteByModuleId(int $moduleId): int;

    // ========== BULK OPERATIONS ==========

    /**
     * Set module permissions for a role in bulk.
     *
     * @param array<int, array> $permissions Keyed by module_id
     */
    public function bulkUpsertForRole(int $roleId, array $permissions): void;

    /**
     * Copy module permissions from one role to another.
     */
    public function copyFromRole(int $sourceRoleId, int $targetRoleId): void;

    // ========== QUERY HELPERS ==========

    /**
     * Get restricted fields for a user on a module.
     *
     * @return array<string>
     */
    public function getRestrictedFields(int $userId, int $moduleId): array;

    /**
     * Check if user can perform action on module.
     */
    public function userCanAccessModule(int $userId, int $moduleId, string $action): bool;
}
