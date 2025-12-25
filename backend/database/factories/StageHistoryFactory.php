<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<StageHistory>
 */
class StageHistoryFactory extends Factory
{
    protected $model = StageHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_record_id' => ModuleRecord::factory(),
            'pipeline_id' => Pipeline::factory(),
            'from_stage_id' => null,
            'to_stage_id' => Stage::factory(),
            'changed_by' => User::factory(),
            'reason' => fake()->optional()->sentence(),
            'duration_in_stage' => fake()->optional()->numberBetween(60, 86400 * 30), // 1 min to 30 days in seconds
        ];
    }

    /**
     * Set the from stage.
     */
    public function fromStage(Stage $stage): static
    {
        return $this->state(fn (array $attributes) => [
            'from_stage_id' => $stage->id,
            'pipeline_id' => $stage->pipeline_id,
        ]);
    }

    /**
     * Set the to stage.
     */
    public function toStage(Stage $stage): static
    {
        return $this->state(fn (array $attributes) => [
            'to_stage_id' => $stage->id,
            'pipeline_id' => $stage->pipeline_id,
        ]);
    }

    /**
     * Set both stages for a transition.
     */
    public function transition(Stage $from, Stage $to): static
    {
        return $this->state(fn (array $attributes) => [
            'from_stage_id' => $from->id,
            'to_stage_id' => $to->id,
            'pipeline_id' => $from->pipeline_id,
        ]);
    }

    /**
     * Initial stage entry (no from stage).
     */
    public function initial(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_stage_id' => null,
            'duration_in_stage' => null,
        ]);
    }

    /**
     * Add a reason for the transition.
     */
    public function withReason(string $reason): static
    {
        return $this->state(fn (array $attributes) => [
            'reason' => $reason,
        ]);
    }

    /**
     * Attach to specific record.
     */
    public function forRecord(ModuleRecord $record): static
    {
        return $this->state(fn (array $attributes) => [
            'module_record_id' => $record->id,
        ]);
    }

    /**
     * Attach to specific pipeline.
     */
    public function forPipeline(Pipeline $pipeline): static
    {
        return $this->state(fn (array $attributes) => [
            'pipeline_id' => $pipeline->id,
        ]);
    }

    /**
     * Set who made the change.
     */
    public function changedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'changed_by' => $user->id,
        ]);
    }
}
