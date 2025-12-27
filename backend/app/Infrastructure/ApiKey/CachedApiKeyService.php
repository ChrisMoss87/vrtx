<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiKey;

use App\Domain\ApiKey\Entities\ApiKey;
use App\Domain\ApiKey\Repositories\ApiKeyRepositoryInterface;
use App\Domain\ApiKey\Services\ApiKeyValidationService;
use App\Domain\ApiKey\ValueObjects\ApiKeyHash;
use DomainException;
use Illuminate\Support\Facades\Redis;

/**
 * Cached decorator for ApiKeyValidationService.
 * Uses Redis for caching API key lookups and rate limiting.
 */
class CachedApiKeyService
{
    private const API_KEY_TTL = 300; // 5 minutes
    private const RATE_LIMIT_WINDOW = 60; // 1 minute

    private ApiKeyValidationService $validationService;

    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeyRepository,
    ) {
        $this->validationService = new ApiKeyValidationService($apiKeyRepository);
    }

    /**
     * Validate an API key and return the entity if valid.
     *
     * @throws DomainException If validation fails
     */
    public function validate(string $plainKey, string $ip, ?string $requiredScope = null): ApiKey
    {
        $apiKey = $this->findByPlainKey($plainKey);

        if ($apiKey === null) {
            throw new DomainException('Invalid API key');
        }

        // Validate the key for this request
        $apiKey->validateForRequest($ip, $requiredScope);

        return $apiKey;
    }

    /**
     * Validate an API key and check rate limit.
     *
     * @throws DomainException If validation or rate limit check fails
     */
    public function validateWithRateLimit(string $plainKey, string $ip, ?string $requiredScope = null): ApiKey
    {
        $apiKey = $this->validate($plainKey, $ip, $requiredScope);

        if ($apiKey->hasRateLimit()) {
            $this->checkRateLimit($apiKey);
        }

        return $apiKey;
    }

    /**
     * Find an API key by plain text key (cached).
     */
    public function findByPlainKey(string $plainKey): ?ApiKey
    {
        $hash = ApiKeyHash::fromPlainKey($plainKey);
        $cacheKey = "apikey:hash:{$hash->value()}";

        $cached = Redis::get($cacheKey);
        if ($cached !== null) {
            if ($cached === 'null') {
                return null;
            }

            $data = json_decode($cached, true);

            return $this->hydrateFromCache($data);
        }

        $apiKey = $this->apiKeyRepository->findEntityByHash($hash->value());

        if ($apiKey === null) {
            Redis::setex($cacheKey, self::API_KEY_TTL, 'null');

            return null;
        }

        Redis::setex($cacheKey, self::API_KEY_TTL, json_encode($this->serializeForCache($apiKey)));

        return $apiKey;
    }

    /**
     * Check if an API key has a specific scope.
     */
    public function hasScope(ApiKey $apiKey, string $scope): bool
    {
        return $apiKey->hasScope($scope);
    }

    /**
     * Check if an IP is allowed for an API key.
     */
    public function isIpAllowed(ApiKey $apiKey, string $ip): bool
    {
        return $apiKey->allowsIp($ip);
    }

    /**
     * Check if an API key is expired.
     */
    public function isExpired(ApiKey $apiKey): bool
    {
        return $apiKey->isExpired();
    }

    /**
     * Check if an API key is valid.
     */
    public function isValid(ApiKey $apiKey): bool
    {
        return $apiKey->isValid();
    }

    /**
     * Check rate limit for an API key.
     *
     * @throws DomainException If rate limit exceeded
     */
    public function checkRateLimit(ApiKey $apiKey): void
    {
        if (!$apiKey->hasRateLimit()) {
            return;
        }

        $apiKeyId = $apiKey->getIdValue();
        if ($apiKeyId === null) {
            return;
        }

        $key = "apikey:rate:{$apiKeyId}";
        $count = (int) Redis::get($key);

        if ($count >= $apiKey->getRateLimit()) {
            throw new DomainException('Rate limit exceeded');
        }
    }

    /**
     * Increment rate limit counter for an API key.
     */
    public function incrementRateLimit(ApiKey $apiKey): void
    {
        $apiKeyId = $apiKey->getIdValue();
        if ($apiKeyId === null || !$apiKey->hasRateLimit()) {
            return;
        }

        $key = "apikey:rate:{$apiKeyId}";
        $count = Redis::incr($key);

        // Set expiry on first increment
        if ($count === 1) {
            Redis::expire($key, self::RATE_LIMIT_WINDOW);
        }
    }

    /**
     * Record an API request for rate limiting and logging.
     */
    public function recordRequest(
        ApiKey $apiKey,
        string $endpoint,
        string $method,
        string $ip,
        int $responseCode,
    ): void {
        $apiKeyId = $apiKey->getIdValue();

        if ($apiKeyId === null) {
            return;
        }

        // Update last used timestamp
        $this->apiKeyRepository->updateLastUsed($apiKeyId);

        // Increment rate limit counter
        if ($apiKey->hasRateLimit()) {
            $this->incrementRateLimit($apiKey);
        }

        // Log the request
        $this->apiKeyRepository->logRequest($apiKeyId, $endpoint, $method, $ip, $responseCode);
    }

    /**
     * Extract the API key from various sources in a request.
     */
    public function extractKeyFromRequest(
        ?string $authorizationHeader,
        ?string $apiKeyHeader,
        ?string $queryParam,
    ): ?string {
        return $this->validationService->extractKeyFromRequest(
            $authorizationHeader,
            $apiKeyHeader,
            $queryParam,
        );
    }

    /**
     * Validate multiple scopes (all required).
     *
     * @param array<string> $scopes
     *
     * @throws DomainException If any scope is missing
     */
    public function validateScopes(ApiKey $apiKey, array $scopes): void
    {
        $this->validationService->validateScopes($apiKey, $scopes);
    }

    /**
     * Validate at least one scope is present.
     *
     * @param array<string> $scopes
     *
     * @throws DomainException If no scopes match
     */
    public function validateAnyScope(ApiKey $apiKey, array $scopes): void
    {
        $this->validationService->validateAnyScope($apiKey, $scopes);
    }

    // ========== CACHE INVALIDATION ==========

    /**
     * Invalidate cached API key by ID.
     */
    public function invalidateById(int $apiKeyId): void
    {
        // Get the key hash from the database
        $apiKey = $this->apiKeyRepository->findById($apiKeyId);
        if ($apiKey) {
            $hash = $apiKey['key'] ?? null;
            if ($hash) {
                Redis::del("apikey:hash:{$hash}");
            }
        }
    }

    /**
     * Invalidate cached API key by hash.
     */
    public function invalidateByHash(string $keyHash): void
    {
        Redis::del("apikey:hash:{$keyHash}");
    }

    /**
     * Invalidate all cached API keys.
     */
    public function invalidateAll(): void
    {
        $keys = Redis::keys('apikey:*');
        if (!empty($keys)) {
            Redis::del(...$keys);
        }
    }

    // ========== PRIVATE HELPERS ==========

    private function serializeForCache(ApiKey $apiKey): array
    {
        return [
            'id' => $apiKey->getIdValue(),
            'name' => $apiKey->getName(),
            'key_hash' => $apiKey->getKeyHash()->value(),
            'user_id' => $apiKey->getUserId(),
            'scopes' => $apiKey->getScopes()->toArray(),
            'ip_whitelist' => $apiKey->getIpWhitelist()->toArray(),
            'rate_limit' => $apiKey->getRateLimit(),
            'expires_at' => $apiKey->getExpiresAt()?->format('Y-m-d H:i:s'),
            'last_used_at' => $apiKey->getLastUsedAt()?->format('Y-m-d H:i:s'),
            'is_active' => $apiKey->isActive(),
            'created_at' => $apiKey->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $apiKey->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    private function hydrateFromCache(array $data): ApiKey
    {
        return ApiKey::reconstitute(
            id: $data['id'],
            name: $data['name'],
            keyHash: $data['key_hash'],
            userId: $data['user_id'],
            scopes: $data['scopes'],
            ipWhitelist: $data['ip_whitelist'],
            rateLimit: $data['rate_limit'],
            expiresAt: $data['expires_at'] ? new \DateTimeImmutable($data['expires_at']) : null,
            lastUsedAt: $data['last_used_at'] ? new \DateTimeImmutable($data['last_used_at']) : null,
            isActive: $data['is_active'],
            createdAt: new \DateTimeImmutable($data['created_at']),
            updatedAt: new \DateTimeImmutable($data['updated_at']),
        );
    }
}
