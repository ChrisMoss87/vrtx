<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Reporting;

use App\Domain\Reporting\Entities\DashboardTemplate;
use App\Domain\Reporting\Entities\DashboardTemplateWidget;
use App\Domain\Reporting\Repositories\DashboardTemplateRepositoryInterface;
use App\Domain\Reporting\ValueObjects\WidgetType;
use App\Domain\Shared\ValueObjects\Timestamp;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the DashboardTemplateRepository.
 */
class EloquentDashboardTemplateRepository implements DashboardTemplateRepositoryInterface
{
    private const TABLE = 'dashboard_templates';
    private const TABLE_WIDGETS = 'dashboard_template_widgets';

    public function findById(int $id): ?DashboardTemplate
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $widgets = $this->getWidgetsForTemplate($id);

        return $this->toDomainEntity($row, $widgets);
    }

    public function findBySlug(string $slug): ?DashboardTemplate
    {
        $row = DB::table(self::TABLE)->where('slug', $slug)->first();

        if (!$row) {
            return null;
        }

        $widgets = $this->getWidgetsForTemplate((int) $row->id);

        return $this->toDomainEntity($row, $widgets);
    }

    public function findAllActive(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $rows->map(function ($row) {
            $widgets = $this->getWidgetsForTemplate((int) $row->id);
            return $this->toDomainEntity($row, $widgets);
        })->all();
    }

    public function findByCategory(string $category): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_active', true)
            ->where('category', $category)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $rows->map(function ($row) {
            $widgets = $this->getWidgetsForTemplate((int) $row->id);
            return $this->toDomainEntity($row, $widgets);
        })->all();
    }

    public function getCategories(): array
    {
        return DB::table(self::TABLE)
            ->where('is_active', true)
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->all();
    }

    public function save(DashboardTemplate $template): DashboardTemplate
    {
        $data = $this->toRowData($template);

        if ($template->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $template->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $template->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Save widgets
        $widgets = $template->widgets();
        if (!empty($widgets)) {
            $this->saveWidgets($id, $widgets);
        }

        $savedWidgets = $this->getWidgetsForTemplate($id);
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->toDomainEntity($row, $savedWidgets);
    }

    public function delete(DashboardTemplate $template): void
    {
        if ($template->getId() === null) {
            return;
        }

        // Delete widgets first
        DB::table(self::TABLE_WIDGETS)->where('template_id', $template->getId())->delete();
        DB::table(self::TABLE)->where('id', $template->getId())->delete();
    }

    private function getWidgetsForTemplate(int $templateId): array
    {
        $rows = DB::table(self::TABLE_WIDGETS)
            ->where('template_id', $templateId)
            ->orderBy('id')
            ->get();

        return $rows->map(fn ($row) => $this->widgetToDomainEntity($row))->all();
    }

    /**
     * Convert database row to domain entity.
     */
    private function toDomainEntity(stdClass $row, array $widgets = []): DashboardTemplate
    {
        $template = DashboardTemplate::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            slug: $row->slug,
            description: $row->description,
            category: $row->category,
            thumbnail: $row->thumbnail,
            settings: $row->settings ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            isActive: (bool) $row->is_active,
            sortOrder: (int) ($row->sort_order ?? 0),
            createdAt: $row->created_at ? Timestamp::fromString($row->created_at) : null,
            updatedAt: $row->updated_at ? Timestamp::fromString($row->updated_at) : null,
        );

        if (!empty($widgets)) {
            $template->setWidgets($widgets);
        }

        return $template;
    }

    /**
     * Convert widget row to domain entity.
     */
    private function widgetToDomainEntity(stdClass $row): DashboardTemplateWidget
    {
        return DashboardTemplateWidget::reconstitute(
            id: (int) $row->id,
            templateId: (int) $row->template_id,
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
     * Convert domain entity to row data.
     */
    private function toRowData(DashboardTemplate $template): array
    {
        return [
            'name' => $template->name(),
            'slug' => $template->slug(),
            'description' => $template->description(),
            'category' => $template->category(),
            'thumbnail' => $template->thumbnail(),
            'settings' => json_encode($template->settings()),
            'is_active' => $template->isActive(),
            'sort_order' => $template->sortOrder(),
        ];
    }

    /**
     * Save widgets for a template.
     *
     * @param array<DashboardTemplateWidget> $widgets
     */
    private function saveWidgets(int $templateId, array $widgets): void
    {
        // Remove existing widgets
        DB::table(self::TABLE_WIDGETS)->where('template_id', $templateId)->delete();

        // Create new widgets
        foreach ($widgets as $widget) {
            DB::table(self::TABLE_WIDGETS)->insert([
                'template_id' => $templateId,
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
