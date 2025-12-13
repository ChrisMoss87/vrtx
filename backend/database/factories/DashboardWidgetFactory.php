<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DashboardWidget>
 */
class DashboardWidgetFactory extends Factory
{
    protected $model = DashboardWidget::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dashboard_id' => Dashboard::factory(),
            'report_id' => null,
            'type' => $this->faker->randomElement(['kpi', 'chart', 'table', 'activity', 'tasks']),
            'title' => $this->faker->words(3, true),
            'config' => [],
            'position' => $this->faker->numberBetween(0, 10),
            'width' => $this->faker->randomElement([1, 2, 3, 4]),
            'height' => $this->faker->randomElement([1, 2]),
        ];
    }

    /**
     * Create a KPI widget.
     */
    public function kpi(array $config = []): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'kpi',
            'config' => array_merge([
                'metric' => 'count',
                'label' => 'Total Records',
            ], $config),
        ]);
    }

    /**
     * Create a chart widget.
     */
    public function chart(string $chartType = 'bar'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'chart',
            'config' => [
                'chart_type' => $chartType,
            ],
        ]);
    }

    /**
     * Create a table widget.
     */
    public function table(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'table',
        ]);
    }

    /**
     * Create an activity widget.
     */
    public function activity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'activity',
            'config' => [
                'limit' => 10,
            ],
        ]);
    }

    /**
     * Create a tasks widget.
     */
    public function tasks(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'tasks',
            'config' => [
                'limit' => 10,
                'show_completed' => false,
            ],
        ]);
    }

    /**
     * Link to a report.
     */
    public function forReport(Report $report): static
    {
        return $this->state(fn (array $attributes) => [
            'report_id' => $report->id,
        ]);
    }

    /**
     * Set widget size.
     */
    public function size(int $width, int $height): static
    {
        return $this->state(fn (array $attributes) => [
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Set widget position.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }
}
