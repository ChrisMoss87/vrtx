<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Entities;

use App\Domain\Analytics\ValueObjects\AlertHistoryStatus;
use DateTimeImmutable;

final class AnalyticsAlertHistory
{
    private function __construct(
        private ?int $id,
        private int $alertId,
        private AlertHistoryStatus $status,
        private ?float $metricValue,
        private ?float $thresholdValue,
        private ?float $baselineValue,
        private ?float $deviationPercent,
        private array $context,
        private ?string $message,
        private ?int $acknowledgedBy,
        private ?DateTimeImmutable $acknowledgedAt,
        private ?string $acknowledgmentNote,
        private array $notificationsSent,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        int $alertId,
        AlertHistoryStatus $status,
        ?float $metricValue = null,
        ?float $thresholdValue = null,
        ?float $baselineValue = null,
        ?float $deviationPercent = null,
        array $context = [],
        ?string $message = null,
    ): self {
        return new self(
            id: null,
            alertId: $alertId,
            status: $status,
            metricValue: $metricValue,
            thresholdValue: $thresholdValue,
            baselineValue: $baselineValue,
            deviationPercent: $deviationPercent,
            context: $context,
            message: $message,
            acknowledgedBy: null,
            acknowledgedAt: null,
            acknowledgmentNote: null,
            notificationsSent: [],
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        int $alertId,
        AlertHistoryStatus $status,
        ?float $metricValue,
        ?float $thresholdValue,
        ?float $baselineValue,
        ?float $deviationPercent,
        array $context,
        ?string $message,
        ?int $acknowledgedBy,
        ?DateTimeImmutable $acknowledgedAt,
        ?string $acknowledgmentNote,
        array $notificationsSent,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            alertId: $alertId,
            status: $status,
            metricValue: $metricValue,
            thresholdValue: $thresholdValue,
            baselineValue: $baselineValue,
            deviationPercent: $deviationPercent,
            context: $context,
            message: $message,
            acknowledgedBy: $acknowledgedBy,
            acknowledgedAt: $acknowledgedAt,
            acknowledgmentNote: $acknowledgmentNote,
            notificationsSent: $notificationsSent,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getAlertId(): int { return $this->alertId; }
    public function getStatus(): AlertHistoryStatus { return $this->status; }
    public function getMetricValue(): ?float { return $this->metricValue; }
    public function getThresholdValue(): ?float { return $this->thresholdValue; }
    public function getBaselineValue(): ?float { return $this->baselineValue; }
    public function getDeviationPercent(): ?float { return $this->deviationPercent; }
    public function getContext(): array { return $this->context; }
    public function getMessage(): ?string { return $this->message; }
    public function getAcknowledgedBy(): ?int { return $this->acknowledgedBy; }
    public function getAcknowledgedAt(): ?DateTimeImmutable { return $this->acknowledgedAt; }
    public function getAcknowledgmentNote(): ?string { return $this->acknowledgmentNote; }
    public function getNotificationsSent(): array { return $this->notificationsSent; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    // Domain actions
    public function acknowledge(int $userId, ?string $note = null): void
    {
        $this->status = AlertHistoryStatus::ACKNOWLEDGED;
        $this->acknowledgedBy = $userId;
        $this->acknowledgedAt = new DateTimeImmutable();
        $this->acknowledgmentNote = $note;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function resolve(): void
    {
        $this->status = AlertHistoryStatus::RESOLVED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function mute(): void
    {
        $this->status = AlertHistoryStatus::MUTED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function recordNotificationSent(string $channel, int $userId): void
    {
        $this->notificationsSent[] = [
            'channel' => $channel,
            'user_id' => $userId,
            'sent_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledgedAt !== null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'alert_id' => $this->alertId,
            'status' => $this->status->value,
            'metric_value' => $this->metricValue,
            'threshold_value' => $this->thresholdValue,
            'baseline_value' => $this->baselineValue,
            'deviation_percent' => $this->deviationPercent,
            'context' => $this->context,
            'message' => $this->message,
            'acknowledged_by' => $this->acknowledgedBy,
            'acknowledged_at' => $this->acknowledgedAt?->format('Y-m-d H:i:s'),
            'acknowledgment_note' => $this->acknowledgmentNote,
            'notifications_sent' => $this->notificationsSent,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
