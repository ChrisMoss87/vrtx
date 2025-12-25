<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Quota;
use App\Infrastructure\Persistence\Eloquent\Models\QuotaSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\QuotaSnapshot>
 */
class QuotaSnapshotFactory extends Factory
{
    protected $model = QuotaSnapshot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currentValue = $this->faker->randomFloat(2, 0, 200000);
        $targetValue = $this->faker->randomFloat(2, 100000, 200000);
        $attainmentPercent = $targetValue > 0 ? ($currentValue / $targetValue) * 100 : 0;

        return [
            'quota_id' => Quota::factory(),
            'snapshot_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'current_value' => $currentValue,
            'attainment_percent' => round($attainmentPercent, 2),
        ];
    }

    /**
     * Recent snapshot.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'snapshot_date' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Historical snapshot.
     */
    public function historical(): static
    {
        return $this->state(fn (array $attributes) => [
            'snapshot_date' => $this->faker->dateTimeBetween('-90 days', '-30 days'),
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
     * High attainment snapshot.
     */
    public function highAttainment(): static
    {
        return $this->state(fn (array $attributes) => [
            'attainment_percent' => $this->faker->randomFloat(2, 80, 150),
        ]);
    }

    /**
     * Low attainment snapshot.
     */
    public function lowAttainment(): static
    {
        return $this->state(fn (array $attributes) => [
            'attainment_percent' => $this->faker->randomFloat(2, 10, 40),
        ]);
    }
}
