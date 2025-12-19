<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Reporting;

use App\Domain\Reporting\Entities\DashboardTemplate;
use App\Domain\Reporting\Entities\DashboardTemplateWidget;
use App\Domain\Reporting\Repositories\DashboardTemplateRepositoryInterface;
use App\Domain\Reporting\ValueObjects\WidgetType;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Models\DashboardTemplate as DashboardTemplateModel;
use App\Models\DashboardTemplateWidget as DashboardTemplateWidgetModel;

/**
 * Eloquent implementation of the DashboardTemplateRepository.
 */
class EloquentDashboardTemplateRepository implements DashboardTemplateRepositoryInterface
{
    public function findById(int $id): ?DashboardTemplate
    {
        $model = DashboardTemplateModel::with('widgets')->find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findBySlug(string $slug): ?DashboardTemplate
    {
        $model = DashboardTemplateModel::with('widgets')->where('slug', $slug)->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findAllActive(): array
    {
        $models = DashboardTemplateModel::with('widgets')
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByCategory(string $category): array
    {
        $models = DashboardTemplateModel::with('widgets')
            ->active()
            ->category($category)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function getCategories(): array
    {
        return DashboardTemplateModel::active()
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->all();
    }

    public function save(DashboardTemplate $template): DashboardTemplate
    {
        $data = $this->toModelData($template);

        if ($template->getId() !== null) {
            $model = DashboardTemplateModel::findOrFail($template->getId());
            $model->update($data);
        } else {
            $model = DashboardTemplateModel::create($data);
        }

        // Save widgets
        $widgets = $template->widgets();
        if (!empty($widgets)) {
            $this->saveWidgets($model, $widgets);
        }

        return $this->toDomainEntity($model->fresh(['widgets']));
    }

    public function delete(DashboardTemplate $template): void
    {
        if ($template->getId() === null) {
            return;
        }

        DashboardTemplateModel::destroy($template->getId());
    }

    /**
     * Convert Eloquent model to domain entity.
     */
    private function toDomainEntity(DashboardTemplateModel $model): DashboardTemplate
    {
        $template = DashboardTemplate::reconstitute(
            id: $model->id,
            name: $model->name,
            slug: $model->slug,
            description: $model->description,
            category: $model->category,
            thumbnail: $model->thumbnail,
            settings: $model->settings ?? [],
            isActive: $model->is_active,
            sortOrder: $model->sort_order,
            createdAt: $model->created_at ? Timestamp::fromDateTime($model->created_at) : null,
            updatedAt: $model->updated_at ? Timestamp::fromDateTime($model->updated_at) : null,
        );

        // Load widgets
        if ($model->relationLoaded('widgets')) {
            $widgets = $model->widgets->map(fn($w) => $this->widgetToDomainEntity($w))->all();
            $template->setWidgets($widgets);
        }

        return $template;
    }

    /**
     * Convert widget model to domain entity.
     */
    private function widgetToDomainEntity(DashboardTemplateWidgetModel $model): DashboardTemplateWidget
    {
        return DashboardTemplateWidget::reconstitute(
            id: $model->id,
            templateId: $model->template_id,
            title: $model->title,
            type: WidgetType::from($model->type),
            config: $model->config ?? [],
            gridPosition: $model->grid_position ?? ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
            refreshInterval: $model->refresh_interval ?? 0,
        );
    }

    /**
     * Convert domain entity to model data.
     */
    private function toModelData(DashboardTemplate $template): array
    {
        return [
            'name' => $template->name(),
            'slug' => $template->slug(),
            'description' => $template->description(),
            'category' => $template->category(),
            'thumbnail' => $template->thumbnail(),
            'settings' => $template->settings(),
            'is_active' => $template->isActive(),
            'sort_order' => $template->sortOrder(),
        ];
    }

    /**
     * Save widgets for a template.
     *
     * @param array<DashboardTemplateWidget> $widgets
     */
    private function saveWidgets(DashboardTemplateModel $model, array $widgets): void
    {
        // Remove existing widgets
        $model->widgets()->delete();

        // Create new widgets
        foreach ($widgets as $widget) {
            $model->widgets()->create([
                'title' => $widget->title(),
                'type' => $widget->type()->value,
                'config' => $widget->config(),
                'grid_position' => $widget->gridPosition(),
                'refresh_interval' => $widget->refreshInterval(),
            ]);
        }
    }
}
