<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Blueprint;
use App\Models\BlueprintRecordState;
use App\Models\BlueprintState;
use App\Models\ModuleRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlueprintRecordState>
 */
class BlueprintRecordStateFactory extends Factory
{
    protected $model = BlueprintRecordState::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blueprint_id' => Blueprint::factory(),
            'record_id' => ModuleRecord::factory(),
            'current_state_id' => BlueprintState::factory(),
            'state_entered_at' => now(),
        ];
    }

    /**
     * Set to initial state.
     */
    public function initialState(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_state_id' => BlueprintState::factory()->initial(),
            'state_entered_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Set state entered at a specific time.
     */
    public function enteredAt(\DateTimeInterface $dateTime): static
    {
        return $this->state(fn (array $attributes) => [
            'state_entered_at' => $dateTime,
        ]);
    }

    /**
     * Set state as recently entered.
     */
    public function recentlyEntered(): static
    {
        return $this->state(fn (array $attributes) => [
            'state_entered_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Set state as entered long ago.
     */
    public function staleState(): static
    {
        return $this->state(fn (array $attributes) => [
            'state_entered_at' => fake()->dateTimeBetween('-1 month', '-1 week'),
        ]);
    }

    /**
     * Attach to specific blueprint.
     */
    public function forBlueprint(Blueprint $blueprint): static
    {
        return $this->state(fn (array $attributes) => [
            'blueprint_id' => $blueprint->id,
        ]);
    }

    /**
     * Attach to specific record.
     */
    public function forRecord(ModuleRecord $record): static
    {
        return $this->state(fn (array $attributes) => [
            'record_id' => $record->id,
        ]);
    }

    /**
     * Set current state.
     */
    public function inState(BlueprintState $state): static
    {
        return $this->state(fn (array $attributes) => [
            'current_state_id' => $state->id,
            'blueprint_id' => $state->blueprint_id,
        ]);
    }
}
