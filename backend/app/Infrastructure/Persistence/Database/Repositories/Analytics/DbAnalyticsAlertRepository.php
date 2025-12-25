<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Analytics;

use App\Domain\Analytics\Entities\AnalyticsAlert as AnalyticsAlertEntity;
use App\Domain\Analytics\Repositories\AnalyticsAlertRepositoryInterface;
use App\Domain\Analytics\ValueObjects\AlertType;
use App\Domain\Analytics\ValueObjects\CheckFrequency;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DbAnalyticsAlertRepository implements AnalyticsAlertRepositoryInterface
{
    private const TABLE = 'analytics_alerts';
    private const TABLE_MODULES = 'modules';
    private const TABLE_REPORTS = 'reports';

    // =========================================================================
    // BASIC CRUD
    // =========================================================================

    public function findById(int $id): ?AnalyticsAlertEntity
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->rowToArray($row) : null;
    }

    public function findByIdWithRelations(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->rowToArrayWithRelations($row) : null;
    }

    public function create(array $data): array
    {
        $id = DB::table(self::TABLE)->insertGetId(
            array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArrayWithRelations($row);
    }

    public function update(int $id, array $data): array
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArrayWithRelations($row);
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    public function findWithFilters(array $filters, int $perPage = 25): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['type'])) {
            $query->where('alert_type', $filters['type']);
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->where('is_active', true);
        }

        if (isset($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        if (isset($filters['report_id'])) {
            $query->where('report_id', $filters['report_id']);
        }

        $query->orderByDesc('created_at');

        $currentPage = $filters['page'] ?? 1;
        $total = $query->count();

        $rows = $query
            ->offset(($currentPage - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->toArray();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage
        );
    }

    public function getDueForCheck(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<=', now()->subMinutes(5));
            })
            ->where(function ($q) {
                $q->whereNull('cooldown_until')
                    ->orWhere('cooldown_until', '<=', now());
            })
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function getForUser(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntity($row))->all();
    }

    public function getForUserAsArray(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();
    }

    public function getActiveCount(?int $userId = null): int
    {
        $query = DB::table(self::TABLE)->where('is_active', true);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->count();
    }

    public function getTotalCount(?int $userId = null): int
    {
        $query = DB::table(self::TABLE);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->count();
    }

    // =========================================================================
    // COMMAND METHODS
    // =========================================================================

    public function save(AnalyticsAlertEntity $entity): AnalyticsAlertEntity
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

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function recordCheck(int $id): void
    {
        DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'last_checked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function recordTrigger(int $id): void
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if ($row) {
            $cooldownMinutes = $row->cooldown_minutes ?? 60;
            DB::table(self::TABLE)
                ->where('id', $id)
                ->update([
                    'last_triggered_at' => now(),
                    'trigger_count' => ($row->trigger_count ?? 0) + 1,
                    'consecutive_triggers' => ($row->consecutive_triggers ?? 0) + 1,
                    'cooldown_until' => now()->addMinutes($cooldownMinutes),
                    'updated_at' => now(),
                ]);
        }
    }

    public function toggleActive(int $id): array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'is_active' => !$row->is_active,
                'updated_at' => now(),
            ]);

        $updated = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->rowToArray($updated);
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): AnalyticsAlertEntity
    {
        return AnalyticsAlertEntity::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            description: $row->description,
            userId: (int) $row->user_id,
            alertType: AlertType::from($row->alert_type),
            moduleId: $row->module_id ? (int) $row->module_id : null,
            reportId: $row->report_id ? (int) $row->report_id : null,
            metricField: $row->metric_field,
            aggregation: $row->aggregation,
            filters: $row->filters ? (is_string($row->filters) ? json_decode($row->filters, true) : $row->filters) : [],
            conditionConfig: $row->condition_config ? (is_string($row->condition_config) ? json_decode($row->condition_config, true) : $row->condition_config) : [],
            notificationConfig: $row->notification_config ? (is_string($row->notification_config) ? json_decode($row->notification_config, true) : $row->notification_config) : [],
            checkFrequency: CheckFrequency::from($row->check_frequency),
            checkTime: $row->check_time,
            isActive: (bool) $row->is_active,
            lastCheckedAt: $row->last_checked_at ? new DateTimeImmutable($row->last_checked_at) : null,
            lastTriggeredAt: $row->last_triggered_at ? new DateTimeImmutable($row->last_triggered_at) : null,
            triggerCount: (int) ($row->trigger_count ?? 0),
            consecutiveTriggers: (int) ($row->consecutive_triggers ?? 0),
            cooldownMinutes: (int) ($row->cooldown_minutes ?? 60),
            cooldownUntil: $row->cooldown_until ? new DateTimeImmutable($row->cooldown_until) : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(AnalyticsAlertEntity $entity): array
    {
        return [
            'name' => $entity->getName(),
            'description' => $entity->getDescription(),
            'user_id' => $entity->getUserId(),
            'alert_type' => $entity->getAlertType()->value,
            'module_id' => $entity->getModuleId(),
            'report_id' => $entity->getReportId(),
            'metric_field' => $entity->getMetricField(),
            'aggregation' => $entity->getAggregation(),
            'filters' => json_encode($entity->getFilters()),
            'condition_config' => json_encode($entity->getConditionConfig()),
            'notification_config' => json_encode($entity->getNotificationConfig()),
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
    }

    private function rowToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'user_id' => $row->user_id,
            'alert_type' => $row->alert_type,
            'module_id' => $row->module_id,
            'report_id' => $row->report_id,
            'metric_field' => $row->metric_field,
            'aggregation' => $row->aggregation,
            'filters' => $row->filters ? (is_string($row->filters) ? json_decode($row->filters, true) : $row->filters) : [],
            'condition_config' => $row->condition_config ? (is_string($row->condition_config) ? json_decode($row->condition_config, true) : $row->condition_config) : [],
            'notification_config' => $row->notification_config ? (is_string($row->notification_config) ? json_decode($row->notification_config, true) : $row->notification_config) : [],
            'check_frequency' => $row->check_frequency,
            'check_time' => $row->check_time,
            'is_active' => (bool) $row->is_active,
            'last_checked_at' => $row->last_checked_at,
            'last_triggered_at' => $row->last_triggered_at,
            'trigger_count' => $row->trigger_count,
            'consecutive_triggers' => $row->consecutive_triggers,
            'cooldown_minutes' => $row->cooldown_minutes,
            'cooldown_until' => $row->cooldown_until,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    private function rowToArrayWithRelations(stdClass $row): array
    {
        $data = $this->rowToArray($row);

        // Load module relation
        if ($row->module_id) {
            $module = DB::table(self::TABLE_MODULES)
                ->select('id', 'api_name', 'label')
                ->where('id', $row->module_id)
                ->first();
            $data['module'] = $module ? (array) $module : null;
        }

        // Load report relation
        if ($row->report_id) {
            $report = DB::table(self::TABLE_REPORTS)
                ->select('id', 'name')
                ->where('id', $row->report_id)
                ->first();
            $data['report'] = $report ? (array) $report : null;
        }

        return $data;
    }
}
