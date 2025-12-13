<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkflowExecution>
 */
class WorkflowExecutionFactory extends Factory
{
    protected $model = WorkflowExecution::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'trigger_record_id' => null,
            'trigger_record_type' => null,
            'trigger_type' => $this->faker->randomElement([
                Workflow::TRIGGER_RECORD_CREATED,
                Workflow::TRIGGER_RECORD_UPDATED,
                Workflow::TRIGGER_MANUAL,
            ]),
            'status' => $this->faker->randomElement(['pending', 'running', 'completed', 'failed']),
            'context_data' => [],
            'started_at' => now(),
            'completed_at' => null,
            'error_message' => null,
        ];
    }

    /**
     * Mark execution as pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Mark execution as running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    /**
     * Mark execution as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark execution as failed.
     */
    public function failed(string $error = 'Execution failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'error_message' => $error,
        ]);
    }

    /**
     * Set execution context data.
     */
    public function withContext(array $context): static
    {
        return $this->state(fn (array $attributes) => [
            'context_data' => $context,
        ]);
    }
}
