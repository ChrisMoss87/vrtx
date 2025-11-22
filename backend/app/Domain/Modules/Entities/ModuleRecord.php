<?php

declare(strict_types=1);

namespace App\Domain\Modules\Entities;

use DateTimeImmutable;

final class ModuleRecord
{
    public function __construct(
        private ?int $id,
        private int $moduleId,
        private array $data,
        private ?int $createdBy,
        private ?int $updatedBy,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt = null,
        private ?DateTimeImmutable $deletedAt = null,
    ) {}

    public static function create(
        int $moduleId,
        array $data,
        int $createdBy
    ): self {
        return new self(
            id: null,
            moduleId: $moduleId,
            data: $data,
            createdBy: $createdBy,
            updatedBy: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function update(array $data, int $updatedBy): void
    {
        $this->data = array_merge($this->data, $data);
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateField(string $fieldName, mixed $value, int $updatedBy): void
    {
        $this->data[$fieldName] = $value;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function delete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getFieldValue(string $fieldName): mixed
    {
        return $this->data[$fieldName] ?? null;
    }

    // Getters
    public function id(): ?int
    {
        return $this->id;
    }

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function updatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
