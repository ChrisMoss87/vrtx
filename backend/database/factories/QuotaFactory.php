<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Quota;
use App\Infrastructure\Persistence\Eloquent\Models\QuotaPeriod;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\Quota>
 */
class QuotaFactory extends Factory
{
    protected $model = Quota::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $metricType = $this->faker->randomElement([
            Quota::METRIC_REVENUE,
            Quota::METRIC_DEALS,
            Quota::METRIC_LEADS,
            Quota::METRIC_CALLS,
            Quota::METRIC_MEETINGS,
        ]);

        $targetValue = $this->getTargetForMetric($metricType);
        $currentValue = $targetValue * $this->faker->randomFloat(2, 0.2, 1.2);
        $attainmentPercent = $targetValue > 0 ? ($currentValue / $targetValue) * 100 : 0;

        return [
            'period_id' => QuotaPeriod::factory(),
            'user_id' => User::factory(),
            'team_id' => null,
            'metric_type' => $metricType,
            'metric_field' => null,
            'module_api_name' => $this->getModuleForMetric($metricType),
            'target_value' => $targetValue,
            'currency' => 'USD',
            'current_value' => $currentValue,
            'attainment_percent' => round($attainmentPercent, 2),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Get target value based on metric type.
     */
    private function getTargetForMetric(string $metric): float
    {
        return match ($metric) {
            Quota::METRIC_REVENUE => $this->faker->randomFloat(2, 50000, 500000),
            Quota::METRIC_DEALS => $this->faker->numberBetween(5, 50),
            Quota::METRIC_LEADS => $this->faker->numberBetween(20, 200),
            Quota::METRIC_CALLS => $this->faker->numberBetween(100, 500),
            Quota::METRIC_MEETINGS => $this->faker->numberBetween(20, 100),
            Quota::METRIC_ACTIVITIES => $this->faker->numberBetween(200, 1000),
            default => $this->faker->numberBetween(10, 100),
        };
    }

    /**
     * Get module for metric type.
     */
    private function getModuleForMetric(string $metric): ?string
    {
        return match ($metric) {
            Quota::METRIC_REVENUE, Quota::METRIC_DEALS => 'deals',
            Quota::METRIC_LEADS => 'leads',
            Quota::METRIC_CALLS => 'calls',
            Quota::METRIC_MEETINGS => 'meetings',
            default => null,
        };
    }

    /**
     * Revenue quota.
     */
    public function revenue(float $target = null): static
    {
        $target = $target ?? $this->faker->randomFloat(2, 50000, 500000);
        return $this->state(fn (array $attributes) => [
            'metric_type' => Quota::METRIC_REVENUE,
            'module_api_name' => 'deals',
            'target_value' => $target,
            'currency' => 'USD',
        ]);
    }

    /**
     * Deals quota.
     */
    public function deals(int $target = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => Quota::METRIC_DEALS,
            'module_api_name' => 'deals',
            'target_value' => $target ?? $this->faker->numberBetween(5, 50),
            'currency' => 'USD',
        ]);
    }

    /**
     * Leads quota.
     */
    public function leads(int $target = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => Quota::METRIC_LEADS,
            'module_api_name' => 'leads',
            'target_value' => $target ?? $this->faker->numberBetween(20, 200),
            'currency' => 'USD',
        ]);
    }

    /**
     * Calls quota.
     */
    public function calls(int $target = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => Quota::METRIC_CALLS,
            'module_api_name' => 'calls',
            'target_value' => $target ?? $this->faker->numberBetween(100, 500),
            'currency' => 'USD',
        ]);
    }

    /**
     * Meetings quota.
     */
    public function meetings(int $target = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => Quota::METRIC_MEETINGS,
            'module_api_name' => 'meetings',
            'target_value' => $target ?? $this->faker->numberBetween(20, 100),
            'currency' => 'USD',
        ]);
    }

    /**
     * High attainment (>100%).
     */
    public function achieved(): static
    {
        return $this->state(function (array $attributes) {
            $target = $attributes['target_value'] ?? 100000;
            $current = $target * $this->faker->randomFloat(2, 1.0, 1.5);
            return [
                'current_value' => $current,
                'attainment_percent' => round(($current / $target) * 100, 2),
            ];
        });
    }

    /**
     * On track (50-99%).
     */
    public function onTrack(): static
    {
        return $this->state(function (array $attributes) {
            $target = $attributes['target_value'] ?? 100000;
            $current = $target * $this->faker->randomFloat(2, 0.5, 0.99);
            return [
                'current_value' => $current,
                'attainment_percent' => round(($current / $target) * 100, 2),
            ];
        });
    }

    /**
     * Behind (0-49%).
     */
    public function behind(): static
    {
        return $this->state(function (array $attributes) {
            $target = $attributes['target_value'] ?? 100000;
            $current = $target * $this->faker->randomFloat(2, 0.1, 0.49);
            return [
                'current_value' => $current,
                'attainment_percent' => round(($current / $target) * 100, 2),
            ];
        });
    }

    /**
     * For specific user.
     */
    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * For team (not individual).
     */
    public function forTeam(int $teamId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'team_id' => $teamId,
        ]);
    }
}
