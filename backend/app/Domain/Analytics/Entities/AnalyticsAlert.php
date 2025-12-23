<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Entities;

use App\Domain\Analytics\ValueObjects\AlertType;
use App\Domain\Analytics\ValueObjects\CheckFrequency;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class AnalyticsAlert implements Entity
{
    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $description,
        private int $userId,
        private AlertType $alertType,
        private ?int $moduleId,
        private ?int $reportId,
        private string $metricField,
        private string $aggregation,
        private array $filters,
        private array $conditionConfig,
        private array $notificationConfig,
        private CheckFrequency $checkFrequency,
        private ?string $checkTime,
        private bool $isActive,
        private ?DateTimeImmutable $lastCheckedAt,
        private ?DateTimeImmutable $lastTriggeredAt,
        private int $triggerCount,
        private int $consecutiveTriggers,
        private int $cooldownMinutes,
        private ?DateTimeImmutable $cooldownUntil,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $name,
        int $userId,
        AlertType $alertType,
        string $metricField,
        array $conditionConfig,
        ?string $description = null,
        ?int $moduleId = null,
        ?int $reportId = null,
        string $aggregation = 'count',
        array $filters = [],
        array $notificationConfig = [],
        CheckFrequency $checkFrequency = CheckFrequency::HOURLY,
        ?string $checkTime = null,
        int $cooldownMinutes = 60,
    ): self {
        return new self(
            id: null,
            name: $name,
            description: $description,
            userId: $userId,
            alertType: $alertType,
            moduleId: $moduleId,
            reportId: $reportId,
            metricField: $metricField,
            aggregation: $aggregation,
            filters: $filters,
            conditionConfig: $conditionConfig,
            notificationConfig: $notificationConfig,
            checkFrequency: $checkFrequency,
            checkTime: $checkTime,
            isActive: true,
            lastCheckedAt: null,
            lastTriggeredAt: null,
            triggerCount: 0,
            consecutiveTriggers: 0,
            cooldownMinutes: $cooldownMinutes,
            cooldownUntil: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?string $description,
        int $userId,
        AlertType $alertType,
        ?int $moduleId,
        ?int $reportId,
        string $metricField,
        string $aggregation,
        array $filters,
        array $conditionConfig,
        array $notificationConfig,
        CheckFrequency $checkFrequency,
        ?string $checkTime,
        bool $isActive,
        ?DateTimeImmutable $lastCheckedAt,
        ?DateTimeImmutable $lastTriggeredAt,
        int $triggerCount,
        int $consecutiveTriggers,
        int $cooldownMinutes,
        ?DateTimeImmutable $cooldownUntil,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            description: $description,
            userId: $userId,
            alertType: $alertType,
            moduleId: $moduleId,
            reportId: $reportId,
            metricField: $metricField,
            aggregation: $aggregation,
            filters: $filters,
            conditionConfig: $conditionConfig,
            notificationConfig: $notificationConfig,
            checkFrequency: $checkFrequency,
            checkTime: $checkTime,
            isActive: $isActive,
            lastCheckedAt: $lastCheckedAt,
            lastTriggeredAt: $lastTriggeredAt,
            triggerCount: $triggerCount,
            consecutiveTriggers: $consecutiveTriggers,
            cooldownMinutes: $cooldownMinutes,
            cooldownUntil: $cooldownUntil,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getUserId(): int { return $this->userId; }
    public function getAlertType(): AlertType { return $this->alertType; }
    public function getModuleId(): ?int { return $this->moduleId; }
    public function getReportId(): ?int { return $this->reportId; }
    public function getMetricField(): string { return $this->metricField; }
    public function getAggregation(): string { return $this->aggregation; }
    public function getFilters(): array { return $this->filters; }
    public function getConditionConfig(): array { return $this->conditionConfig; }
    public function getNotificationConfig(): array { return $this->notificationConfig; }
    public function getCheckFrequency(): CheckFrequency { return $this->checkFrequency; }
    public function getCheckTime(): ?string { return $this->checkTime; }
    public function isActive(): bool { return $this->isActive; }
    public function getLastCheckedAt(): ?DateTimeImmutable { return $this->lastCheckedAt; }
    public function getLastTriggeredAt(): ?DateTimeImmutable { return $this->lastTriggeredAt; }
    public function getTriggerCount(): int { return $this->triggerCount; }
    public function getConsecutiveTriggers(): int { return $this->consecutiveTriggers; }
    public function getCooldownMinutes(): int { return $this->cooldownMinutes; }
    public function getCooldownUntil(): ?DateTimeImmutable { return $this->cooldownUntil; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    // Domain actions
    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function recordCheck(): void
    {
        $this->lastCheckedAt = new DateTimeImmutable();
        $this->consecutiveTriggers = 0;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function recordTrigger(): void
    {
        $now = new DateTimeImmutable();
        $this->lastTriggeredAt = $now;
        $this->triggerCount++;
        $this->consecutiveTriggers++;
        $this->cooldownUntil = $now->modify("+{$this->cooldownMinutes} minutes");
        $this->updatedAt = $now;
    }

    public function isInCooldown(): bool
    {
        if ($this->cooldownUntil === null) {
            return false;
        }

        return $this->cooldownUntil > new DateTimeImmutable();
    }

    public function getRecipientIds(): array
    {
        $recipients = $this->notificationConfig['recipients'] ?? [];

        if (!in_array($this->userId, $recipients)) {
            $recipients[] = $this->userId;
        }

        return $recipients;
    }

    public function getChannels(): array
    {
        return $this->notificationConfig['channels'] ?? ['in_app'];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'user_id' => $this->userId,
            'alert_type' => $this->alertType->value,
            'module_id' => $this->moduleId,
            'report_id' => $this->reportId,
            'metric_field' => $this->metricField,
            'aggregation' => $this->aggregation,
            'filters' => $this->filters,
            'condition_config' => $this->conditionConfig,
            'notification_config' => $this->notificationConfig,
            'check_frequency' => $this->checkFrequency->value,
            'check_time' => $this->checkTime,
            'is_active' => $this->isActive,
            'last_checked_at' => $this->lastCheckedAt?->format('Y-m-d H:i:s'),
            'last_triggered_at' => $this->lastTriggeredAt?->format('Y-m-d H:i:s'),
            'trigger_count' => $this->triggerCount,
            'consecutive_triggers' => $this->consecutiveTriggers,
            'cooldown_minutes' => $this->cooldownMinutes,
            'cooldown_until' => $this->cooldownUntil?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->id !== null && $this->id === $other->id;
    }
}
