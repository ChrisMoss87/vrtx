<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Forecasting\Entities\ForecastAdjustment;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Forecasting\Entities\ForecastAdjustment>
 */
class ForecastAdjustmentFactory extends Factory
{
    protected $model = ForecastAdjustment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([
            ForecastAdjustment::TYPE_CATEGORY_CHANGE,
            ForecastAdjustment::TYPE_AMOUNT_OVERRIDE,
            ForecastAdjustment::TYPE_CLOSE_DATE_CHANGE,
        ]);

        return [
            'user_id' => User::factory(),
            'module_record_id' => ModuleRecord::factory(),
            'adjustment_type' => $type,
            'old_value' => $this->getOldValue($type),
            'new_value' => $this->getNewValue($type),
            'reason' => $this->faker->randomElement([
                'Updated based on customer feedback',
                'Pipeline review adjustment',
                'Manager override',
                'Deal slipped from original timeline',
                'Increased confidence after demo',
            ]),
        ];
    }

    private function getOldValue(string $type): string
    {
        return match ($type) {
            ForecastAdjustment::TYPE_CATEGORY_CHANGE => $this->faker->randomElement(['pipeline', 'best_case']),
            ForecastAdjustment::TYPE_AMOUNT_OVERRIDE => (string) $this->faker->numberBetween(50000, 200000),
            ForecastAdjustment::TYPE_CLOSE_DATE_CHANGE => now()->addDays($this->faker->numberBetween(10, 30))->toDateString(),
            default => '',
        };
    }

    private function getNewValue(string $type): string
    {
        return match ($type) {
            ForecastAdjustment::TYPE_CATEGORY_CHANGE => $this->faker->randomElement(['commit', 'closed']),
            ForecastAdjustment::TYPE_AMOUNT_OVERRIDE => (string) $this->faker->numberBetween(60000, 250000),
            ForecastAdjustment::TYPE_CLOSE_DATE_CHANGE => now()->addDays($this->faker->numberBetween(40, 60))->toDateString(),
            default => '',
        };
    }

    /**
     * Category change adjustment.
     */
    public function categoryChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustment_type' => ForecastAdjustment::TYPE_CATEGORY_CHANGE,
            'old_value' => 'pipeline',
            'new_value' => 'commit',
            'reason' => 'Moved to commit based on verbal agreement',
        ]);
    }

    /**
     * Amount override adjustment.
     */
    public function amountOverride(): static
    {
        $oldAmount = $this->faker->numberBetween(50000, 150000);
        $newAmount = $oldAmount + $this->faker->numberBetween(10000, 50000);

        return $this->state(fn (array $attributes) => [
            'adjustment_type' => ForecastAdjustment::TYPE_AMOUNT_OVERRIDE,
            'old_value' => (string) $oldAmount,
            'new_value' => (string) $newAmount,
            'reason' => 'Increased deal size after upsell opportunity',
        ]);
    }

    /**
     * Close date change adjustment.
     */
    public function closeDateChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustment_type' => ForecastAdjustment::TYPE_CLOSE_DATE_CHANGE,
            'old_value' => now()->addDays(15)->toDateString(),
            'new_value' => now()->addDays(30)->toDateString(),
            'reason' => 'Deal pushed due to customer budget cycle',
        ]);
    }
}
