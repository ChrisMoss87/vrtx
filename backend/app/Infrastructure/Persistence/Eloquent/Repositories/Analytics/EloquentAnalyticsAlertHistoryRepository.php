<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Analytics;

use App\Domain\Analytics\Entities\AnalyticsAlertHistory as AnalyticsAlertHistoryEntity;
use App\Domain\Analytics\Repositories\AnalyticsAlertHistoryRepositoryInterface;
use App\Domain\Analytics\ValueObjects\AlertHistoryStatus;
use App\Models\AnalyticsAlertHistory;
use DateTimeImmutable;

final class EloquentAnalyticsAlertHistoryRepository implements AnalyticsAlertHistoryRepositoryInterface
{
    public function findById(int $id): ?AnalyticsAlertHistoryEntity
    {
        $model = AnalyticsAlertHistory::with('alert')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function getForAlert(int $alertId, int $limit = 50): array
    {
        $models = AnalyticsAlertHistory::where('alert_id', $alertId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn ($model) => $this->toDomain($model))->all();
    }

    public function getHistoricalValues(int $alertId, int $periods): array
    {
        return AnalyticsAlertHistory::where('alert_id', $alertId)
            ->whereNotNull('metric_value')
            ->orderBy('created_at', 'desc')
            ->limit($periods)
            ->pluck('metric_value')
            ->reverse()
            ->values()
            ->toArray();
    }

    public function getUnacknowledgedForUser(int $userId): array
    {
        $models = AnalyticsAlertHistory::unacknowledged()
            ->whereHas('alert', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereRaw("notification_config->'recipients' @> ?", [json_encode([$userId])]);
            })
            ->with('alert')
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn ($model) => $this->toDomain($model))->all();
    }

    public function getTriggeredTodayCount(?int $userId = null): int
    {
        $query = AnalyticsAlertHistory::whereDate('created_at', today());

        if ($userId !== null) {
            $query->whereHas('alert', fn ($q) => $q->where('user_id', $userId));
        }

        return $query->count();
    }

    public function getUnacknowledgedCount(?int $userId = null): int
    {
        $query = AnalyticsAlertHistory::unacknowledged();

        if ($userId !== null) {
            $query->whereHas('alert', fn ($q) => $q->where('user_id', $userId));
        }

        return $query->count();
    }

    public function calculateBaseline(int $alertId, int $periods): float
    {
        $historicalValues = AnalyticsAlertHistory::where('alert_id', $alertId)
            ->where('status', AnalyticsAlertHistory::STATUS_RESOLVED)
            ->whereNotNull('metric_value')
            ->orderBy('created_at', 'desc')
            ->limit($periods)
            ->pluck('metric_value')
            ->toArray();

        if (count($historicalValues) >= $periods / 2) {
            return array_sum($historicalValues) / count($historicalValues);
        }

        return 0.0;
    }

    public function getComparisonValue(int $alertId, int $periodDays): ?float
    {
        $model = AnalyticsAlertHistory::where('alert_id', $alertId)
            ->whereNotNull('metric_value')
            ->whereBetween('created_at', [
                now()->subDays($periodDays + 1),
                now()->subDays($periodDays - 1),
            ])
            ->orderBy('created_at', 'desc')
            ->first();

        return $model?->metric_value;
    }

    public function save(AnalyticsAlertHistoryEntity $entity): AnalyticsAlertHistoryEntity
    {
        $data = [
            'alert_id' => $entity->getAlertId(),
            'status' => $entity->getStatus()->value,
            'metric_value' => $entity->getMetricValue(),
            'threshold_value' => $entity->getThresholdValue(),
            'baseline_value' => $entity->getBaselineValue(),
            'deviation_percent' => $entity->getDeviationPercent(),
            'context' => $entity->getContext(),
            'message' => $entity->getMessage(),
            'acknowledged_by' => $entity->getAcknowledgedBy(),
            'acknowledged_at' => $entity->getAcknowledgedAt()?->format('Y-m-d H:i:s'),
            'acknowledgment_note' => $entity->getAcknowledgmentNote(),
            'notifications_sent' => $entity->getNotificationsSent(),
        ];

        if ($entity->getId() !== null) {
            $model = AnalyticsAlertHistory::findOrFail($entity->getId());
            $model->update($data);
        } else {
            $model = AnalyticsAlertHistory::create($data);
        }

        return $this->toDomain($model->fresh());
    }

    public function acknowledge(int $id, int $userId, ?string $note = null): void
    {
        $model = AnalyticsAlertHistory::findOrFail($id);
        $model->acknowledge($userId, $note);
    }

    private function toDomain(AnalyticsAlertHistory $model): AnalyticsAlertHistoryEntity
    {
        return AnalyticsAlertHistoryEntity::reconstitute(
            id: $model->id,
            alertId: $model->alert_id,
            status: AlertHistoryStatus::from($model->status),
            metricValue: $model->metric_value !== null ? (float) $model->metric_value : null,
            thresholdValue: $model->threshold_value !== null ? (float) $model->threshold_value : null,
            baselineValue: $model->baseline_value !== null ? (float) $model->baseline_value : null,
            deviationPercent: $model->deviation_percent !== null ? (float) $model->deviation_percent : null,
            context: $model->context ?? [],
            message: $model->message,
            acknowledgedBy: $model->acknowledged_by,
            acknowledgedAt: $model->acknowledged_at
                ? new DateTimeImmutable($model->acknowledged_at->toDateTimeString())
                : null,
            acknowledgmentNote: $model->acknowledgment_note,
            notificationsSent: $model->notifications_sent ?? [],
            createdAt: $model->created_at
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
    }
}
