<?php

declare(strict_types=1);

namespace Domain\TenantManagement\Entities;

use Domain\TenantManagement\ValueObjects\TenantId;
use DateTimeImmutable;

final class Tenant
{
    private function __construct(
        private readonly TenantId $id,
        private string $name,
        private array $data,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        TenantId $id,
        string $name,
        array $data = []
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: $id,
            name: $name,
            data: $data,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function reconstitute(
        TenantId $id,
        string $name,
        array $data,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self(
            id: $id,
            name: $name,
            data: $data,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    public function id(): TenantId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function rename(string $newName): void
    {
        $this->name = $newName;
        $this->touch();
    }

    public function updateData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
