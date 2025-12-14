<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Reporting;

use App\Domain\Reporting\Entities\Dashboard;
use App\Domain\Reporting\Entities\DashboardWidget;
use App\Domain\Reporting\Repositories\DashboardRepositoryInterface;
use App\Domain\Reporting\ValueObjects\WidgetType;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Models\Dashboard as DashboardModel;
use App\Models\DashboardWidget as DashboardWidgetModel;

/**
 * Eloquent implementation of the DashboardRepository.
 */
class EloquentDashboardRepository implements DashboardRepositoryInterface
{
    public function findById(int $id, bool $includeWidgets = false): ?Dashboard
    {
        $query = DashboardModel::query();

        if ($includeWidgets) {
            $query->with('widgets');
        }

        $model = $query->find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model, $includeWidgets);
    }

    public function findAll(): array
    {
        $models = DashboardModel::orderBy('name')->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findAccessibleByUser(int $userId): array
    {
        $models = DashboardModel::where(function ($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->orWhere('is_public', true);
        })
        ->orderBy('name')
        ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findPublic(): array
    {
        $models = DashboardModel::where('is_public', true)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findDefaultForUser(int $userId): ?Dashboard
    {
        $model = DashboardModel::where('user_id', $userId)
            ->where('is_default', true)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function save(Dashboard $dashboard): Dashboard
    {
        $data = $this->toModelData($dashboard);

        if ($dashboard->getId() !== null) {
            $model = DashboardModel::findOrFail($dashboard->getId());
            $model->update($data);
        } else {
            $model = DashboardModel::create($data);
        }

        // Save widgets if present
        $widgets = $dashboard->widgets();
        if (!empty($widgets)) {
            $this->saveWidgets($model, $widgets);
        }

        return $this->toDomainEntity($model->fresh(['widgets']), !empty($widgets));
    }

    public function delete(int $id): bool
    {
        $model = DashboardModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    public function forceDelete(int $id): bool
    {
        $model = DashboardModel::withTrashed()->find($id);

        if (!$model) {
            return false;
        }

        // Delete widgets first
        $model->widgets()->forceDelete();

        return $model->forceDelete() ?? false;
    }

    public function restore(int $id): bool
    {
        $model = DashboardModel::withTrashed()->find($id);

        if (!$model || !$model->trashed()) {
            return false;
        }

        return $model->restore() ?? false;
    }

    public function unsetDefaultForUser(int $userId, ?int $exceptDashboardId = null): void
    {
        $query = DashboardModel::where('user_id', $userId)
            ->where('is_default', true);

        if ($exceptDashboardId !== null) {
            $query->where('id', '!=', $exceptDashboardId);
        }

        $query->update(['is_default' => false]);
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(DashboardModel $model, bool $includeWidgets = false): Dashboard
    {
        $dashboard = Dashboard::reconstitute(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            userId: $model->user_id ? UserId::fromInt($model->user_id) : null,
            isDefault: $model->is_default,
            isPublic: $model->is_public,
            layout: $model->layout ?? [],
            settings: $model->settings ?? [],
            filters: $model->filters ?? [],
            refreshInterval: $model->refresh_interval ?? 0,
            createdAt: $model->created_at
                ? Timestamp::fromDateTime($model->created_at)
                : null,
            updatedAt: $model->updated_at
                ? Timestamp::fromDateTime($model->updated_at)
                : null,
            deletedAt: $model->deleted_at
                ? Timestamp::fromDateTime($model->deleted_at)
                : null,
        );

        // Add widgets to dashboard
        if ($includeWidgets && $model->relationLoaded('widgets')) {
            $widgets = $model->widgets->map(fn($w) => $this->widgetToDomainEntity($w))->all();
            $dashboard->setWidgets($widgets);
        }

        return $dashboard;
    }

    /**
     * Convert a DashboardWidget model to domain entity.
     */
    private function widgetToDomainEntity(DashboardWidgetModel $model): DashboardWidget
    {
        return DashboardWidget::reconstitute(
            id: $model->id,
            dashboardId: $model->dashboard_id,
            reportId: $model->report_id,
            title: $model->title,
            type: WidgetType::from($model->type),
            config: $model->config ?? [],
            position: $model->position ?? 0,
            size: $model->size ?? ['w' => 6, 'h' => 4],
            refreshInterval: $model->refresh_interval ?? 0,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(Dashboard $dashboard): array
    {
        return [
            'name' => $dashboard->name(),
            'description' => $dashboard->description(),
            'user_id' => $dashboard->userId()?->value(),
            'is_default' => $dashboard->isDefault(),
            'is_public' => $dashboard->isPublic(),
            'layout' => $dashboard->layout(),
            'settings' => $dashboard->settings(),
            'filters' => $dashboard->filters(),
            'refresh_interval' => $dashboard->refreshInterval(),
        ];
    }

    /**
     * Save widgets for a dashboard.
     *
     * @param array<DashboardWidget> $widgets
     */
    private function saveWidgets(DashboardModel $dashboardModel, array $widgets): void
    {
        // Delete existing widgets
        $dashboardModel->widgets()->delete();

        // Create new widgets
        foreach ($widgets as $widget) {
            DashboardWidgetModel::create([
                'dashboard_id' => $dashboardModel->id,
                'report_id' => $widget->reportId(),
                'title' => $widget->title(),
                'type' => $widget->type()->value,
                'config' => $widget->config(),
                'position' => $widget->position(),
                'size' => $widget->size(),
                'refresh_interval' => $widget->refreshInterval(),
            ]);
        }
    }
}
