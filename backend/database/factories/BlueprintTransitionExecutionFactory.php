<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Blueprint\Entities\BlueprintTransitionExecution;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Blueprint\Entities\BlueprintTransitionExecution>
 */
class BlueprintTransitionExecutionFactory extends Factory
{
    protected $model = BlueprintTransitionExecution::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transition_id' => BlueprintTransition::factory(),
            'record_id' => $this->faker->numberBetween(1, 1000),
            'from_state_id' => BlueprintState::factory(),
            'to_state_id' => BlueprintState::factory(),
            'executed_by' => User::factory(),
            'status' => BlueprintTransitionExecution::STATUS_PENDING,
            'requirements_data' => [],
            'action_results' => [],
            'started_at' => now(),
            'completed_at' => null,
            'error_message' => null,
        ];
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintTransitionExecution::STATUS_PENDING,
            'completed_at' => null,
        ]);
    }

    /**
     * Pending requirements status.
     */
    public function pendingRequirements(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintTransitionExecution::STATUS_PENDING_REQUIREMENTS,
        ]);
    }

    /**
     * Pending approval status.
     */
    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintTransitionExecution::STATUS_PENDING_APPROVAL,
        ]);
    }

    /**
     * Completed status.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintTransitionExecution::STATUS_COMPLETED,
            'completed_at' => now(),
            'action_results' => [
                ['action_id' => 1, 'status' => 'success'],
            ],
        ]);
    }

    /**
     * Failed status.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintTransitionExecution::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => 'Transition failed due to validation error',
        ]);
    }

    /**
     * Rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BlueprintTransitionExecution::STATUS_REJECTED,
            'completed_at' => now(),
        ]);
    }

    /**
     * With requirements data.
     */
    public function withRequirements(array $data = []): static
    {
        return $this->state(fn (array $attributes) => [
            'requirements_data' => $data ?: [
                'notes' => 'Closing notes for the deal',
                'attachment_id' => 123,
            ],
        ]);
    }
}
