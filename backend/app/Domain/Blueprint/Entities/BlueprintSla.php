<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Entities;

use App\Domain\Blueprint\ValueObjects\SlaStatus;

/**
 * Represents an SLA configuration for a blueprint state.
 */
class BlueprintSla
{
    private ?int $id = null;
    private int $blueprintId;
    private int $stateId;
    private string $name;
    private int $durationHours;
    private int $warningHours;
    private bool $businessHoursOnly;
    private array $escalationConfig;
    private bool $isActive;
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    private function __construct(
        int $blueprintId,
        int $stateId,
        string $name,
        int $durationHours,
        int $warningHours = 0,
        bool $businessHoursOnly = false,
        array $escalationConfig = [],
        bool $isActive = true,
    ) {
        $this->blueprintId = $blueprintId;
        $this->stateId = $stateId;
        $this->name = $name;
        $this->durationHours = $durationHours;
        $this->warningHours = $warningHours ?: (int) ($durationHours * 0.75);
        $this->businessHoursOnly = $businessHoursOnly;
        $this->escalationConfig = $escalationConfig;
        $this->isActive = $isActive;
    }

    public static function create(
        int $blueprintId,
        int $stateId,
        string $name,
        int $durationHours,
        int $warningHours = 0,
        bool $businessHoursOnly = false,
        array $escalationConfig = [],
    ): self {
        return new self(
            blueprintId: $blueprintId,
            stateId: $stateId,
            name: $name,
            durationHours: $durationHours,
            warningHours: $warningHours,
            businessHoursOnly: $businessHoursOnly,
            escalationConfig: $escalationConfig,
        );
    }

    public static function reconstitute(
        int $id,
        int $blueprintId,
        int $stateId,
        string $name,
        int $durationHours,
        int $warningHours,
        bool $businessHoursOnly,
        array $escalationConfig,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $sla = new self(
            blueprintId: $blueprintId,
            stateId: $stateId,
            name: $name,
            durationHours: $durationHours,
            warningHours: $warningHours,
            businessHoursOnly: $businessHoursOnly,
            escalationConfig: $escalationConfig,
            isActive: $isActive,
        );
        $sla->id = $id;
        $sla->createdAt = $createdAt;
        $sla->updatedAt = $updatedAt;

        return $sla;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlueprintId(): int
    {
        return $this->blueprintId;
    }

    public function getStateId(): int
    {
        return $this->stateId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDurationHours(): int
    {
        return $this->durationHours;
    }

    public function getWarningHours(): int
    {
        return $this->warningHours;
    }

    public function isBusinessHoursOnly(): bool
    {
        return $this->businessHoursOnly;
    }

    public function getEscalationConfig(): array
    {
        return $this->escalationConfig;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Domain methods
    public function update(
        string $name,
        int $durationHours,
        int $warningHours,
        bool $businessHoursOnly,
        array $escalationConfig,
    ): void {
        $this->name = $name;
        $this->durationHours = $durationHours;
        $this->warningHours = $warningHours;
        $this->businessHoursOnly = $businessHoursOnly;
        $this->escalationConfig = $escalationConfig;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Calculate the due date from a start time.
     */
    public function calculateDueDate(\DateTimeImmutable $startTime): \DateTimeImmutable
    {
        if (!$this->businessHoursOnly) {
            return $startTime->modify("+{$this->durationHours} hours");
        }

        // Business hours calculation would go here
        // For now, simple calculation
        return $startTime->modify("+{$this->durationHours} hours");
    }

    /**
     * Calculate the warning date from a start time.
     */
    public function calculateWarningDate(\DateTimeImmutable $startTime): \DateTimeImmutable
    {
        if (!$this->businessHoursOnly) {
            return $startTime->modify("+{$this->warningHours} hours");
        }

        return $startTime->modify("+{$this->warningHours} hours");
    }

    /**
     * Get the status for a given elapsed time.
     */
    public function getStatusForElapsedHours(int $elapsedHours): SlaStatus
    {
        if ($elapsedHours >= $this->durationHours) {
            return SlaStatus::BREACHED;
        }
        if ($elapsedHours >= $this->warningHours) {
            return SlaStatus::WARNING;
        }
        return SlaStatus::ACTIVE;
    }
}
