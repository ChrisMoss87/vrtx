<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Reporting;

use App\Domain\Reporting\Repositories\DashboardWidgetAlertRepositoryInterface;
use Illuminate\Support\Facades\DB;

class DbDashboardWidgetAlertRepository implements DashboardWidgetAlertRepositoryInterface
{
    private const TABLE = 'dashboard_widget_alerts';
    private const WIDGETS_TABLE = 'dashboard_widgets';
    private const USERS_TABLE = 'users';
    private const HISTORY_TABLE = 'dashboard_widget_alert_histories';

    public function findById(int $id): ?array
    {
        $alert = DB::table(self::TABLE)->where('id', $id)->first();

        return $alert ? $this->formatAlert($alert) : null;
    }

    public function findByIdWithWidget(int $id): ?array
    {
        $alert = $this->findById($id);

        if (!$alert) {
            return null;
        }

        $alert['widget'] = $this->getWidget($id);

        return $alert;
    }

    public function findByWidgetId(int $widgetId): array
    {
        return DB::table(self::TABLE)
            ->where('widget_id', $widgetId)
            ->get()
            ->map(fn($row) => $this->formatAlert($row))
            ->toArray();
    }

    public function findActiveByDashboardId(int $dashboardId): array
    {
        return DB::table(self::TABLE)
            ->join(self::WIDGETS_TABLE, self::TABLE . '.widget_id', '=', self::WIDGETS_TABLE . '.id')
            ->where(self::WIDGETS_TABLE . '.dashboard_id', $dashboardId)
            ->where(self::TABLE . '.is_active', true)
            ->select(self::TABLE . '.*')
            ->get()
            ->map(fn($row) => $this->formatAlert($row))
            ->toArray();
    }

    public function create(array $data): array
    {
        $now = now();
        $insertData = array_merge($data, [
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (isset($insertData['notification_channels']) && is_array($insertData['notification_channels'])) {
            $insertData['notification_channels'] = json_encode($insertData['notification_channels']);
        }

        $id = DB::table(self::TABLE)->insertGetId($insertData);

        return $this->findById($id);
    }

    public function update(int $id, array $data): ?array
    {
        $updateData = array_merge($data, ['updated_at' => now()]);

        if (isset($updateData['notification_channels']) && is_array($updateData['notification_channels'])) {
            $updateData['notification_channels'] = json_encode($updateData['notification_channels']);
        }

        DB::table(self::TABLE)->where('id', $id)->update($updateData);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        // Delete history first
        DB::table(self::HISTORY_TABLE)->where('alert_id', $id)->delete();

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function toggleActive(int $id): ?array
    {
        $alert = $this->findById($id);
        if (!$alert) {
            return null;
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'is_active' => !$alert['is_active'],
            'updated_at' => now(),
        ]);

        return $this->findById($id);
    }

    public function isInCooldown(int $id): bool
    {
        $alert = $this->findById($id);
        if (!$alert) {
            return false;
        }

        $cooldownMinutes = $alert['cooldown_minutes'] ?? 60;

        $lastTrigger = DB::table(self::HISTORY_TABLE)
            ->where('alert_id', $id)
            ->where('created_at', '>', now()->subMinutes($cooldownMinutes))
            ->exists();

        return $lastTrigger;
    }

    public function getWidget(int $alertId): ?array
    {
        $alert = DB::table(self::TABLE)->where('id', $alertId)->first();
        if (!$alert) {
            return null;
        }

        $widget = DB::table(self::WIDGETS_TABLE)->where('id', $alert->widget_id)->first();

        return $widget ? (array) $widget : null;
    }

    public function getUser(int $alertId): ?array
    {
        $alert = DB::table(self::TABLE)->where('id', $alertId)->first();
        if (!$alert) {
            return null;
        }

        $user = DB::table(self::USERS_TABLE)
            ->where('id', $alert->user_id)
            ->select('id', 'name', 'email')
            ->first();

        return $user ? (array) $user : null;
    }

    public function exists(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->exists();
    }

    private function formatAlert(object $row): array
    {
        $data = (array) $row;

        if (isset($data['notification_channels']) && is_string($data['notification_channels'])) {
            $data['notification_channels'] = json_decode($data['notification_channels'], true);
        }

        if (isset($data['is_active'])) {
            $data['is_active'] = (bool) $data['is_active'];
        }

        return $data;
    }
}
