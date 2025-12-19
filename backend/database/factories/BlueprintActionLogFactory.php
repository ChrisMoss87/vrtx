<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlueprintActionLog;
use App\Models\BlueprintTransitionAction;
use App\Models\BlueprintTransitionExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlueprintActionLog>
 */
class BlueprintActionLogFactory extends Factory
{
    protected $model = BlueprintActionLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'execution_id' => BlueprintTransitionExecution::factory(),
            'action_id' => BlueprintTransitionAction::factory(),
            'status' => $this->faker->randomElement(['success', 'failed']),
            'result' => [
                'message' => 'Action executed',
            ],
            'executed_at' => now(),
        ];
    }

    /**
     * Success status.
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'result' => [
                'message' => 'Action completed successfully',
            ],
        ]);
    }

    /**
     * Failed status.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'result' => [
                'error' => 'Action failed',
                'reason' => 'Configuration error',
            ],
        ]);
    }
}
