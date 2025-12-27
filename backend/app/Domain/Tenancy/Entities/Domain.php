<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Entities;

use App\Domain\Tenancy\ValueObjects\TenantId;
use DateTimeImmutable;

final class Domain
{
    public function __construct(
        private readonly int $id,
        private readonly string $domain,
        private readonly TenantId $tenantId,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Check if this domain matches the given hostname.
     * Supports both full domain and subdomain matching.
     */
    public function matches(string $hostname): bool
    {
        return strcasecmp($this->domain, $hostname) === 0;
    }

    /**
     * Extract subdomain from this domain given a base domain.
     */
    public function getSubdomain(string $baseDomain): ?string
    {
        $baseDomain = strtolower($baseDomain);
        $domain = strtolower($this->domain);

        if (!str_ends_with($domain, '.' . $baseDomain)) {
            return null;
        }

        return substr($domain, 0, -(strlen($baseDomain) + 1));
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabase(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            domain: $row['domain'],
            tenantId: new TenantId($row['tenant_id']),
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
