<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\Entities;

use App\Domain\Pipeline\ValueObjects\StageOutcome;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Stage Entity - represents a stage within a pipeline.
 *
 * Stages define the progression steps in a pipeline and can be
 * configured as win/loss stages for outcome tracking.
 */
final class Stage implements Entity
{
    /**
     * @param array<string, mixed> $settings
     */
    private function __construct(
        private ?int $id,
        private int $pipelineId,
        private string $name,
        private string $color,
        private int $probability,
        private int $displayOrder,
        private bool $isWonStage,
        private bool $isLostStage,
        private ?int $rottingDays,
        private array $settings,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    /**
     * Create a new Stage entity.
     *
     * @param array<string, mixed> $settings
     */
    public static function create(
        int $pipelineId,
        string $name,
        int $displayOrder = 0,
        string $color = '#6b7280',
        int $probability = 0,
        ?int $rottingDays = null,
        array $settings = [],
    ): self {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Stage name cannot be empty');
        }

        if ($probability < 0 || $probability > 100) {
            throw new InvalidArgumentException('Probability must be between 0 and 100');
        }

        if ($rottingDays !== null && $rottingDays < 0) {
            throw new InvalidArgumentException('Rotting days cannot be negative');
        }

        return new self(
            id: null,
            pipelineId: $pipelineId,
            name: trim($name),
            color: $color,
            probability: $probability,
            displayOrder: $displayOrder,
            isWonStage: false,
            isLostStage: false,
            rottingDays: $rottingDays,
            settings: $settings,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Create a won stage.
     *
     * @param array<string, mixed> $settings
     */
    public static function createWonStage(
        int $pipelineId,
        string $name,
        int $displayOrder,
        string $color = '#22c55e',
        array $settings = [],
    ): self {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Stage name cannot be empty');
        }

        return new self(
            id: null,
            pipelineId: $pipelineId,
            name: trim($name),
            color: $color,
            probability: 100,
            displayOrder: $displayOrder,
            isWonStage: true,
            isLostStage: false,
            rottingDays: null,
            settings: $settings,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Create a lost stage.
     *
     * @param array<string, mixed> $settings
     */
    public static function createLostStage(
        int $pipelineId,
        string $name,
        int $displayOrder,
        string $color = '#ef4444',
        array $settings = [],
    ): self {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Stage name cannot be empty');
        }

        return new self(
            id: null,
            pipelineId: $pipelineId,
            name: trim($name),
            color: $color,
            probability: 0,
            displayOrder: $displayOrder,
            isWonStage: false,
            isLostStage: true,
            rottingDays: null,
            settings: $settings,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Reconstitute a Stage entity from persistence.
     *
     * @param array<string, mixed> $settings
     */
    public static function reconstitute(
        int $id,
        int $pipelineId,
        string $name,
        string $color,
        int $probability,
        int $displayOrder,
        bool $isWonStage,
        bool $isLostStage,
        ?int $rottingDays,
        array $settings,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt = null,
    ): self {
        return new self(
            id: $id,
            pipelineId: $pipelineId,
            name: $name,
            color: $color,
            probability: $probability,
            displayOrder: $displayOrder,
            isWonStage: $isWonStage,
            isLostStage: $isLostStage,
            rottingDays: $rottingDays,
            settings: $settings,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPipelineId(): int
    {
        return $this->pipelineId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function getProbability(): int
    {
        return $this->probability;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function isWonStage(): bool
    {
        return $this->isWonStage;
    }

    public function isLostStage(): bool
    {
        return $this->isLostStage;
    }

    public function getRottingDays(): ?int
    {
        return $this->rottingDays;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Get a specific setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
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

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Check if two entities are the same based on identity.
     */
    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if ($this->id === null || $other->getId() === null) {
            return false;
        }

        return $this->id === $other->getId();
    }

    // =========================================================================
    // State Queries
    // =========================================================================

    /**
     * Get the outcome type for this stage.
     */
    public function getOutcome(): StageOutcome
    {
        if ($this->isWonStage) {
            return StageOutcome::Won;
        }
        if ($this->isLostStage) {
            return StageOutcome::Lost;
        }
        return StageOutcome::Open;
    }

    /**
     * Check if this is a terminal (closed) stage.
     */
    public function isTerminal(): bool
    {
        return $this->isWonStage || $this->isLostStage;
    }

    /**
     * Check if this is an open (active) stage.
     */
    public function isOpen(): bool
    {
        return !$this->isTerminal();
    }

    /**
     * Check if rotting tracking is enabled for this stage.
     */
    public function hasRottingTracking(): bool
    {
        return $this->rottingDays !== null && $this->rottingDays > 0;
    }

    /**
     * Check if a record is rotting based on days in this stage.
     */
    public function isRecordRotting(int $daysInStage): bool
    {
        if (!$this->hasRottingTracking()) {
            return false;
        }
        return $daysInStage > $this->rottingDays;
    }

    // =========================================================================
    // State Mutations (Immutable - return new instance)
    // =========================================================================

    /**
     * Update stage details.
     */
    public function updateDetails(
        string $name,
        string $color,
        int $probability,
    ): self {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Stage name cannot be empty');
        }

        if ($probability < 0 || $probability > 100) {
            throw new InvalidArgumentException('Probability must be between 0 and 100');
        }

        // Won stages must have 100% probability
        $finalProbability = $this->isWonStage ? 100 : $probability;
        // Lost stages must have 0% probability
        $finalProbability = $this->isLostStage ? 0 : $finalProbability;

        return new self(
            id: $this->id,
            pipelineId: $this->pipelineId,
            name: trim($name),
            color: $color,
            probability: $finalProbability,
            displayOrder: $this->displayOrder,
            isWonStage: $this->isWonStage,
            isLostStage: $this->isLostStage,
            rottingDays: $this->rottingDays,
            settings: $this->settings,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Update display order.
     */
    public function withDisplayOrder(int $order): self
    {
        if ($order < 0) {
            throw new InvalidArgumentException('Display order cannot be negative');
        }

        return new self(
            id: $this->id,
            pipelineId: $this->pipelineId,
            name: $this->name,
            color: $this->color,
            probability: $this->probability,
            displayOrder: $order,
            isWonStage: $this->isWonStage,
            isLostStage: $this->isLostStage,
            rottingDays: $this->rottingDays,
            settings: $this->settings,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Configure rotting days.
     */
    public function withRottingDays(?int $days): self
    {
        if ($days !== null && $days < 0) {
            throw new InvalidArgumentException('Rotting days cannot be negative');
        }

        // Terminal stages shouldn't have rotting
        if ($this->isTerminal()) {
            $days = null;
        }

        return new self(
            id: $this->id,
            pipelineId: $this->pipelineId,
            name: $this->name,
            color: $this->color,
            probability: $this->probability,
            displayOrder: $this->displayOrder,
            isWonStage: $this->isWonStage,
            isLostStage: $this->isLostStage,
            rottingDays: $days,
            settings: $this->settings,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Update settings.
     *
     * @param array<string, mixed> $settings
     */
    public function withSettings(array $settings): self
    {
        return new self(
            id: $this->id,
            pipelineId: $this->pipelineId,
            name: $this->name,
            color: $this->color,
            probability: $this->probability,
            displayOrder: $this->displayOrder,
            isWonStage: $this->isWonStage,
            isLostStage: $this->isLostStage,
            rottingDays: $this->rottingDays,
            settings: array_merge($this->settings, $settings),
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Mark this as a won stage.
     */
    public function markAsWonStage(): self
    {
        return new self(
            id: $this->id,
            pipelineId: $this->pipelineId,
            name: $this->name,
            color: $this->color,
            probability: 100,
            displayOrder: $this->displayOrder,
            isWonStage: true,
            isLostStage: false,
            rottingDays: null,
            settings: $this->settings,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Mark this as a lost stage.
     */
    public function markAsLostStage(): self
    {
        return new self(
            id: $this->id,
            pipelineId: $this->pipelineId,
            name: $this->name,
            color: $this->color,
            probability: 0,
            displayOrder: $this->displayOrder,
            isWonStage: false,
            isLostStage: true,
            rottingDays: null,
            settings: $this->settings,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Mark this as an open (regular) stage.
     */
    public function markAsOpenStage(): self
    {
        return new self(
            id: $this->id,
            pipelineId: $this->pipelineId,
            name: $this->name,
            color: $this->color,
            probability: $this->probability,
            displayOrder: $this->displayOrder,
            isWonStage: false,
            isLostStage: false,
            rottingDays: $this->rottingDays,
            settings: $this->settings,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Soft delete the stage.
     */
    public function delete(): self
    {
        if ($this->isDeleted()) {
            return $this;
        }

        return new self(
            id: $this->id,
            pipelineId: $this->pipelineId,
            name: $this->name,
            color: $this->color,
            probability: $this->probability,
            displayOrder: $this->displayOrder,
            isWonStage: $this->isWonStage,
            isLostStage: $this->isLostStage,
            rottingDays: $this->rottingDays,
            settings: $this->settings,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Restore a soft-deleted stage.
     */
    public function restore(): self
    {
        if (!$this->isDeleted()) {
            return $this;
        }

        return new self(
            id: $this->id,
            pipelineId: $this->pipelineId,
            name: $this->name,
            color: $this->color,
            probability: $this->probability,
            displayOrder: $this->displayOrder,
            isWonStage: $this->isWonStage,
            isLostStage: $this->isLostStage,
            rottingDays: $this->rottingDays,
            settings: $this->settings,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: null,
        );
    }
}
