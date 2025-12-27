<?php

declare(strict_types=1);

namespace App\Application\Services\ApiKey;

use App\Domain\ApiKey\Entities\ApiKey;
use App\Domain\ApiKey\Repositories\ApiKeyRepositoryInterface;
use App\Domain\ApiKey\ValueObjects\ApiKeyId;
use App\Domain\ApiKey\ValueObjects\ApiKeyScopes;
use App\Domain\ApiKey\ValueObjects\IpWhitelist;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Infrastructure\ApiKey\CachedApiKeyService;
use DateTimeImmutable;
use DomainException;

/**
 * Application service for API key management.
 * Orchestrates API key CRUD, validation, and rate limiting.
 */
class ApiKeyApplicationService
{
    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeyRepository,
        private readonly CachedApiKeyService $cachedApiKeyService,
    ) {}

    // ========== CRUD OPERATIONS ==========

    /**
     * Get all API keys for a user.
     *
     * @return array<int, array>
     */
    public function getUserApiKeys(int $userId): array
    {
        return $this->apiKeyRepository->findByUserId($userId);
    }

    /**
     * Get API keys with pagination.
     */
    public function getApiKeysPaginated(array $filters, int $perPage = 25, int $page = 1): PaginatedResult
    {
        return $this->apiKeyRepository->findWithFilters($filters, $perPage, $page);
    }

    /**
     * Get a single API key.
     */
    public function getApiKey(int $id): ?array
    {
        return $this->apiKeyRepository->findById($id);
    }

    /**
     * Create a new API key.
     *
     * @param array{
     *     name: string,
     *     user_id: int,
     *     scopes?: array<string>,
     *     ip_whitelist?: array<string>,
     *     rate_limit?: int,
     *     expires_at?: string|DateTimeImmutable,
     * } $data
     *
     * @return array{api_key: array, plain_key: string}
     */
    public function createApiKey(array $data): array
    {
        // Validate name is unique for user
        if ($this->apiKeyRepository->nameExistsForUser($data['name'], $data['user_id'])) {
            throw new DomainException("API key name '{$data['name']}' already exists for this user");
        }

        // Parse expiry date
        $expiresAt = null;
        if (!empty($data['expires_at'])) {
            $expiresAt = $data['expires_at'] instanceof DateTimeImmutable
                ? $data['expires_at']
                : new DateTimeImmutable($data['expires_at']);
        }

        // Create the API key entity
        $result = ApiKey::create(
            name: $data['name'],
            userId: $data['user_id'],
            scopes: isset($data['scopes']) ? ApiKeyScopes::fromArray($data['scopes']) : null,
            ipWhitelist: isset($data['ip_whitelist']) ? IpWhitelist::fromArray($data['ip_whitelist']) : null,
            rateLimit: $data['rate_limit'] ?? null,
            expiresAt: $expiresAt,
        );

        $savedKey = $this->apiKeyRepository->saveEntity($result['entity']);

        return [
            'api_key' => $savedKey->toArray(),
            'plain_key' => $result['plainKey'],
        ];
    }

    /**
     * Update an API key.
     *
     * @param array{
     *     name?: string,
     *     scopes?: array<string>,
     *     ip_whitelist?: array<string>,
     *     rate_limit?: int|null,
     *     expires_at?: string|DateTimeImmutable|null,
     *     is_active?: bool,
     * } $data
     */
    public function updateApiKey(int $id, int $userId, array $data): array
    {
        $apiKey = $this->apiKeyRepository->findEntityById(ApiKeyId::fromInt($id));

        if ($apiKey === null) {
            throw new DomainException('API key not found');
        }

        // Ensure user owns this key
        if ($apiKey->getUserId() !== $userId) {
            throw new DomainException('API key not found');
        }

        // Check name uniqueness if changing
        if (isset($data['name']) && $data['name'] !== $apiKey->getName()) {
            if ($this->apiKeyRepository->nameExistsForUser($data['name'], $userId, $id)) {
                throw new DomainException("API key name '{$data['name']}' already exists for this user");
            }
            $apiKey = $apiKey->withName($data['name']);
        }

        // Update scopes
        if (isset($data['scopes'])) {
            $apiKey = $apiKey->withScopes(ApiKeyScopes::fromArray($data['scopes']));
        }

        // Update IP whitelist
        if (isset($data['ip_whitelist'])) {
            $apiKey = $apiKey->withIpWhitelist(IpWhitelist::fromArray($data['ip_whitelist']));
        }

        // Update rate limit
        if (array_key_exists('rate_limit', $data)) {
            $apiKey = $apiKey->withRateLimit($data['rate_limit']);
        }

        // Update active status
        if (isset($data['is_active'])) {
            $apiKey = $data['is_active'] ? $apiKey->activate() : $apiKey->deactivate();
        }

        $savedKey = $this->apiKeyRepository->saveEntity($apiKey);

        // Invalidate cache
        $this->cachedApiKeyService->invalidateById($id);

        return $savedKey->toArray();
    }

    /**
     * Delete an API key.
     */
    public function deleteApiKey(int $id, int $userId): bool
    {
        $apiKey = $this->apiKeyRepository->findById($id);

        if ($apiKey === null) {
            throw new DomainException('API key not found');
        }

        // Ensure user owns this key
        if ($apiKey['user_id'] !== $userId) {
            throw new DomainException('API key not found');
        }

        // Invalidate cache before deletion
        $this->cachedApiKeyService->invalidateById($id);

        return $this->apiKeyRepository->delete($id);
    }

    /**
     * Activate an API key.
     */
    public function activateApiKey(int $id, int $userId): array
    {
        return $this->updateApiKey($id, $userId, ['is_active' => true]);
    }

    /**
     * Deactivate an API key.
     */
    public function deactivateApiKey(int $id, int $userId): array
    {
        return $this->updateApiKey($id, $userId, ['is_active' => false]);
    }

    /**
     * Regenerate an API key (creates new key with same settings).
     *
     * @return array{api_key: array, plain_key: string}
     */
    public function regenerateApiKey(int $id, int $userId): array
    {
        $apiKey = $this->apiKeyRepository->findEntityById(ApiKeyId::fromInt($id));

        if ($apiKey === null) {
            throw new DomainException('API key not found');
        }

        // Ensure user owns this key
        if ($apiKey->getUserId() !== $userId) {
            throw new DomainException('API key not found');
        }

        // Delete old key
        $this->cachedApiKeyService->invalidateById($id);
        $this->apiKeyRepository->delete($id);

        // Create new key with same settings
        $result = ApiKey::create(
            name: $apiKey->getName(),
            userId: $apiKey->getUserId(),
            scopes: $apiKey->getScopes(),
            ipWhitelist: $apiKey->getIpWhitelist(),
            rateLimit: $apiKey->getRateLimit(),
            expiresAt: $apiKey->getExpiresAt(),
        );

        $savedKey = $this->apiKeyRepository->saveEntity($result['entity']);

        return [
            'api_key' => $savedKey->toArray(),
            'plain_key' => $result['plainKey'],
        ];
    }

    // ========== VALIDATION (delegated to cached service) ==========

    /**
     * Validate an API key for a request.
     *
     * @throws DomainException If validation fails
     */
    public function validateApiKey(string $plainKey, string $ip, ?string $requiredScope = null): ApiKey
    {
        return $this->cachedApiKeyService->validate($plainKey, $ip, $requiredScope);
    }

    /**
     * Validate an API key with rate limit check.
     *
     * @throws DomainException If validation or rate limit check fails
     */
    public function validateApiKeyWithRateLimit(string $plainKey, string $ip, ?string $requiredScope = null): ApiKey
    {
        return $this->cachedApiKeyService->validateWithRateLimit($plainKey, $ip, $requiredScope);
    }

    /**
     * Record an API request for logging and rate limiting.
     */
    public function recordRequest(
        ApiKey $apiKey,
        string $endpoint,
        string $method,
        string $ip,
        int $responseCode,
    ): void {
        $this->cachedApiKeyService->recordRequest($apiKey, $endpoint, $method, $ip, $responseCode);
    }

    /**
     * Extract API key from request headers/query.
     */
    public function extractKeyFromRequest(
        ?string $authorizationHeader,
        ?string $apiKeyHeader,
        ?string $queryParam,
    ): ?string {
        return $this->cachedApiKeyService->extractKeyFromRequest(
            $authorizationHeader,
            $apiKeyHeader,
            $queryParam,
        );
    }

    // ========== MAINTENANCE ==========

    /**
     * Get expired API keys.
     *
     * @return array<int, array>
     */
    public function getExpiredKeys(): array
    {
        return $this->apiKeyRepository->findExpired();
    }

    /**
     * Delete all expired API keys.
     */
    public function deleteExpiredKeys(): int
    {
        $count = $this->apiKeyRepository->deleteExpired();

        // Invalidate all API key caches
        $this->cachedApiKeyService->invalidateAll();

        return $count;
    }

    /**
     * Get the number of API keys for a user.
     */
    public function countUserApiKeys(int $userId): int
    {
        return $this->apiKeyRepository->countByUserId($userId);
    }
}
