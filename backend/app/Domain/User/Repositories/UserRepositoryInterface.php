<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Repository interface for User aggregate root.
 */
interface UserRepositoryInterface
{
    /**
     * Find a user by ID.
     */
    public function findById(int $id): ?array;

    /**
     * Find a user by ID with roles.
     */
    public function findByIdWithRoles(int $id): ?array;

    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): ?array;

    /**
     * List users with filtering and pagination.
     */
    public function list(
        ?string $search = null,
        ?string $role = null,
        ?bool $isActive = null,
        int $perPage = 25
    ): LengthAwarePaginator;

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

    /**
     * Sync roles for a user.
     *
     * @param array<int> $roleIds
     */
    public function syncRoles(int $id, array $roleIds): void;

    /**
     * Update user password.
     */
    public function updatePassword(int $id, string $hashedPassword): bool;

    /**
     * Toggle user active status.
     */
    public function toggleActive(int $id): bool;

    /**
     * Check if users table has is_active column.
     */
    public function hasActiveStatusColumn(): bool;
}
