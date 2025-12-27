<?php

declare(strict_types=1);

namespace App\Domain\ApiKey\Repositories;

use App\Domain\ApiKey\Entities\ApiKey;
use App\Domain\ApiKey\ValueObjects\ApiKeyId;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface ApiKeyRepositoryInterface
{
    // ========== ENTITY-BASED (Domain operations) ==========

    public function findEntityById(ApiKeyId $id): ?ApiKey;

    public function findEntityByHash(string $keyHash): ?ApiKey;

    public function saveEntity(ApiKey $apiKey): ApiKey;

    public function deleteEntity(ApiKeyId $id): bool;

    // ========== ARRAY-BASED (Application layer queries) ==========

    public function findById(int $id): ?array;

    public function findByHash(string $keyHash): ?array;

    /**
     * Find API key by verifying a plain text key.
     * Returns null if no matching key is found.
     */
    public function findByPlainKey(string $plainKey): ?array;

    /**
     * Get all API keys for a user.
     *
     * @return array<int, array>
     */
    public function findByUserId(int $userId): array;

    /**
     * List API keys with filtering and pagination.
     *
     * @param array{
     *     user_id?: int,
     *     is_active?: bool,
     *     search?: string,
     * } $filters
     */
    public function findWithFilters(array $filters, int $perPage = 25, int $page = 1): PaginatedResult;

    public function create(array $data): array;

    public function update(int $id, array $data): array;

    public function delete(int $id): bool;

    // ========== USAGE TRACKING ==========

    /**
     * Update the last_used_at timestamp for an API key.
     */
    public function updateLastUsed(int $id): void;

    /**
     * Log an API request.
     */
    public function logRequest(int $apiKeyId, string $endpoint, string $method, string $ip, int $responseCode): void;

    /**
     * Get request count for rate limiting.
     */
    public function getRequestCount(int $apiKeyId, int $windowSeconds = 60): int;

    /**
     * Increment request count for rate limiting.
     */
    public function incrementRequestCount(int $apiKeyId): void;

    // ========== UTILITY ==========

    public function exists(int $id): bool;

    /**
     * Check if name is already in use by a user.
     */
    public function nameExistsForUser(string $name, int $userId, ?int $excludeId = null): bool;

    /**
     * Count API keys for a user.
     */
    public function countByUserId(int $userId): int;

    /**
     * Get expired API keys.
     *
     * @return array<int, array>
     */
    public function findExpired(): array;

    /**
     * Delete expired API keys.
     */
    public function deleteExpired(): int;
}
