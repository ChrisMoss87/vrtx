<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Entities;

use App\Domain\Tenancy\ValueObjects\TenantId;
use DateTimeImmutable;

final class Tenant
{
    /**
     * @param array<string, mixed> $data
     * @param array<Domain> $domains
     */
    public function __construct(
        private readonly TenantId $id,
        private readonly array $data,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private array $domains = [],
    ) {}

    public function id(): TenantId
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
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
     * @return array<Domain>
     */
    public function domains(): array
    {
        return $this->domains;
    }

    public function addDomain(Domain $domain): void
    {
        $this->domains[] = $domain;
    }

    public function databaseName(): string
    {
        $prefix = config('tenancy.database.prefix', 'tenant');
        $suffix = config('tenancy.database.suffix', '');

        return $prefix . $this->id->value() . $suffix;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabase(array $row): self
    {
        $data = $row['data'] ?? '{}';
        if (is_string($data)) {
            $data = json_decode($data, true) ?? [];
        }

        return new self(
            id: new TenantId($row['id']),
            data: $data,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
