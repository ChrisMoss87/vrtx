<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalProgressLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoalProgressLog>
 */
class GoalProgressLogFactory extends Factory
{
    protected $model = GoalProgressLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $value = $this->faker->randomFloat(2, 1000, 100000);
        $changeAmount = $this->faker->randomFloat(2, 100, 10000);

        return [
            'goal_id' => Goal::factory(),
            'log_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'value' => $value,
            'change_amount' => $changeAmount,
            'change_source' => $this->faker->randomElement(['deal_won', 'lead_created', 'call_logged', 'meeting_held', 'manual']),
            'source_record_id' => $this->faker->optional(0.7)->numberBetween(1, 1000),
        ];
    }

    /**
     * From deal won.
     */
    public function fromDeal(int $dealId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'change_source' => 'deal_won',
            'source_record_id' => $dealId ?? $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * From lead created.
     */
    public function fromLead(int $leadId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'change_source' => 'lead_created',
            'source_record_id' => $leadId ?? $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * From call logged.
     */
    public function fromCall(int $callId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'change_source' => 'call_logged',
            'source_record_id' => $callId ?? $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * From meeting held.
     */
    public function fromMeeting(int $meetingId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'change_source' => 'meeting_held',
            'source_record_id' => $meetingId ?? $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * Manual entry.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_source' => 'manual',
            'source_record_id' => null,
        ]);
    }

    /**
     * Recent log.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'log_date' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Today's log.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'log_date' => now()->toDateString(),
        ]);
    }

    /**
     * Positive change (increase).
     */
    public function increase(float $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'change_amount' => $amount ?? $this->faker->randomFloat(2, 100, 10000),
        ]);
    }

    /**
     * Negative change (decrease/adjustment).
     */
    public function decrease(float $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'change_amount' => -1 * ($amount ?? $this->faker->randomFloat(2, 100, 5000)),
        ]);
    }
}
