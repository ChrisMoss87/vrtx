<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Goal\Entities\Goal;

use App\Domain\User\Entities\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Goal\Entities\Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $metricType = $this->faker->randomElement(['revenue', 'deals', 'leads', 'calls', 'meetings', 'activities']);
        $targetValue = $this->getTargetForMetric($metricType);
        $currentValue = $targetValue * $this->faker->randomFloat(2, 0.1, 1.1);
        $attainmentPercent = $targetValue > 0 ? ($currentValue / $targetValue) * 100 : 0;
        $status = $currentValue >= $targetValue ? Goal::STATUS_ACHIEVED : Goal::STATUS_IN_PROGRESS;

        return [
            'name' => $this->faker->randomElement([
                'Q' . $this->faker->numberBetween(1, 4) . ' Revenue Target',
                'Monthly Sales Goal',
                'New Customer Acquisition',
                'Demo Meetings Target',
                'Lead Generation Goal',
                'Activity Completion',
            ]),
            'description' => $this->faker->sentence(),
            'goal_type' => $this->faker->randomElement([Goal::TYPE_INDIVIDUAL, Goal::TYPE_TEAM, Goal::TYPE_COMPANY]),
            'user_id' => User::factory(),
            'team_id' => null,
            'metric_type' => $metricType,
            'metric_field' => null,
            'module_api_name' => $this->getModuleForMetric($metricType),
            'target_value' => $targetValue,
            'currency' => 'USD',
            'start_date' => Carbon::now()->startOfMonth(),
            'end_date' => Carbon::now()->endOfMonth(),
            'current_value' => $currentValue,
            'attainment_percent' => round($attainmentPercent, 2),
            'status' => $status,
            'achieved_at' => $status === Goal::STATUS_ACHIEVED ? $this->faker->dateTimeBetween('-7 days', 'now') : null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Get target value based on metric type.
     */
    private function getTargetForMetric(string $metric): float
    {
        return match ($metric) {
            'revenue' => $this->faker->randomFloat(2, 50000, 500000),
            'deals' => $this->faker->numberBetween(5, 50),
            'leads' => $this->faker->numberBetween(20, 200),
            'calls' => $this->faker->numberBetween(100, 500),
            'meetings' => $this->faker->numberBetween(20, 100),
            'activities' => $this->faker->numberBetween(200, 1000),
            default => $this->faker->numberBetween(10, 100),
        };
    }

    /**
     * Get module for metric type.
     */
    private function getModuleForMetric(string $metric): ?string
    {
        return match ($metric) {
            'revenue', 'deals' => 'deals',
            'leads' => 'leads',
            'calls' => 'calls',
            'meetings' => 'meetings',
            default => null,
        };
    }

    /**
     * Individual goal.
     */
    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'goal_type' => Goal::TYPE_INDIVIDUAL,
            'user_id' => User::factory(),
            'team_id' => null,
        ]);
    }

    /**
     * Team goal.
     */
    public function team(int $teamId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'goal_type' => Goal::TYPE_TEAM,
            'user_id' => null,
            'team_id' => $teamId ?? $this->faker->numberBetween(1, 10),
        ]);
    }

    /**
     * Company goal.
     */
    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'goal_type' => Goal::TYPE_COMPANY,
            'user_id' => null,
            'team_id' => null,
        ]);
    }

    /**
     * In progress goal.
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            $target = $attributes['target_value'] ?? 100000;
            $current = $target * $this->faker->randomFloat(2, 0.3, 0.9);
            return [
                'status' => Goal::STATUS_IN_PROGRESS,
                'current_value' => $current,
                'attainment_percent' => round(($current / $target) * 100, 2),
                'achieved_at' => null,
            ];
        });
    }

    /**
     * Achieved goal.
     */
    public function achieved(): static
    {
        return $this->state(function (array $attributes) {
            $target = $attributes['target_value'] ?? 100000;
            $current = $target * $this->faker->randomFloat(2, 1.0, 1.3);
            return [
                'status' => Goal::STATUS_ACHIEVED,
                'current_value' => $current,
                'attainment_percent' => round(($current / $target) * 100, 2),
                'achieved_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            ];
        });
    }

    /**
     * Missed goal.
     */
    public function missed(): static
    {
        return $this->state(function (array $attributes) {
            $target = $attributes['target_value'] ?? 100000;
            $current = $target * $this->faker->randomFloat(2, 0.3, 0.8);
            return [
                'status' => Goal::STATUS_MISSED,
                'current_value' => $current,
                'attainment_percent' => round(($current / $target) * 100, 2),
                'start_date' => Carbon::now()->subMonths(2)->startOfMonth(),
                'end_date' => Carbon::now()->subMonth()->endOfMonth(),
            ];
        });
    }

    /**
     * Paused goal.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Goal::STATUS_PAUSED,
        ]);
    }

    /**
     * Revenue goal.
     */
    public function revenue(float $target = null): static
    {
        $target = $target ?? $this->faker->randomFloat(2, 50000, 500000);
        return $this->state(fn (array $attributes) => [
            'name' => 'Revenue Target',
            'metric_type' => 'revenue',
            'module_api_name' => 'deals',
            'target_value' => $target,
            'currency' => 'USD',
        ]);
    }

    /**
     * Current period (this month).
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => Carbon::now()->startOfMonth(),
            'end_date' => Carbon::now()->endOfMonth(),
        ]);
    }

    /**
     * Quarterly goal.
     */
    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Q' . Carbon::now()->quarter . ' Target',
            'start_date' => Carbon::now()->startOfQuarter(),
            'end_date' => Carbon::now()->endOfQuarter(),
        ]);
    }
}
