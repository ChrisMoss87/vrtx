<?php

declare(strict_types=1);

namespace App\Domain\ApiKey\Entities;

use App\Domain\ApiKey\ValueObjects\ApiKeyHash;
use App\Domain\ApiKey\ValueObjects\ApiKeyId;
use App\Domain\ApiKey\ValueObjects\ApiKeyScopes;
use App\Domain\ApiKey\ValueObjects\IpWhitelist;
use DateTimeImmutable;
use DomainException;

final class ApiKey
{
    private function __construct(
        private ?ApiKeyId $id,
        private string $name,
        private ApiKeyHash $keyHash,
        private int $userId,
        private ApiKeyScopes $scopes,
        private IpWhitelist $ipWhitelist,
        private ?int $rateLimit,
        private ?DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $lastUsedAt,
        private bool $isActive,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Create a new API key and return both the entity and the plain key.
     *
     * @return array{entity: self, plainKey: string}
     */
    public static function create(
        string $name,
        int $userId,
        ?ApiKeyScopes $scopes = null,
        ?IpWhitelist $ipWhitelist = null,
        ?int $rateLimit = null,
        ?DateTimeImmutable $expiresAt = null,
    ): array {
        $keyData = ApiKeyHash::generate();
        $now = new DateTimeImmutable();

        $entity = new self(
            id: null,
            name: $name,
            keyHash: $keyData['hash'],
            userId: $userId,
            scopes: $scopes ?? ApiKeyScopes::all(),
            ipWhitelist: $ipWhitelist ?? IpWhitelist::allowAll(),
            rateLimit: $rateLimit,
            expiresAt: $expiresAt,
            lastUsedAt: null,
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
        );

        return [
            'entity' => $entity,
            'plainKey' => $keyData['plain'],
        ];
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $keyHash,
        int $userId,
        array $scopes,
        array $ipWhitelist,
        ?int $rateLimit,
        ?DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $lastUsedAt,
        bool $isActive,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: ApiKeyId::fromInt($id),
            name: $name,
            keyHash: ApiKeyHash::fromHash($keyHash),
            userId: $userId,
            scopes: ApiKeyScopes::fromArray($scopes),
            ipWhitelist: IpWhitelist::fromArray($ipWhitelist),
            rateLimit: $rateLimit,
            expiresAt: $expiresAt,
            lastUsedAt: $lastUsedAt,
            isActive: $isActive,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function getId(): ?ApiKeyId
    {
        return $this->id;
    }

    public function getIdValue(): ?int
    {
        return $this->id?->value();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKeyHash(): ApiKeyHash
    {
        return $this->keyHash;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getScopes(): ApiKeyScopes
    {
        return $this->scopes;
    }

    public function getIpWhitelist(): IpWhitelist
    {
        return $this->ipWhitelist;
    }

    public function getRateLimit(): ?int
    {
        return $this->rateLimit;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Check if the API key is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    /**
     * Check if the API key is valid (active and not expired).
     */
    public function isValid(): bool
    {
        return $this->isActive && !$this->isExpired();
    }

    /**
     * Verify that a plain text key matches this API key.
     */
    public function verify(string $plainKey): bool
    {
        return $this->keyHash->verify($plainKey);
    }

    /**
     * Check if this API key has a specific scope.
     */
    public function hasScope(string $scope): bool
    {
        return $this->scopes->has($scope);
    }

    /**
     * Check if an IP address is allowed.
     */
    public function allowsIp(string $ip): bool
    {
        return $this->ipWhitelist->allows($ip);
    }

    /**
     * Check if rate limiting is enabled.
     */
    public function hasRateLimit(): bool
    {
        return $this->rateLimit !== null && $this->rateLimit > 0;
    }

    /**
     * Mark the key as used and return a new instance.
     */
    public function markAsUsed(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            keyHash: $this->keyHash,
            userId: $this->userId,
            scopes: $this->scopes,
            ipWhitelist: $this->ipWhitelist,
            rateLimit: $this->rateLimit,
            expiresAt: $this->expiresAt,
            lastUsedAt: new DateTimeImmutable(),
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withName(string $name): self
    {
        return new self(
            id: $this->id,
            name: $name,
            keyHash: $this->keyHash,
            userId: $this->userId,
            scopes: $this->scopes,
            ipWhitelist: $this->ipWhitelist,
            rateLimit: $this->rateLimit,
            expiresAt: $this->expiresAt,
            lastUsedAt: $this->lastUsedAt,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withScopes(ApiKeyScopes $scopes): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            keyHash: $this->keyHash,
            userId: $this->userId,
            scopes: $scopes,
            ipWhitelist: $this->ipWhitelist,
            rateLimit: $this->rateLimit,
            expiresAt: $this->expiresAt,
            lastUsedAt: $this->lastUsedAt,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withIpWhitelist(IpWhitelist $ipWhitelist): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            keyHash: $this->keyHash,
            userId: $this->userId,
            scopes: $this->scopes,
            ipWhitelist: $ipWhitelist,
            rateLimit: $this->rateLimit,
            expiresAt: $this->expiresAt,
            lastUsedAt: $this->lastUsedAt,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withRateLimit(?int $rateLimit): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            keyHash: $this->keyHash,
            userId: $this->userId,
            scopes: $this->scopes,
            ipWhitelist: $this->ipWhitelist,
            rateLimit: $rateLimit,
            expiresAt: $this->expiresAt,
            lastUsedAt: $this->lastUsedAt,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function activate(): self
    {
        if ($this->isActive) {
            return $this;
        }

        return new self(
            id: $this->id,
            name: $this->name,
            keyHash: $this->keyHash,
            userId: $this->userId,
            scopes: $this->scopes,
            ipWhitelist: $this->ipWhitelist,
            rateLimit: $this->rateLimit,
            expiresAt: $this->expiresAt,
            lastUsedAt: $this->lastUsedAt,
            isActive: true,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function deactivate(): self
    {
        if (!$this->isActive) {
            return $this;
        }

        return new self(
            id: $this->id,
            name: $this->name,
            keyHash: $this->keyHash,
            userId: $this->userId,
            scopes: $this->scopes,
            ipWhitelist: $this->ipWhitelist,
            rateLimit: $this->rateLimit,
            expiresAt: $this->expiresAt,
            lastUsedAt: $this->lastUsedAt,
            isActive: false,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Validate the API key for a request.
     *
     * @throws DomainException
     */
    public function validateForRequest(string $ip, ?string $requiredScope = null): void
    {
        if (!$this->isActive) {
            throw new DomainException('API key is inactive');
        }

        if ($this->isExpired()) {
            throw new DomainException('API key has expired');
        }

        if (!$this->allowsIp($ip)) {
            throw new DomainException('IP address is not allowed');
        }

        if ($requiredScope !== null && !$this->hasScope($requiredScope)) {
            throw new DomainException("API key does not have required scope: {$requiredScope}");
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id?->value(),
            'name' => $this->name,
            'user_id' => $this->userId,
            'scopes' => $this->scopes->toArray(),
            'ip_whitelist' => $this->ipWhitelist->toArray(),
            'rate_limit' => $this->rateLimit,
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'last_used_at' => $this->lastUsedAt?->format('Y-m-d H:i:s'),
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
