<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\Entities;

use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Pipeline Entity - Aggregate Root for the Pipeline domain.
 *
 * Represents a sales/workflow pipeline that contains stages for tracking
 * record progression through a defined process.
 */
final class Pipeline implements AggregateRoot
{
    use HasDomainEvents;

    /**
     * @param array<string, mixed> $settings
     */
    private function __construct(
        private ?int $id,
        private string $name,
        private int $moduleId,
        private string $stageFieldApiName,
        private bool $isActive,
        private array $settings,
        private ?int $createdBy,
        private ?int $updatedBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    /**
     * Create a new Pipeline entity.
     *
     * @param array<string, mixed> $settings
     */
    public static function create(
        string $name,
        int $moduleId,
        string $stageFieldApiName,
        ?int $createdBy = null,
        array $settings = [],
    ): self {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Pipeline name cannot be empty');
        }

        if (empty(trim($stageFieldApiName))) {
            throw new InvalidArgumentException('Stage field API name cannot be empty');
        }

        return new self(
            id: null,
            name: trim($name),
            moduleId: $moduleId,
            stageFieldApiName: $stageFieldApiName,
            isActive: true,
            settings: $settings,
            createdBy: $createdBy,
            updatedBy: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Reconstitute a Pipeline entity from persistence.
     *
     * @param array<string, mixed> $settings
     */
    public static function reconstitute(
        int $id,
        string $name,
        int $moduleId,
        string $stageFieldApiName,
        bool $isActive,
        array $settings,
        ?int $createdBy,
        ?int $updatedBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt = null,
    ): self {
        return new self(
            id: $id,
            name: $name,
            moduleId: $moduleId,
            stageFieldApiName: $stageFieldApiName,
            isActive: $isActive,
            settings: $settings,
            createdBy: $createdBy,
            updatedBy: $updatedBy,
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getModuleId(): int
    {
        return $this->moduleId;
    }

    public function getStageFieldApiName(): string
    {
        return $this->stageFieldApiName;
    }

    public function isActive(): bool
    {
        return $this->isActive;
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

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
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

    // =========================================================================
    // State Queries
    // =========================================================================

    /**
     * Check if pipeline can accept new records.
     */
    public function canAcceptRecords(): bool
    {
        return $this->isActive && !$this->isDeleted();
    }

    /**
     * Check if probability tracking is enabled.
     */
    public function hasProbabilityTracking(): bool
    {
        return (bool) ($this->settings['track_probability'] ?? true);
    }

    /**
     * Check if rotting (stale deal) tracking is enabled.
     */
    public function hasRottingTracking(): bool
    {
        return (bool) ($this->settings['track_rotting'] ?? false);
    }

    /**
     * Get default rotting days for stages.
     */
    public function getDefaultRottingDays(): ?int
    {
        $days = $this->settings['default_rotting_days'] ?? null;
        return $days !== null ? (int) $days : null;
    }

    // =========================================================================
    // State Mutations (Immutable - return new instance)
    // =========================================================================

    /**
     * Update pipeline details.
     */
    public function updateDetails(
        string $name,
        ?int $updatedBy = null,
    ): self {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Pipeline name cannot be empty');
        }

        return new self(
            id: $this->id,
            name: trim($name),
            moduleId: $this->moduleId,
            stageFieldApiName: $this->stageFieldApiName,
            isActive: $this->isActive,
            settings: $this->settings,
            createdBy: $this->createdBy,
            updatedBy: $updatedBy ?? $this->updatedBy,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Activate the pipeline.
     */
    public function activate(?int $updatedBy = null): self
    {
        if ($this->isActive) {
            return $this;
        }

        return new self(
            id: $this->id,
            name: $this->name,
            moduleId: $this->moduleId,
            stageFieldApiName: $this->stageFieldApiName,
            isActive: true,
            settings: $this->settings,
            createdBy: $this->createdBy,
            updatedBy: $updatedBy ?? $this->updatedBy,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Deactivate the pipeline.
     */
    public function deactivate(?int $updatedBy = null): self
    {
        if (!$this->isActive) {
            return $this;
        }

        return new self(
            id: $this->id,
            name: $this->name,
            moduleId: $this->moduleId,
            stageFieldApiName: $this->stageFieldApiName,
            isActive: false,
            settings: $this->settings,
            createdBy: $this->createdBy,
            updatedBy: $updatedBy ?? $this->updatedBy,
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
    public function withSettings(array $settings, ?int $updatedBy = null): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            moduleId: $this->moduleId,
            stageFieldApiName: $this->stageFieldApiName,
            isActive: $this->isActive,
            settings: array_merge($this->settings, $settings),
            createdBy: $this->createdBy,
            updatedBy: $updatedBy ?? $this->updatedBy,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Configure probability tracking.
     */
    public function configureProbabilityTracking(bool $enabled, ?int $updatedBy = null): self
    {
        return $this->withSettings(['track_probability' => $enabled], $updatedBy);
    }

    /**
     * Configure rotting tracking.
     */
    public function configureRottingTracking(
        bool $enabled,
        ?int $defaultDays = null,
        ?int $updatedBy = null,
    ): self {
        $settings = ['track_rotting' => $enabled];
        if ($defaultDays !== null) {
            $settings['default_rotting_days'] = $defaultDays;
        }
        return $this->withSettings($settings, $updatedBy);
    }

    /**
     * Soft delete the pipeline.
     */
    public function delete(?int $updatedBy = null): self
    {
        if ($this->isDeleted()) {
            return $this;
        }

        return new self(
            id: $this->id,
            name: $this->name,
            moduleId: $this->moduleId,
            stageFieldApiName: $this->stageFieldApiName,
            isActive: false,
            settings: $this->settings,
            createdBy: $this->createdBy,
            updatedBy: $updatedBy ?? $this->updatedBy,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Restore a soft-deleted pipeline.
     */
    public function restore(?int $updatedBy = null): self
    {
        if (!$this->isDeleted()) {
            return $this;
        }

        return new self(
            id: $this->id,
            name: $this->name,
            moduleId: $this->moduleId,
            stageFieldApiName: $this->stageFieldApiName,
            isActive: $this->isActive,
            settings: $this->settings,
            createdBy: $this->createdBy,
            updatedBy: $updatedBy ?? $this->updatedBy,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: null,
        );
    }
}
