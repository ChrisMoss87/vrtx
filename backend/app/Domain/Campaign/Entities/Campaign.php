<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Entities;

use App\Domain\Campaign\ValueObjects\CampaignStatus;
use App\Domain\Campaign\ValueObjects\CampaignType;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Traits\HasDomainEvents;
use DateTimeImmutable;

/**
 * Campaign domain entity representing a marketing campaign.
 *
 * Campaigns can be email blasts, drip sequences, event promotions,
 * product launches, newsletters, or re-engagement campaigns.
 */
final class Campaign implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $description,
        private CampaignType $type,
        private CampaignStatus $status,
        private ?int $moduleId,
        private ?DateTimeImmutable $startDate,
        private ?DateTimeImmutable $endDate,
        private ?string $budget,
        private string $spent,
        private array $settings,
        private array $goals,
        private ?int $createdBy,
        private ?int $ownerId,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    /**
     * Create a new campaign.
     */
    public static function create(
        string $name,
        CampaignType $type,
        int $createdBy,
        ?string $description = null,
        ?int $ownerId = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            description: $description,
            type: $type,
            status: CampaignStatus::Draft,
            moduleId: null,
            startDate: null,
            endDate: null,
            budget: null,
            spent: '0.00',
            settings: [],
            goals: [],
            createdBy: $createdBy,
            ownerId: $ownerId ?? $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * Reconstitute a campaign from persistence.
     */
    public static function reconstitute(
        int $id,
        string $name,
        ?string $description,
        CampaignType $type,
        CampaignStatus $status,
        ?int $moduleId,
        ?DateTimeImmutable $startDate,
        ?DateTimeImmutable $endDate,
        ?string $budget,
        string $spent,
        array $settings,
        array $goals,
        ?int $createdBy,
        ?int $ownerId,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            description: $description,
            type: $type,
            status: $status,
            moduleId: $moduleId,
            startDate: $startDate,
            endDate: $endDate,
            budget: $budget,
            spent: $spent,
            settings: $settings,
            goals: $goals,
            createdBy: $createdBy,
            ownerId: $ownerId,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
        );
    }

    // -------------------------------------------------------------------------
    // Business Logic Methods
    // -------------------------------------------------------------------------

    /**
     * Check if campaign is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === CampaignStatus::Draft;
    }

    /**
     * Check if campaign is active.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if campaign can be started.
     */
    public function canBeStarted(): bool
    {
        return $this->status->canBeStarted();
    }

    /**
     * Check if campaign can be paused.
     */
    public function canBePaused(): bool
    {
        return $this->status->canBePaused();
    }

    /**
     * Check if campaign can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    /**
     * Check if campaign is editable.
     */
    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Check if campaign has ended (terminal state).
     */
    public function hasEnded(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if campaign is within date range.
     */
    public function isWithinDateRange(): bool
    {
        $now = new DateTimeImmutable();

        if ($this->startDate !== null && $now < $this->startDate) {
            return false;
        }

        if ($this->endDate !== null && $now > $this->endDate) {
            return false;
        }

        return true;
    }

    /**
     * Calculate budget utilization percentage.
     */
    public function getBudgetUtilization(): float
    {
        if ($this->budget === null || (float) $this->budget === 0.0) {
            return 0.0;
        }

        return round(((float) $this->spent / (float) $this->budget) * 100, 2);
    }

    /**
     * Check if budget is exceeded.
     */
    public function isBudgetExceeded(): bool
    {
        if ($this->budget === null) {
            return false;
        }

        return (float) $this->spent > (float) $this->budget;
    }

    /**
     * Get remaining budget.
     */
    public function getRemainingBudget(): float
    {
        if ($this->budget === null) {
            return 0.0;
        }

        return max(0, (float) $this->budget - (float) $this->spent);
    }

    /**
     * Start the campaign.
     *
     * @return self Returns a new instance with active status
     * @throws \DomainException If campaign cannot be started
     */
    public function start(): self
    {
        if (!$this->canBeStarted()) {
            throw new \DomainException("Campaign cannot be started from status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: CampaignStatus::Active,
            moduleId: $this->moduleId,
            startDate: $this->startDate ?? new DateTimeImmutable(),
            endDate: $this->endDate,
            budget: $this->budget,
            spent: $this->spent,
            settings: $this->settings,
            goals: $this->goals,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Pause the campaign.
     *
     * @return self Returns a new instance with paused status
     * @throws \DomainException If campaign cannot be paused
     */
    public function pause(): self
    {
        if (!$this->canBePaused()) {
            throw new \DomainException("Campaign cannot be paused from status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: CampaignStatus::Paused,
            moduleId: $this->moduleId,
            startDate: $this->startDate,
            endDate: $this->endDate,
            budget: $this->budget,
            spent: $this->spent,
            settings: $this->settings,
            goals: $this->goals,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Cancel the campaign.
     *
     * @return self Returns a new instance with cancelled status
     * @throws \DomainException If campaign cannot be cancelled
     */
    public function cancel(): self
    {
        if (!$this->canBeCancelled()) {
            throw new \DomainException("Campaign cannot be cancelled from status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: CampaignStatus::Cancelled,
            moduleId: $this->moduleId,
            startDate: $this->startDate,
            endDate: new DateTimeImmutable(),
            budget: $this->budget,
            spent: $this->spent,
            settings: $this->settings,
            goals: $this->goals,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Complete the campaign.
     *
     * @return self Returns a new instance with completed status
     */
    public function complete(): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: CampaignStatus::Completed,
            moduleId: $this->moduleId,
            startDate: $this->startDate,
            endDate: new DateTimeImmutable(),
            budget: $this->budget,
            spent: $this->spent,
            settings: $this->settings,
            goals: $this->goals,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Schedule the campaign for a future date.
     *
     * @return self Returns a new instance with scheduled status
     */
    public function schedule(DateTimeImmutable $startDate, ?DateTimeImmutable $endDate = null): self
    {
        if (!$this->isDraft()) {
            throw new \DomainException("Only draft campaigns can be scheduled");
        }

        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: CampaignStatus::Scheduled,
            moduleId: $this->moduleId,
            startDate: $startDate,
            endDate: $endDate ?? $this->endDate,
            budget: $this->budget,
            spent: $this->spent,
            settings: $this->settings,
            goals: $this->goals,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Update campaign details.
     *
     * @return self Returns a new instance with updated details
     * @throws \DomainException If campaign is not editable
     */
    public function updateDetails(
        ?string $name = null,
        ?string $description = null,
        ?string $budget = null,
    ): self {
        if (!$this->isEditable()) {
            throw new \DomainException("Campaign cannot be edited in status: {$this->status->value}");
        }

        return new self(
            id: $this->id,
            name: $name ?? $this->name,
            description: $description ?? $this->description,
            type: $this->type,
            status: $this->status,
            moduleId: $this->moduleId,
            startDate: $this->startDate,
            endDate: $this->endDate,
            budget: $budget ?? $this->budget,
            spent: $this->spent,
            settings: $this->settings,
            goals: $this->goals,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Record spending.
     *
     * @return self Returns a new instance with updated spent amount
     */
    public function recordSpending(string $amount): self
    {
        $newSpent = bcadd($this->spent, $amount, 2);

        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: $this->status,
            moduleId: $this->moduleId,
            startDate: $this->startDate,
            endDate: $this->endDate,
            budget: $this->budget,
            spent: $newSpent,
            settings: $this->settings,
            goals: $this->goals,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Update settings.
     *
     * @return self Returns a new instance with updated settings
     */
    public function withSettings(array $settings): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: $this->status,
            moduleId: $this->moduleId,
            startDate: $this->startDate,
            endDate: $this->endDate,
            budget: $this->budget,
            spent: $this->spent,
            settings: array_merge($this->settings, $settings),
            goals: $this->goals,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Set campaign goals.
     *
     * @return self Returns a new instance with updated goals
     */
    public function withGoals(array $goals): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: $this->status,
            moduleId: $this->moduleId,
            startDate: $this->startDate,
            endDate: $this->endDate,
            budget: $this->budget,
            spent: $this->spent,
            settings: $this->settings,
            goals: $goals,
            createdBy: $this->createdBy,
            ownerId: $this->ownerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    /**
     * Transfer ownership.
     *
     * @return self Returns a new instance with new owner
     */
    public function transferOwnership(int $newOwnerId): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            type: $this->type,
            status: $this->status,
            moduleId: $this->moduleId,
            startDate: $this->startDate,
            endDate: $this->endDate,
            budget: $this->budget,
            spent: $this->spent,
            settings: $this->settings,
            goals: $this->goals,
            createdBy: $this->createdBy,
            ownerId: $newOwnerId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            deletedAt: $this->deletedAt,
        );
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): CampaignType
    {
        return $this->type;
    }

    public function getStatus(): CampaignStatus
    {
        return $this->status;
    }

    public function getModuleId(): ?int
    {
        return $this->moduleId;
    }

    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getBudget(): ?string
    {
        return $this->budget;
    }

    public function getSpent(): string
    {
        return $this->spent;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getGoals(): array
    {
        return $this->goals;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
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
}
