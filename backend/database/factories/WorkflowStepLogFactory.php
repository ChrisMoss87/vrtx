<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<WorkflowStepLog>
 */
class WorkflowStepLogFactory extends Factory
{
    protected $model = WorkflowStepLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'execution_id' => WorkflowExecution::factory(),
            'step_id' => WorkflowStep::factory(),
            'status' => WorkflowStepLog::STATUS_PENDING,
            'started_at' => null,
            'completed_at' => null,
            'duration_ms' => null,
            'input_data' => null,
            'output_data' => null,
            'error_message' => null,
            'error_trace' => null,
            'retry_attempt' => 0,
        ];
    }

    /**
     * Set the step as running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkflowStepLog::STATUS_RUNNING,
            'started_at' => now(),
            'input_data' => ['record_id' => fake()->randomNumber()],
        ]);
    }

    /**
     * Set the step as completed.
     */
    public function completed(): static
    {
        $startedAt = now()->subMilliseconds(fake()->numberBetween(50, 5000));

        return $this->state(fn (array $attributes) => [
            'status' => WorkflowStepLog::STATUS_COMPLETED,
            'started_at' => $startedAt,
            'completed_at' => now(),
            'duration_ms' => now()->diffInMilliseconds($startedAt),
            'input_data' => ['record_id' => fake()->randomNumber()],
            'output_data' => ['success' => true],
        ]);
    }

    /**
     * Set the step as failed.
     */
    public function failed(): static
    {
        $startedAt = now()->subMilliseconds(fake()->numberBetween(50, 2000));

        return $this->state(fn (array $attributes) => [
            'status' => WorkflowStepLog::STATUS_FAILED,
            'started_at' => $startedAt,
            'completed_at' => now(),
            'duration_ms' => now()->diffInMilliseconds($startedAt),
            'input_data' => ['record_id' => fake()->randomNumber()],
            'error_message' => fake()->sentence(),
            'error_trace' => "Error at line " . fake()->numberBetween(1, 100),
        ]);
    }

    /**
     * Set the step as skipped.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkflowStepLog::STATUS_SKIPPED,
            'completed_at' => now(),
            'output_data' => ['skip_reason' => 'Condition not met'],
        ]);
    }

    /**
     * Set specific retry attempt.
     */
    public function retry(int $attempt): static
    {
        return $this->state(fn (array $attributes) => [
            'retry_attempt' => $attempt,
        ]);
    }

    /**
     * Attach to specific execution.
     */
    public function forExecution(WorkflowExecution $execution): static
    {
        return $this->state(fn (array $attributes) => [
            'execution_id' => $execution->id,
        ]);
    }

    /**
     * Attach to specific step.
     */
    public function forStep(WorkflowStep $step): static
    {
        return $this->state(fn (array $attributes) => [
            'step_id' => $step->id,
        ]);
    }
}
