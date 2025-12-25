<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<RottingAlert>
 */
class RottingAlertFactory extends Factory
{
    protected $model = RottingAlert::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_record_id' => ModuleRecord::factory(),
            'stage_id' => Stage::factory(),
            'user_id' => User::factory(),
            'alert_type' => fake()->randomElement([
                RottingAlert::TYPE_WARNING,
                RottingAlert::TYPE_STALE,
                RottingAlert::TYPE_ROTTING,
            ]),
            'days_inactive' => fake()->numberBetween(5, 30),
            'sent_at' => now(),
            'acknowledged' => false,
            'acknowledged_at' => null,
        ];
    }

    /**
     * Create a warning alert (50-75% threshold).
     */
    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => RottingAlert::TYPE_WARNING,
            'days_inactive' => fake()->numberBetween(4, 6),
        ]);
    }

    /**
     * Create a stale alert (75-100% threshold).
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => RottingAlert::TYPE_STALE,
            'days_inactive' => fake()->numberBetween(6, 8),
        ]);
    }

    /**
     * Create a rotting alert (>100% threshold).
     */
    public function rotting(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => RottingAlert::TYPE_ROTTING,
            'days_inactive' => fake()->numberBetween(8, 20),
        ]);
    }

    /**
     * Mark as acknowledged.
     */
    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledged' => true,
            'acknowledged_at' => now(),
        ]);
    }

    /**
     * Set for a specific record.
     */
    public function forRecord(ModuleRecord $record): static
    {
        return $this->state(fn (array $attributes) => [
            'module_record_id' => $record->id,
        ]);
    }

    /**
     * Set for a specific stage.
     */
    public function forStage(Stage $stage): static
    {
        return $this->state(fn (array $attributes) => [
            'stage_id' => $stage->id,
        ]);
    }

    /**
     * Set for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set sent in the past.
     */
    public function sentDaysAgo(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => now()->subDays($days),
        ]);
    }
}
