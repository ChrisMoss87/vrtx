<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Reporting;

use App\Domain\Reporting\Repositories\DashboardWidgetAlertHistoryRepositoryInterface;
use Illuminate\Support\Facades\DB;

class DbDashboardWidgetAlertHistoryRepository implements DashboardWidgetAlertHistoryRepositoryInterface
{
    private const TABLE = 'dashboard_widget_alert_histories';
    private const ALERTS_TABLE = 'dashboard_widget_alerts';
    private const WIDGETS_TABLE = 'dashboard_widgets';

    public function findById(int $id): ?array
    {
        $history = DB::table(self::TABLE)->where('id', $id)->first();

        return $history ? (array) $history : null;
    }

    public function findByAlertId(int $alertId, int $limit = 50): array
    {
        return DB::table(self::TABLE)
            ->where('alert_id', $alertId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();
    }

    public function findByUserId(int $userId, ?int $dashboardId = null, int $limit = 50): array
    {
        $query = DB::table(self::TABLE)
            ->join(self::ALERTS_TABLE, self::TABLE . '.alert_id', '=', self::ALERTS_TABLE . '.id')
            ->where(self::ALERTS_TABLE . '.user_id', $userId);

        if ($dashboardId !== null) {
            $query->join(self::WIDGETS_TABLE, self::ALERTS_TABLE . '.widget_id', '=', self::WIDGETS_TABLE . '.id')
                ->where(self::WIDGETS_TABLE . '.dashboard_id', $dashboardId);
        }

        return $query
            ->select(self::TABLE . '.*')
            ->orderByDesc(self::TABLE . '.created_at')
            ->limit($limit)
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();
    }

    public function findUnacknowledgedByUserId(int $userId): array
    {
        return DB::table(self::TABLE)
            ->join(self::ALERTS_TABLE, self::TABLE . '.alert_id', '=', self::ALERTS_TABLE . '.id')
            ->where(self::ALERTS_TABLE . '.user_id', $userId)
            ->whereNull(self::TABLE . '.acknowledged_at')
            ->whereNull(self::TABLE . '.dismissed_at')
            ->select(self::TABLE . '.*')
            ->orderByDesc(self::TABLE . '.created_at')
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();
    }

    public function create(array $data): array
    {
        $now = now();
        $insertData = array_merge($data, [
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (isset($insertData['context']) && is_array($insertData['context'])) {
            $insertData['context'] = json_encode($insertData['context']);
        }

        $id = DB::table(self::TABLE)->insertGetId($insertData);

        return $this->findById($id);
    }

    public function acknowledge(int $id, int $userId): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'acknowledged_at' => now(),
                'acknowledged_by' => $userId,
                'updated_at' => now(),
            ]) > 0;
    }

    public function dismiss(int $id, int $userId): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'dismissed_at' => now(),
                'dismissed_by' => $userId,
                'updated_at' => now(),
            ]) > 0;
    }

    public function getUnacknowledgedCount(int $userId): int
    {
        return DB::table(self::TABLE)
            ->join(self::ALERTS_TABLE, self::TABLE . '.alert_id', '=', self::ALERTS_TABLE . '.id')
            ->where(self::ALERTS_TABLE . '.user_id', $userId)
            ->whereNull(self::TABLE . '.acknowledged_at')
            ->whereNull(self::TABLE . '.dismissed_at')
            ->count();
    }

    public function getLastTriggeredValue(int $alertId, int $cooldownMinutes): ?float
    {
        $history = DB::table(self::TABLE)
            ->where('alert_id', $alertId)
            ->where('created_at', '<', now()->subMinutes($cooldownMinutes))
            ->orderByDesc('created_at')
            ->first();

        return $history ? (float) $history->triggered_value : null;
    }

    public function exists(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->exists();
    }
}
