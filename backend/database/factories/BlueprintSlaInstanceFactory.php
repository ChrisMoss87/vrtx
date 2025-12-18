<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlueprintSla;
use App\Models\BlueprintSlaInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlueprintSlaInstance>
 */
class BlueprintSlaInstanceFactory extends Factory
{
    protected $model = BlueprintSlaInstance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stateEnteredAt = $this->faker->dateTimeBetween('-24 hours', '-1 hour');

        return [
            'sla_id' => BlueprintSla::factory(),
            'record_id' => $this->faker->numberBetween(1, 1000),
            'state_entered_at' => $stateEnteredAt,
            'due_at' => now()->addHours($this->faker->numberBetween(4, 48)),
            'status' => BlueprintSlaInstance::STATUS_ACTIVE,
            'completed_at' => null,
        ];
    }

    /**
     * Active status.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintSlaInstance::STATUS_ACTIVE,
            'completed_at' => null,
        ]);
    }

    /**
     * Completed status.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintSlaInstance::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Breached status.
     */
    public function breached(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintSlaInstance::STATUS_BREACHED,
            'due_at' => now()->subHours(2),
        ]);
    }

    /**
     * Approaching breach (80%+ elapsed).
     */
    public function approaching(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintSlaInstance::STATUS_ACTIVE,
            'state_entered_at' => now()->subHours(8),
            'due_at' => now()->addHours(2),
        ]);
    }

    /**
     * Overdue (past due date but not yet marked breached).
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintSlaInstance::STATUS_ACTIVE,
            'due_at' => now()->subHours(1),
        ]);
    }
}
