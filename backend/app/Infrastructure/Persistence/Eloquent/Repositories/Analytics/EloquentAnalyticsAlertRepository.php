<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Analytics;

use App\Domain\Analytics\Entities\AnalyticsAlert as AnalyticsAlertEntity;
use App\Domain\Analytics\Repositories\AnalyticsAlertRepositoryInterface;
use App\Domain\Analytics\ValueObjects\AlertType;
use App\Domain\Analytics\ValueObjects\CheckFrequency;
use App\Models\AnalyticsAlert;
use DateTimeImmutable;

final class EloquentAnalyticsAlertRepository implements AnalyticsAlertRepositoryInterface
{
    public function findById(int $id): ?AnalyticsAlertEntity
    {
        $model = AnalyticsAlert::with(['module', 'report'])->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function getDueForCheck(): array
    {
        $models = AnalyticsAlert::dueForCheck()->get();

        return $models->map(fn ($model) => $this->toDomain($model))->all();
    }

    public function getForUser(int $userId): array
    {
        $models = AnalyticsAlert::where('user_id', $userId)
            ->with(['module', 'report'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn ($model) => $this->toDomain($model))->all();
    }

    public function getActiveCount(?int $userId = null): int
    {
        $query = AnalyticsAlert::active();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->count();
    }

    public function getTotalCount(?int $userId = null): int
    {
        $query = AnalyticsAlert::query();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->count();
    }

    public function save(AnalyticsAlertEntity $entity): AnalyticsAlertEntity
    {
        $data = [
            'name' => $entity->getName(),
            'description' => $entity->getDescription(),
            'user_id' => $entity->getUserId(),
            'alert_type' => $entity->getAlertType()->value,
            'module_id' => $entity->getModuleId(),
            'report_id' => $entity->getReportId(),
            'metric_field' => $entity->getMetricField(),
            'aggregation' => $entity->getAggregation(),
            'filters' => $entity->getFilters(),
            'condition_config' => $entity->getConditionConfig(),
            'notification_config' => $entity->getNotificationConfig(),
            'check_frequency' => $entity->getCheckFrequency()->value,
            'check_time' => $entity->getCheckTime(),
            'is_active' => $entity->isActive(),
            'last_checked_at' => $entity->getLastCheckedAt()?->format('Y-m-d H:i:s'),
            'last_triggered_at' => $entity->getLastTriggeredAt()?->format('Y-m-d H:i:s'),
            'trigger_count' => $entity->getTriggerCount(),
            'consecutive_triggers' => $entity->getConsecutiveTriggers(),
            'cooldown_minutes' => $entity->getCooldownMinutes(),
            'cooldown_until' => $entity->getCooldownUntil()?->format('Y-m-d H:i:s'),
        ];

        if ($entity->getId() !== null) {
            $model = AnalyticsAlert::findOrFail($entity->getId());
            $model->update($data);
        } else {
            $model = AnalyticsAlert::create($data);
        }

        return $this->toDomain($model->fresh(['module', 'report']));
    }

    public function delete(int $id): bool
    {
        $model = AnalyticsAlert::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    public function recordCheck(int $id): void
    {
        $model = AnalyticsAlert::find($id);
        $model?->recordCheck();
    }

    public function recordTrigger(int $id): void
    {
        $model = AnalyticsAlert::find($id);
        $model?->recordTrigger();
    }

    private function toDomain(AnalyticsAlert $model): AnalyticsAlertEntity
    {
        return AnalyticsAlertEntity::reconstitute(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            userId: $model->user_id,
            alertType: AlertType::from($model->alert_type),
            moduleId: $model->module_id,
            reportId: $model->report_id,
            metricField: $model->metric_field,
            aggregation: $model->aggregation,
            filters: $model->filters ?? [],
            conditionConfig: $model->condition_config ?? [],
            notificationConfig: $model->notification_config ?? [],
            checkFrequency: CheckFrequency::from($model->check_frequency),
            checkTime: $model->check_time?->format('H:i'),
            isActive: $model->is_active,
            lastCheckedAt: $model->last_checked_at
                ? new DateTimeImmutable($model->last_checked_at->toDateTimeString())
                : null,
            lastTriggeredAt: $model->last_triggered_at
                ? new DateTimeImmutable($model->last_triggered_at->toDateTimeString())
                : null,
            triggerCount: $model->trigger_count,
            consecutiveTriggers: $model->consecutive_triggers,
            cooldownMinutes: $model->cooldown_minutes,
            cooldownUntil: $model->cooldown_until
                ? new DateTimeImmutable($model->cooldown_until->toDateTimeString())
                : null,
            createdAt: $model->created_at
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
    }
}
