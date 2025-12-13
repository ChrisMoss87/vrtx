<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowRunHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowRunHistory>
 */
class WorkflowRunHistoryFactory extends Factory
{
    protected $model = WorkflowRunHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $triggerTypes = [
            Workflow::TRIGGER_RECORD_CREATED,
            Workflow::TRIGGER_RECORD_UPDATED,
            Workflow::TRIGGER_FIELD_CHANGED,
            Workflow::TRIGGER_MANUAL,
        ];

        return [
            'workflow_id' => Workflow::factory(),
            'record_id' => fake()->randomNumber(),
            'record_type' => 'App\\Models\\ModuleRecord',
            'trigger_type' => fake()->randomElement($triggerTypes),
            'executed_at' => now(),
        ];
    }

    /**
     * Set record created trigger.
     */
    public function recordCreated(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => Workflow::TRIGGER_RECORD_CREATED,
        ]);
    }

    /**
     * Set record updated trigger.
     */
    public function recordUpdated(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => Workflow::TRIGGER_RECORD_UPDATED,
        ]);
    }

    /**
     * Set field changed trigger.
     */
    public function fieldChanged(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => Workflow::TRIGGER_FIELD_CHANGED,
        ]);
    }

    /**
     * Set manual trigger.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => Workflow::TRIGGER_MANUAL,
        ]);
    }

    /**
     * Set specific record.
     */
    public function forRecord(int $recordId, string $recordType = 'App\\Models\\ModuleRecord'): static
    {
        return $this->state(fn (array $attributes) => [
            'record_id' => $recordId,
            'record_type' => $recordType,
        ]);
    }

    /**
     * Attach to specific workflow.
     */
    public function forWorkflow(Workflow $workflow): static
    {
        return $this->state(fn (array $attributes) => [
            'workflow_id' => $workflow->id,
        ]);
    }

    /**
     * Set executed at a specific time.
     */
    public function executedAt(\DateTimeInterface $dateTime): static
    {
        return $this->state(fn (array $attributes) => [
            'executed_at' => $dateTime,
        ]);
    }
}
