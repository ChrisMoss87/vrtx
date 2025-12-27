<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\Email;
use App\Domain\User\ValueObjects\UserId;

/**
 * Repository interface for User aggregate root.
 *
 * Following DDD patterns:
 * - Entity-based methods for domain operations
 * - Array-based methods for application layer queries (avoiding entity overhead)
 */
interface UserRepositoryInterface
{
    // ========== ENTITY-BASED (Domain operations) ==========

    /**
     * Find a user entity by ID.
     */
    public function findEntityById(UserId $id): ?User;

    /**
     * Find a user entity by email.
     */
    public function findEntityByEmail(Email $email): ?User;

    /**
     * Save a user entity (create or update).
     */
    public function saveEntity(User $user): User;

    /**
     * Delete a user entity.
     */
    public function deleteEntity(UserId $id): bool;

    // ========== ARRAY-BASED (Application layer queries) ==========

    /**
     * Find a user by ID.
     */
    public function findById(int $id): ?array;

    /**
     * Find a user by ID with roles loaded.
     */
    public function findByIdWithRoles(int $id): ?array;

    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): ?array;

    /**
     * List users with filtering and pagination.
     *
     * @param array{
     *     search?: string,
     *     role_id?: int,
     *     is_active?: bool,
     * } $filters
     */
    public function findWithFilters(array $filters, int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get users by role ID.
     *
     * @return array<int, array>
     */
    public function findByRoleId(int $roleId): array;

    /**
     * Get all user IDs that have a specific role.
     *
     * @return array<int>
     */
    public function getUserIdsByRoleId(int $roleId): array;

    /**
     * Create a new user.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): array;

    /**
     * Update a user.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): array;

    /**
     * Delete a user.
     */
    public function delete(int $id): bool;

    // ========== ROLE MANAGEMENT ==========

    /**
     * Sync roles for a user (replaces all existing roles).
     *
     * @param array<int> $roleIds
     */
    public function syncRoles(int $userId, array $roleIds): void;

    /**
     * Assign a role to a user.
     */
    public function assignRole(int $userId, int $roleId): void;

    /**
     * Remove a role from a user.
     */
    public function removeRole(int $userId, int $roleId): void;

    /**
     * Get role IDs for a user.
     *
     * @return array<int>
     */
    public function getUserRoleIds(int $userId): array;

    /**
     * Get roles for a user with details.
     *
     * @return array<int, array{id: int, name: string, display_name: ?string}>
     */
    public function getUserRoles(int $userId): array;

    /**
     * Check if user has a specific role.
     */
    public function hasRole(int $userId, int $roleId): bool;

    /**
     * Check if user has a specific role by name.
     */
    public function hasRoleByName(int $userId, string $roleName): bool;

    // ========== UTILITY METHODS ==========

    /**
     * Update user password.
     */
    public function updatePassword(int $id, string $hashedPassword): bool;

    /**
     * Toggle user active status.
     */
    public function toggleActive(int $id): bool;

    /**
     * Check if a user exists.
     */
    public function exists(int $id): bool;

    /**
     * Check if email is already in use.
     */
    public function emailExists(string $email, ?int $excludeId = null): bool;

    /**
     * Count total users.
     */
    public function count(): int;

    /**
     * Check if users table has is_active column.
     */
    public function hasActiveStatusColumn(): bool;

    /**
     * Search users by name or email.
     *
     * @return array<array{id: int, name: string, email: string}>
     */
    public function search(string $query, int $limit = 10): array;
}
