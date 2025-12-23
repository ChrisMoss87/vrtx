<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\Entities;

use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * StageHistory Entity - tracks stage transitions for records.
 *
 * Immutable record of when a record moved from one stage to another,
 * including who made the change and how long it was in the previous stage.
 */
final class StageHistory implements Entity
{
    private function __construct(
        private ?int $id,
        private int $moduleRecordId,
        private int $pipelineId,
        private ?int $fromStageId,
        private int $toStageId,
        private int $changedBy,
        private ?string $reason,
        private ?int $durationInStage,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Record a stage transition.
     */
    public static function recordTransition(
        int $moduleRecordId,
        int $pipelineId,
        ?int $fromStageId,
        int $toStageId,
        int $changedBy,
        ?string $reason = null,
        ?int $durationInStage = null,
    ): self {
        if ($fromStageId === $toStageId && $fromStageId !== null) {
            throw new InvalidArgumentException('Cannot transition to the same stage');
        }

        return new self(
            id: null,
            moduleRecordId: $moduleRecordId,
            pipelineId: $pipelineId,
            fromStageId: $fromStageId,
            toStageId: $toStageId,
            changedBy: $changedBy,
            reason: $reason !== null ? trim($reason) : null,
            durationInStage: $durationInStage,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Record initial stage assignment (when record is created).
     */
    public static function recordInitialAssignment(
        int $moduleRecordId,
        int $pipelineId,
        int $toStageId,
        int $changedBy,
    ): self {
        return new self(
            id: null,
            moduleRecordId: $moduleRecordId,
            pipelineId: $pipelineId,
            fromStageId: null,
            toStageId: $toStageId,
            changedBy: $changedBy,
            reason: null,
            durationInStage: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute a StageHistory entity from persistence.
     */
    public static function reconstitute(
        int $id,
        int $moduleRecordId,
        int $pipelineId,
        ?int $fromStageId,
        int $toStageId,
        int $changedBy,
        ?string $reason,
        ?int $durationInStage,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            moduleRecordId: $moduleRecordId,
            pipelineId: $pipelineId,
            fromStageId: $fromStageId,
            toStageId: $toStageId,
            changedBy: $changedBy,
            reason: $reason,
            durationInStage: $durationInStage,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModuleRecordId(): int
    {
        return $this->moduleRecordId;
    }

    public function getPipelineId(): int
    {
        return $this->pipelineId;
    }

    public function getFromStageId(): ?int
    {
        return $this->fromStageId;
    }

    public function getToStageId(): int
    {
        return $this->toStageId;
    }

    public function getChangedBy(): int
    {
        return $this->changedBy;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getDurationInStage(): ?int
    {
        return $this->durationInStage;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
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
     * Check if this is an initial stage assignment.
     */
    public function isInitialAssignment(): bool
    {
        return $this->fromStageId === null;
    }

    /**
     * Check if this transition has a recorded reason.
     */
    public function hasReason(): bool
    {
        return $this->reason !== null && !empty($this->reason);
    }

    /**
     * Check if duration in previous stage was tracked.
     */
    public function hasDuration(): bool
    {
        return $this->durationInStage !== null;
    }

    /**
     * Get duration formatted as human-readable string.
     */
    public function getFormattedDuration(): ?string
    {
        if ($this->durationInStage === null) {
            return null;
        }

        $seconds = $this->durationInStage;

        if ($seconds < 60) {
            return "{$seconds} seconds";
        }

        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return "{$minutes} minute" . ($minutes !== 1 ? 's' : '');
        }

        $hours = floor($minutes / 60);
        if ($hours < 24) {
            return "{$hours} hour" . ($hours !== 1 ? 's' : '');
        }

        $days = floor($hours / 24);
        return "{$days} day" . ($days !== 1 ? 's' : '');
    }

    /**
     * Get duration in days.
     */
    public function getDurationInDays(): ?float
    {
        if ($this->durationInStage === null) {
            return null;
        }

        return $this->durationInStage / 86400;
    }

    /**
     * Get duration in hours.
     */
    public function getDurationInHours(): ?float
    {
        if ($this->durationInStage === null) {
            return null;
        }

        return $this->durationInStage / 3600;
    }
}
