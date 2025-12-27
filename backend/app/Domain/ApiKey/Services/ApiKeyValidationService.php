<?php

declare(strict_types=1);

namespace App\Domain\ApiKey\Services;

use App\Domain\ApiKey\Entities\ApiKey;
use App\Domain\ApiKey\Repositories\ApiKeyRepositoryInterface;
use App\Domain\ApiKey\ValueObjects\ApiKeyHash;
use App\Domain\ApiKey\ValueObjects\ApiKeyId;
use DomainException;

/**
 * Domain service for API key validation logic.
 */
class ApiKeyValidationService
{
    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeyRepository,
    ) {}

    /**
     * Validate an API key and return the entity if valid.
     *
     * @throws DomainException If validation fails
     */
    public function validate(string $plainKey, string $ip, ?string $requiredScope = null): ApiKey
    {
        // Find the API key by hash
        $keyHash = ApiKeyHash::fromPlainKey($plainKey);
        $apiKey = $this->apiKeyRepository->findEntityByHash($keyHash->value());

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

        $count = $this->apiKeyRepository->getRequestCount($apiKey->getIdValue());

        if ($count >= $apiKey->getRateLimit()) {
            throw new DomainException('Rate limit exceeded');
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
            $this->apiKeyRepository->incrementRequestCount($apiKeyId);
        }

        // Log the request
        $this->apiKeyRepository->logRequest($apiKeyId, $endpoint, $method, $ip, $responseCode);
    }

    /**
     * Extract the API key from various sources in a request.
     * Returns null if no key is found.
     */
    public function extractKeyFromRequest(
        ?string $authorizationHeader,
        ?string $apiKeyHeader,
        ?string $queryParam,
    ): ?string {
        // Check Authorization header (Bearer token)
        if ($authorizationHeader !== null) {
            if (str_starts_with($authorizationHeader, 'Bearer ')) {
                return substr($authorizationHeader, 7);
            }
        }

        // Check X-API-Key header
        if ($apiKeyHeader !== null && !empty($apiKeyHeader)) {
            return $apiKeyHeader;
        }

        // Check query parameter
        if ($queryParam !== null && !empty($queryParam)) {
            return $queryParam;
        }

        return null;
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
        foreach ($scopes as $scope) {
            if (!$apiKey->hasScope($scope)) {
                throw new DomainException("API key missing required scope: {$scope}");
            }
        }
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
        foreach ($scopes as $scope) {
            if ($apiKey->hasScope($scope)) {
                return;
            }
        }

        throw new DomainException('API key missing required scopes: '.implode(', ', $scopes));
    }
}
