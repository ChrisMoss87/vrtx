<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Report',
            'description' => $this->faker->sentence(),
            'module_id' => fn () => DB::table('modules')->where('api_name', 'deals')->first()?->id ?? DB::table('modules')->first()?->id,
            'user_id' => User::factory(),
            'type' => Report::TYPE_TABLE,
            'chart_type' => null,
            'is_public' => false,
            'is_favorite' => false,
            'config' => [],
            'filters' => [],
            'grouping' => [],
            'aggregations' => [],
            'sorting' => [],
            'date_range' => [],
        ];
    }

    /**
     * Create a table report.
     */
    public function table(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Report::TYPE_TABLE,
            'chart_type' => null,
        ]);
    }

    /**
     * Create a chart report.
     */
    public function chart(string $chartType = Report::CHART_BAR): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Report::TYPE_CHART,
            'chart_type' => $chartType,
        ]);
    }

    /**
     * Create a summary report.
     */
    public function summary(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Report::TYPE_SUMMARY,
        ]);
    }

    /**
     * Create a pivot report.
     */
    public function pivot(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Report::TYPE_PIVOT,
        ]);
    }

    /**
     * Create a bar chart report.
     */
    public function barChart(): static
    {
        return $this->chart(Report::CHART_BAR);
    }

    /**
     * Create a line chart report.
     */
    public function lineChart(): static
    {
        return $this->chart(Report::CHART_LINE);
    }

    /**
     * Create a pie chart report.
     */
    public function pieChart(): static
    {
        return $this->chart(Report::CHART_PIE);
    }

    /**
     * Mark report as public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Mark report as favorite.
     */
    public function favorite(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_favorite' => true,
        ]);
    }

    /**
     * Add filters to the report.
     */
    public function withFilters(array $filters): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => $filters,
        ]);
    }

    /**
     * Add grouping to the report.
     */
    public function withGrouping(array $grouping): static
    {
        return $this->state(fn (array $attributes) => [
            'grouping' => $grouping,
        ]);
    }

    /**
     * Add aggregations to the report.
     */
    public function withAggregations(array $aggregations): static
    {
        return $this->state(fn (array $attributes) => [
            'aggregations' => $aggregations,
        ]);
    }

    /**
     * Add date range to the report.
     */
    public function withDateRange(string $start, string $end): static
    {
        return $this->state(fn (array $attributes) => [
            'date_range' => [
                'start' => $start,
                'end' => $end,
            ],
        ]);
    }
}
