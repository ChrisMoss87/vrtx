<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Reporting;

use App\Domain\Reporting\Entities\Dashboard;
use App\Domain\Reporting\Entities\DashboardWidget;
use App\Domain\Reporting\Repositories\DashboardRepositoryInterface;
use App\Domain\Reporting\ValueObjects\WidgetType;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the DashboardRepository.
 */
class DbDashboardRepository implements DashboardRepositoryInterface
{
    private const TABLE = 'dashboards';
    private const TABLE_WIDGETS = 'dashboard_widgets';

    public function findById(int $id, bool $includeWidgets = false): ?Dashboard
    {
        $row = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        $widgets = [];
        if ($includeWidgets) {
            $widgets = $this->getWidgetsForDashboard($id);
        }

        return $this->toDomainEntity($row, $widgets);
    }

    public function findAll(): array
    {
        $rows = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findAccessibleByUser(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('is_public', true);
            })
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findPublic(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_public', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findDefaultForUser(int $userId): ?Dashboard
    {
        $row = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(Dashboard $dashboard): Dashboard
    {
        $data = $this->toRowData($dashboard);

        if ($dashboard->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $dashboard->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $dashboard->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Save widgets if present
        $widgets = $dashboard->widgets();
        if (!empty($widgets)) {
            $this->saveWidgets($id, $widgets);
        }

        $savedWidgets = $this->getWidgetsForDashboard($id);
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->toDomainEntity($row, $savedWidgets);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->update(['deleted_at' => now()]) > 0;
    }

    public function forceDelete(int $id): bool
    {
        // Delete widgets first
        DB::table(self::TABLE_WIDGETS)->where('dashboard_id', $id)->delete();

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function restore(int $id): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]) > 0;
    }

    public function unsetDefaultForUser(int $userId, ?int $exceptDashboardId = null): void
    {
        $query = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('is_default', true);

        if ($exceptDashboardId !== null) {
            $query->where('id', '!=', $exceptDashboardId);
        }

        $query->update(['is_default' => false]);
    }

    private function getWidgetsForDashboard(int $dashboardId): array
    {
        $rows = DB::table(self::TABLE_WIDGETS)
            ->where('dashboard_id', $dashboardId)
            ->orderBy('id')
            ->get();

        return $rows->map(fn ($row) => $this->widgetToDomainEntity($row))->all();
    }

    /**
     * Convert a database row to a domain entity.
     */
    private function toDomainEntity(stdClass $row, array $widgets = []): Dashboard
    {
        $dashboard = Dashboard::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            description: $row->description,
            userId: $row->user_id ? UserId::fromInt((int) $row->user_id) : null,
            isDefault: (bool) $row->is_default,
            isPublic: (bool) $row->is_public,
            layout: $row->layout ? (is_string($row->layout) ? json_decode($row->layout, true) : $row->layout) : [],
            settings: $row->settings ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            filters: $row->filters ? (is_string($row->filters) ? json_decode($row->filters, true) : $row->filters) : [],
            refreshInterval: (int) ($row->refresh_interval ?? 0),
            createdAt: $row->created_at ? Timestamp::fromString($row->created_at) : null,
            updatedAt: $row->updated_at ? Timestamp::fromString($row->updated_at) : null,
            deletedAt: $row->deleted_at ? Timestamp::fromString($row->deleted_at) : null,
        );

        if (!empty($widgets)) {
            $dashboard->setWidgets($widgets);
        }

        return $dashboard;
    }

    /**
     * Convert a widget row to domain entity.
     */
    private function widgetToDomainEntity(stdClass $row): DashboardWidget
    {
        return DashboardWidget::reconstitute(
            id: (int) $row->id,
            dashboardId: (int) $row->dashboard_id,
            reportId: $row->report_id ? (int) $row->report_id : null,
            title: $row->title,
            type: WidgetType::from($row->type),
            config: $row->config ? (is_string($row->config) ? json_decode($row->config, true) : $row->config) : [],
            gridPosition: $row->grid_position
                ? (is_string($row->grid_position) ? json_decode($row->grid_position, true) : $row->grid_position)
                : ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
            refreshInterval: (int) ($row->refresh_interval ?? 0),
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(Dashboard $dashboard): array
    {
        return [
            'name' => $dashboard->name(),
            'description' => $dashboard->description(),
            'user_id' => $dashboard->userId()?->value(),
            'is_default' => $dashboard->isDefault(),
            'is_public' => $dashboard->isPublic(),
            'layout' => json_encode($dashboard->layout()),
            'settings' => json_encode($dashboard->settings()),
            'filters' => json_encode($dashboard->filters()),
            'refresh_interval' => $dashboard->refreshInterval(),
        ];
    }

    /**
     * Save widgets for a dashboard.
     *
     * @param array<DashboardWidget> $widgets
     */
    private function saveWidgets(int $dashboardId, array $widgets): void
    {
        // Delete existing widgets
        DB::table(self::TABLE_WIDGETS)->where('dashboard_id', $dashboardId)->delete();

        // Create new widgets
        foreach ($widgets as $widget) {
            DB::table(self::TABLE_WIDGETS)->insert([
                'dashboard_id' => $dashboardId,
                'report_id' => $widget->reportId(),
                'title' => $widget->title(),
                'type' => $widget->type()->value,
                'config' => json_encode($widget->config()),
                'grid_position' => json_encode($widget->gridPosition()),
                'refresh_interval' => $widget->refreshInterval(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
