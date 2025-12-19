<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ForecastScenario;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ForecastScenario>
 */
class ForecastScenarioFactory extends Factory
{
    protected $model = ForecastScenario::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalUnweighted = $this->faker->randomFloat(2, 500000, 2000000);
        $totalWeighted = $totalUnweighted * 0.6;
        $targetAmount = $this->faker->randomFloat(2, 400000, 600000);

        return [
            'name' => $this->faker->randomElement([
                'Q4 Sales Forecast',
                'Monthly Pipeline Scenario',
                'Conservative Estimate',
                'Optimistic Projection',
                'Board Review Forecast',
            ]),
            'description' => $this->faker->sentence(),
            'user_id' => User::factory(),
            'module_id' => fn () => Module::where('api_name', 'deals')->first()?->id ?? Module::first()?->id,
            'period_start' => now()->startOfQuarter(),
            'period_end' => now()->endOfQuarter(),
            'scenario_type' => $this->faker->randomElement(array_keys(ForecastScenario::getScenarioTypes())),
            'is_baseline' => $this->faker->boolean(20),
            'is_shared' => $this->faker->boolean(40),
            'total_weighted' => $totalWeighted,
            'total_unweighted' => $totalUnweighted,
            'target_amount' => $targetAmount,
            'deal_count' => $this->faker->numberBetween(20, 80),
            'settings' => [
                'include_stages' => ['qualification', 'proposal', 'negotiation'],
                'probability_override' => false,
            ],
        ];
    }

    /**
     * Current state scenario.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'scenario_type' => ForecastScenario::TYPE_CURRENT,
            'name' => 'Current Pipeline State',
        ]);
    }

    /**
     * Best case scenario.
     */
    public function bestCase(): static
    {
        return $this->state(fn (array $attributes) => [
            'scenario_type' => ForecastScenario::TYPE_BEST_CASE,
            'name' => 'Best Case Scenario',
            'settings' => [
                'include_stages' => ['qualification', 'proposal', 'negotiation', 'demo'],
                'probability_override' => true,
                'probability_adjustment' => 1.2,
            ],
        ]);
    }

    /**
     * Worst case scenario.
     */
    public function worstCase(): static
    {
        return $this->state(fn (array $attributes) => [
            'scenario_type' => ForecastScenario::TYPE_WORST_CASE,
            'name' => 'Worst Case Scenario',
            'settings' => [
                'include_stages' => ['proposal', 'negotiation'],
                'probability_override' => true,
                'probability_adjustment' => 0.7,
            ],
        ]);
    }

    /**
     * Target hit scenario.
     */
    public function targetHit(): static
    {
        return $this->state(function (array $attributes) {
            $target = $attributes['target_amount'] ?? 500000;

            return [
                'scenario_type' => ForecastScenario::TYPE_TARGET_HIT,
                'name' => 'Target Achievement Plan',
                'total_weighted' => $target,
            ];
        });
    }

    /**
     * Custom scenario.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'scenario_type' => ForecastScenario::TYPE_CUSTOM,
            'name' => 'Custom Scenario',
        ]);
    }

    /**
     * Baseline scenario.
     */
    public function baseline(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_baseline' => true,
            'scenario_type' => ForecastScenario::TYPE_CURRENT,
        ]);
    }

    /**
     * Shared scenario.
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_shared' => true,
        ]);
    }

    /**
     * Private scenario.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_shared' => false,
        ]);
    }

    /**
     * Monthly period.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);
    }

    /**
     * Quarterly period.
     */
    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => now()->firstOfQuarter(),
            'period_end' => now()->lastOfQuarter(),
        ]);
    }
}
