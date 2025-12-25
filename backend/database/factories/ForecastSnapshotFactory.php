<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ForecastSnapshot>
 */
class ForecastSnapshotFactory extends Factory
{
    protected $model = ForecastSnapshot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $commitAmount = $this->faker->randomFloat(2, 100000, 500000);
        $bestCaseAmount = $this->faker->randomFloat(2, 50000, 200000);
        $pipelineAmount = $this->faker->randomFloat(2, 200000, 1000000);

        return [
            'user_id' => User::factory(),
            'pipeline_id' => Pipeline::factory(),
            'period_type' => $this->faker->randomElement([
                ForecastSnapshot::PERIOD_MONTH,
                ForecastSnapshot::PERIOD_QUARTER,
            ]),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'commit_amount' => $commitAmount,
            'best_case_amount' => $bestCaseAmount,
            'pipeline_amount' => $pipelineAmount,
            'weighted_amount' => $this->faker->randomFloat(2, 150000, 400000),
            'closed_won_amount' => $this->faker->randomFloat(2, 50000, 200000),
            'deal_count' => $this->faker->numberBetween(10, 50),
            'snapshot_date' => now()->toDateString(),
            'metadata' => [
                'source' => 'daily_snapshot',
                'avg_deal_size' => $this->faker->randomFloat(2, 10000, 50000),
            ],
        ];
    }

    /**
     * Weekly period.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => ForecastSnapshot::PERIOD_WEEK,
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
        ]);
    }

    /**
     * Monthly period.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => ForecastSnapshot::PERIOD_MONTH,
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
            'period_type' => ForecastSnapshot::PERIOD_QUARTER,
            'period_start' => now()->firstOfQuarter(),
            'period_end' => now()->lastOfQuarter(),
        ]);
    }

    /**
     * Yearly period.
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => ForecastSnapshot::PERIOD_YEAR,
            'period_start' => now()->startOfYear(),
            'period_end' => now()->endOfYear(),
            'commit_amount' => $this->faker->randomFloat(2, 1000000, 5000000),
            'best_case_amount' => $this->faker->randomFloat(2, 500000, 2000000),
            'pipeline_amount' => $this->faker->randomFloat(2, 2000000, 10000000),
        ]);
    }

    /**
     * High performing forecast.
     */
    public function highPerforming(): static
    {
        $closedWon = $this->faker->randomFloat(2, 300000, 500000);
        $weighted = $closedWon * 0.8;

        return $this->state(fn (array $attributes) => [
            'closed_won_amount' => $closedWon,
            'weighted_amount' => $weighted,
            'deal_count' => $this->faker->numberBetween(30, 60),
        ]);
    }

    /**
     * Today's snapshot.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'snapshot_date' => now()->toDateString(),
        ]);
    }

    /**
     * Historical snapshot.
     */
    public function historical(int $daysAgo = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'snapshot_date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }
}
