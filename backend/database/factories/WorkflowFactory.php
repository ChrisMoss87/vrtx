<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Module;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workflow>
 */
class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Workflow',
            'description' => $this->faker->sentence(),
            'module_id' => fn () => Module::where('api_name', 'deals')->first()?->id ?? Module::first()?->id,
            'is_active' => $this->faker->boolean(80),
            'priority' => $this->faker->numberBetween(0, 10),
            'trigger_type' => $this->faker->randomElement([
                Workflow::TRIGGER_RECORD_CREATED,
                Workflow::TRIGGER_RECORD_UPDATED,
                Workflow::TRIGGER_FIELD_CHANGED,
                Workflow::TRIGGER_MANUAL,
            ]),
            'trigger_config' => [],
            'trigger_timing' => Workflow::TIMING_ALL,
            'watched_fields' => [],
            'stop_on_first_match' => false,
            'conditions' => [],
            'run_once_per_record' => false,
            'allow_manual_trigger' => true,
            'delay_seconds' => 0,
            'execution_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
        ];
    }

    /**
     * Indicate that the workflow is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the workflow is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set trigger type to record_created.
     */
    public function onRecordCreated(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => Workflow::TRIGGER_RECORD_CREATED,
        ]);
    }

    /**
     * Set trigger type to record_updated.
     */
    public function onRecordUpdated(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => Workflow::TRIGGER_RECORD_UPDATED,
        ]);
    }

    /**
     * Set trigger type to field_changed.
     */
    public function onFieldChanged(array $fields = ['status']): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => Workflow::TRIGGER_FIELD_CHANGED,
            'watched_fields' => $fields,
        ]);
    }

    /**
     * Set trigger type to manual.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => Workflow::TRIGGER_MANUAL,
        ]);
    }

    /**
     * Create workflow with steps.
     */
    public function withSteps(int $count = 3): static
    {
        return $this->has(
            \App\Models\WorkflowStep::factory()->count($count),
            'steps'
        );
    }

    /**
     * Set workflow to run once per record.
     */
    public function runOnce(): static
    {
        return $this->state(fn (array $attributes) => [
            'run_once_per_record' => true,
        ]);
    }

    /**
     * Add conditions to the workflow.
     */
    public function withConditions(array $conditions): static
    {
        return $this->state(fn (array $attributes) => [
            'conditions' => $conditions,
        ]);
    }

    /**
     * Set a creator user.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}
