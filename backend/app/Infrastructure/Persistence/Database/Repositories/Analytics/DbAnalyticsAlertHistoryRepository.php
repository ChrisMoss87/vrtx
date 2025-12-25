<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Analytics;

use App\Domain\Analytics\Entities\AnalyticsAlertHistory as AnalyticsAlertHistoryEntity;
use App\Domain\Analytics\Repositories\AnalyticsAlertHistoryRepositoryInterface;
use App\Domain\Analytics\ValueObjects\AlertHistoryStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DbAnalyticsAlertHistoryRepository implements AnalyticsAlertHistoryRepositoryInterface
{
    private const TABLE = 'analytics_alert_history';
    private const TABLE_ALERTS = 'analytics_alerts';

    private const STATUS_TRIGGERED = 'triggered';
    private const STATUS_RESOLVED = 'resolved';

    public function findById(int $id): ?AnalyticsAlertHistoryEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function getForAlert(int $alertId, int $limit = 50): array
    {
        $rows = DB::table(self::TABLE)
            ->where('alert_id', $alertId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function getHistoricalValues(int $alertId, int $periods): array
    {
        return DB::table(self::TABLE)
            ->where('alert_id', $alertId)
            ->whereNotNull('metric_value')
            ->orderByDesc('created_at')
            ->limit($periods)
            ->pluck('metric_value')
            ->reverse()
            ->values()
            ->toArray();
    }

    public function getUnacknowledgedForUser(int $userId): array
    {
        // Get alert IDs for this user
        $alertIds = DB::table(self::TABLE_ALERTS)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereRaw("notification_config->'recipients' @> ?", [json_encode([$userId])]);
            })
            ->pluck('id');

        $rows = DB::table(self::TABLE)
            ->whereIn('alert_id', $alertIds)
            ->whereNull('acknowledged_at')
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function getTriggeredTodayCount(?int $userId = null): int
    {
        $query = DB::table(self::TABLE)
            ->whereDate('created_at', now()->toDateString());

        if ($userId !== null) {
            $alertIds = DB::table(self::TABLE_ALERTS)
                ->where('user_id', $userId)
                ->pluck('id');
            $query->whereIn('alert_id', $alertIds);
        }

        return $query->count();
    }

    public function getUnacknowledgedCount(?int $userId = null): int
    {
        $query = DB::table(self::TABLE)->whereNull('acknowledged_at');

        if ($userId !== null) {
            $alertIds = DB::table(self::TABLE_ALERTS)
                ->where('user_id', $userId)
                ->pluck('id');
            $query->whereIn('alert_id', $alertIds);
        }

        return $query->count();
    }

    public function calculateBaseline(int $alertId, int $periods): float
    {
        $historicalValues = DB::table(self::TABLE)
            ->where('alert_id', $alertId)
            ->where('status', self::STATUS_RESOLVED)
            ->whereNotNull('metric_value')
            ->orderByDesc('created_at')
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
        $row = DB::table(self::TABLE)
            ->where('alert_id', $alertId)
            ->whereNotNull('metric_value')
            ->whereBetween('created_at', [
                now()->subDays($periodDays + 1),
                now()->subDays($periodDays - 1),
            ])
            ->orderByDesc('created_at')
            ->first();

        return $row?->metric_value;
    }

    public function save(AnalyticsAlertHistoryEntity $entity): AnalyticsAlertHistoryEntity
    {
        $data = $this->toRowData($entity);

        if ($entity->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $entity->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $entity->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function acknowledge(int $id, int $userId, ?string $note = null): void
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'acknowledged_by' => $userId,
                'acknowledged_at' => now(),
                'acknowledgment_note' => $note,
                'updated_at' => now(),
            ]);
    }

    private function toDomainEntity(stdClass $row): AnalyticsAlertHistoryEntity
    {
        return AnalyticsAlertHistoryEntity::reconstitute(
            id: (int) $row->id,
            alertId: (int) $row->alert_id,
            status: AlertHistoryStatus::from($row->status),
            metricValue: $row->metric_value !== null ? (float) $row->metric_value : null,
            thresholdValue: $row->threshold_value !== null ? (float) $row->threshold_value : null,
            baselineValue: $row->baseline_value !== null ? (float) $row->baseline_value : null,
            deviationPercent: $row->deviation_percent !== null ? (float) $row->deviation_percent : null,
            context: $row->context ? (is_string($row->context) ? json_decode($row->context, true) : $row->context) : [],
            message: $row->message,
            acknowledgedBy: $row->acknowledged_by ? (int) $row->acknowledged_by : null,
            acknowledgedAt: $row->acknowledged_at ? new DateTimeImmutable($row->acknowledged_at) : null,
            acknowledgmentNote: $row->acknowledgment_note,
            notificationsSent: $row->notifications_sent ? (is_string($row->notifications_sent) ? json_decode($row->notifications_sent, true) : $row->notifications_sent) : [],
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(AnalyticsAlertHistoryEntity $entity): array
    {
        return [
            'alert_id' => $entity->getAlertId(),
            'status' => $entity->getStatus()->value,
            'metric_value' => $entity->getMetricValue(),
            'threshold_value' => $entity->getThresholdValue(),
            'baseline_value' => $entity->getBaselineValue(),
            'deviation_percent' => $entity->getDeviationPercent(),
            'context' => json_encode($entity->getContext()),
            'message' => $entity->getMessage(),
            'acknowledged_by' => $entity->getAcknowledgedBy(),
            'acknowledged_at' => $entity->getAcknowledgedAt()?->format('Y-m-d H:i:s'),
            'acknowledgment_note' => $entity->getAcknowledgmentNote(),
            'notifications_sent' => json_encode($entity->getNotificationsSent()),
        ];
    }
}
