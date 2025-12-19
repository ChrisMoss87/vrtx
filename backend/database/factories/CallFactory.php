<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Call;
use App\Models\CallProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Call>
 */
class CallFactory extends Factory
{
    protected $model = Call::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $direction = $this->faker->randomElement(['inbound', 'outbound']);
        $status = $this->faker->randomElement(['completed', 'no_answer', 'busy', 'failed']);
        $startedAt = $this->faker->dateTimeBetween('-7 days', 'now');
        $duration = $status === 'completed' ? $this->faker->numberBetween(30, 1800) : 0;

        return [
            'provider_id' => CallProvider::factory(),
            'external_call_id' => 'CALL_' . $this->faker->uuid(),
            'direction' => $direction,
            'status' => $status,
            'from_number' => '+1' . $this->faker->numerify('##########'),
            'to_number' => '+1' . $this->faker->numerify('##########'),
            'user_id' => User::factory(),
            'contact_id' => null,
            'contact_module' => null,
            'duration_seconds' => $duration,
            'ring_duration_seconds' => $this->faker->numberBetween(3, 30),
            'started_at' => $startedAt,
            'answered_at' => $status === 'completed' ? $startedAt : null,
            'ended_at' => $status === 'completed'
                ? (clone $startedAt)->modify("+{$duration} seconds")
                : $startedAt,
            'recording_url' => $this->faker->optional(0.5)->url(),
            'recording_sid' => null,
            'recording_duration_seconds' => null,
            'recording_status' => null,
            'notes' => $this->faker->optional(0.5)->sentence(),
            'outcome' => $status === 'completed'
                ? $this->faker->randomElement(['interested', 'not_interested', 'callback', 'voicemail'])
                : null,
            'custom_fields' => [],
            'metadata' => [],
        ];
    }

    /**
     * Inbound call.
     */
    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'inbound',
        ]);
    }

    /**
     * Outbound call.
     */
    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'outbound',
        ]);
    }

    /**
     * Completed call.
     */
    public function completed(): static
    {
        $duration = $this->faker->numberBetween(60, 1800);
        $startedAt = $this->faker->dateTimeBetween('-7 days', '-1 hour');

        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'duration_seconds' => $duration,
            'started_at' => $startedAt,
            'answered_at' => $startedAt,
            'ended_at' => (clone $startedAt)->modify("+{$duration} seconds"),
        ]);
    }

    /**
     * Missed call.
     */
    public function missed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'no_answer',
            'duration_seconds' => 0,
            'answered_at' => null,
        ]);
    }

    /**
     * With recording.
     */
    public function withRecording(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'recording_url' => 'https://recordings.example.com/' . $this->faker->uuid() . '.mp3',
            'recording_sid' => 'RE' . $this->faker->regexify('[a-f0-9]{32}'),
            'recording_duration_seconds' => $attributes['duration_seconds'] ?? 300,
            'recording_status' => 'completed',
        ]);
    }

    /**
     * Linked to a contact.
     */
    public function linkedToContact(int $contactId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contactId ?? $this->faker->numberBetween(1, 100),
            'contact_module' => 'contacts',
        ]);
    }

    /**
     * Long call.
     */
    public function long(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'duration_seconds' => $this->faker->numberBetween(1800, 7200),
        ]);
    }

    /**
     * Today's call.
     */
    public function today(): static
    {
        $startedAt = $this->faker->dateTimeBetween('today', 'now');

        return $this->state(fn (array $attributes) => [
            'started_at' => $startedAt,
            'ended_at' => $startedAt,
        ]);
    }
}
