<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Repositories;

use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface RoleRepositoryInterface
{
    // ========== ENTITY-BASED (Domain operations) ==========

    public function findEntityById(RoleId $id): ?Role;

    public function findEntityByName(string $name): ?Role;

    public function saveEntity(Role $role): Role;

    public function deleteEntity(RoleId $id): bool;

    // ========== ARRAY-BASED (Application layer queries) ==========

    public function findById(int $id): ?array;

    public function findByName(string $name): ?array;

    public function findByIdWithPermissions(int $id): ?array;

    /**
     * @return array<int, array>
     */
    public function findAll(): array;

    public function findWithFilters(array $filters, int $perPage = 25, int $page = 1): PaginatedResult;

    public function create(array $data): array;

    public function update(int $id, array $data): array;

    public function delete(int $id): bool;

    // ========== PERMISSION MANAGEMENT ==========

    /**
     * Get all permission names for a role.
     *
     * @return array<string>
     */
    public function getRolePermissions(int $roleId): array;

    /**
     * Sync permissions for a role.
     *
     * @param array<string> $permissionNames
     */
    public function syncPermissions(int $roleId, array $permissionNames): void;

    /**
     * Grant a permission to a role.
     */
    public function grantPermission(int $roleId, string $permissionName): void;

    /**
     * Revoke a permission from a role.
     */
    public function revokePermission(int $roleId, string $permissionName): void;

    // ========== USER ROLE QUERIES ==========

    /**
     * Get all permission names for a user (aggregated from all roles).
     *
     * @return array<string>
     */
    public function getUserPermissions(int $userId): array;

    /**
     * Get all role IDs for a user.
     *
     * @return array<int>
     */
    public function getUserRoleIds(int $userId): array;

    /**
     * Get all roles for a user.
     *
     * @return array<int, array>
     */
    public function getUserRoles(int $userId): array;

    /**
     * Check if user has a specific role.
     */
    public function userHasRole(int $userId, int $roleId): bool;

    /**
     * Check if user has a specific role by name.
     */
    public function userHasRoleByName(int $userId, string $roleName): bool;

    /**
     * Check if user has admin role.
     */
    public function userIsAdmin(int $userId): bool;

    // ========== PERMISSION QUERIES ==========

    /**
     * Get all permissions in the system.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getAllPermissions(): array;

    // ========== UTILITY ==========

    public function exists(int $id): bool;

    public function nameExists(string $name, ?int $excludeId = null): bool;

    public function count(): int;

    /**
     * Get count of users with this role.
     */
    public function getUserCount(int $roleId): int;
}
