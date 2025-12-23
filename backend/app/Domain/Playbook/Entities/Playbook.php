<?php

declare(strict_types=1);

namespace App\Domain\Playbook\Entities;

use App\Domain\Playbook\ValueObjects\PlaybookStatus;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

final class Playbook implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?string $description,
        private ?string $triggerModule,
        private ?string $triggerCondition,
        private ?array $triggerConfig,
        private ?int $estimatedDays,
        private PlaybookStatus $status,
        private bool $autoAssign,
        private ?int $defaultOwnerId,
        private array $tags,
        private int $displayOrder,
        private ?int $createdBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    public static function create(
        string $name,
        string $slug,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            slug: $slug,
            description: null,
            triggerModule: null,
            triggerCondition: null,
            triggerConfig: null,
            estimatedDays: null,
            status: PlaybookStatus::INACTIVE,
            autoAssign: false,
            defaultOwnerId: null,
            tags: [],
            displayOrder: 0,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        ?string $triggerModule,
        ?string $triggerCondition,
        ?array $triggerConfig,
        ?int $estimatedDays,
        PlaybookStatus $status,
        bool $autoAssign,
        ?int $defaultOwnerId,
        array $tags,
        int $displayOrder,
        ?int $createdBy,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            description: $description,
            triggerModule: $triggerModule,
            triggerCondition: $triggerCondition,
            triggerConfig: $triggerConfig,
            estimatedDays: $estimatedDays,
            status: $status,
            autoAssign: $autoAssign,
            defaultOwnerId: $defaultOwnerId,
            tags: $tags,
            displayOrder: $displayOrder,
            createdBy: $createdBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    public function update(
        string $name,
        ?string $description,
        ?string $triggerModule = null,
        ?string $triggerCondition = null,
        ?array $triggerConfig = null,
        ?int $estimatedDays = null,
    ): self {
        $clone = clone $this;
        $clone->name = $name;
        $clone->description = $description;
        $clone->triggerModule = $triggerModule;
        $clone->triggerCondition = $triggerCondition;
        $clone->triggerConfig = $triggerConfig;
        $clone->estimatedDays = $estimatedDays;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateSlug(string $slug): self
    {
        $clone = clone $this;
        $clone->slug = $slug;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function activate(): self
    {
        if ($this->status === PlaybookStatus::ACTIVE) {
            return $this;
        }

        $clone = clone $this;
        $clone->status = PlaybookStatus::ACTIVE;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function deactivate(): self
    {
        if ($this->status === PlaybookStatus::INACTIVE) {
            return $this;
        }

        $clone = clone $this;
        $clone->status = PlaybookStatus::INACTIVE;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function enableAutoAssign(?int $defaultOwnerId = null): self
    {
        $clone = clone $this;
        $clone->autoAssign = true;
        $clone->defaultOwnerId = $defaultOwnerId;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function disableAutoAssign(): self
    {
        $clone = clone $this;
        $clone->autoAssign = false;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateTags(array $tags): self
    {
        $clone = clone $this;
        $clone->tags = array_values(array_unique($tags));
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function addTag(string $tag): self
    {
        if (in_array($tag, $this->tags, true)) {
            return $this;
        }

        $clone = clone $this;
        $clone->tags[] = $tag;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function removeTag(string $tag): self
    {
        $index = array_search($tag, $this->tags, true);
        if ($index === false) {
            return $this;
        }

        $clone = clone $this;
        array_splice($clone->tags, $index, 1);
        $clone->tags = array_values($clone->tags);
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function updateDisplayOrder(int $order): self
    {
        $clone = clone $this;
        $clone->displayOrder = $order;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function delete(): self
    {
        if ($this->deletedAt !== null) {
            return $this;
        }

        $clone = clone $this;
        $clone->deletedAt = new DateTimeImmutable();
        return $clone;
    }

    public function restore(): self
    {
        if ($this->deletedAt === null) {
            return $this;
        }

        $clone = clone $this;
        $clone->deletedAt = null;
        $clone->updatedAt = new DateTimeImmutable();
        return $clone;
    }

    public function isActive(): bool
    {
        return $this->status === PlaybookStatus::ACTIVE && $this->deletedAt === null;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    public function canBeTriggeredFor(string $module): bool
    {
        return $this->isActive()
            && $this->triggerModule !== null
            && $this->triggerModule === $module;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getTriggerModule(): ?string
    {
        return $this->triggerModule;
    }

    public function getTriggerCondition(): ?string
    {
        return $this->triggerCondition;
    }

    public function getTriggerConfig(): ?array
    {
        return $this->triggerConfig;
    }

    public function getEstimatedDays(): ?int
    {
        return $this->estimatedDays;
    }

    public function getStatus(): PlaybookStatus
    {
        return $this->status;
    }

    public function isAutoAssign(): bool
    {
        return $this->autoAssign;
    }

    public function getDefaultOwnerId(): ?int
    {
        return $this->defaultOwnerId;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function equals(Entity $other): bool
    {
        return $other instanceof self
            && $this->id !== null
            && $this->id === $other->id;
    }
}
