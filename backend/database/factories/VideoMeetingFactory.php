<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\VideoMeeting;
use App\Models\VideoProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VideoMeeting>
 */
class VideoMeetingFactory extends Factory
{
    protected $model = VideoMeeting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduledAt = $this->faker->dateTimeBetween('+1 day', '+14 days');

        return [
            'provider_id' => VideoProvider::factory(),
            'external_meeting_id' => $this->faker->regexify('[0-9]{9,11}'),
            'host_id' => User::factory(),
            'title' => $this->faker->randomElement([
                'Team Sync',
                'Product Demo',
                'Client Meeting',
                'Strategy Review',
                'Weekly Standup',
            ]),
            'description' => $this->faker->optional(0.5)->paragraph(),
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'started_at' => null,
            'ended_at' => null,
            'duration_minutes' => $this->faker->randomElement([30, 45, 60]),
            'actual_duration_seconds' => null,
            'join_url' => 'https://zoom.us/j/' . $this->faker->regexify('[0-9]{10}'),
            'host_url' => 'https://zoom.us/s/' . $this->faker->regexify('[0-9]{10}'),
            'password' => $this->faker->regexify('[A-Za-z0-9]{6}'),
            'waiting_room_enabled' => $this->faker->boolean(70),
            'recording_enabled' => $this->faker->boolean(50),
            'recording_auto_start' => false,
            'recording_url' => null,
            'recording_status' => null,
            'meeting_type' => 'instant',
            'recurrence_type' => null,
            'recurrence_settings' => null,
            'deal_id' => null,
            'deal_module' => null,
            'custom_fields' => [],
            'metadata' => [],
        ];
    }

    /**
     * Scheduled status.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'started_at' => null,
            'ended_at' => null,
        ]);
    }

    /**
     * Started status.
     */
    public function started(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'started',
            'started_at' => now(),
            'ended_at' => null,
        ]);
    }

    /**
     * Ended status.
     */
    public function ended(): static
    {
        $startedAt = $this->faker->dateTimeBetween('-2 hours', '-30 minutes');
        $duration = $this->faker->numberBetween(1200, 3600);

        return $this->state(fn (array $attributes) => [
            'status' => 'ended',
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->modify("+{$duration} seconds"),
            'actual_duration_seconds' => $duration,
        ]);
    }

    /**
     * Cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
        ]);
    }

    /**
     * Upcoming meeting.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_at' => $this->faker->dateTimeBetween('+1 hour', '+7 days'),
        ]);
    }

    /**
     * With recording.
     */
    public function withRecording(): static
    {
        return $this->state(fn (array $attributes) => [
            'recording_enabled' => true,
            'recording_status' => 'completed',
            'recording_url' => 'https://zoom.us/rec/share/' . $this->faker->regexify('[A-Za-z0-9]{32}'),
        ]);
    }

    /**
     * Linked to deal.
     */
    public function linkedToDeal(int $dealId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'deal_id' => $dealId ?? $this->faker->numberBetween(1, 100),
            'deal_module' => 'deals',
        ]);
    }

    /**
     * Recurring meeting.
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'meeting_type' => 'recurring',
            'recurrence_type' => 'weekly',
            'recurrence_settings' => [
                'interval' => 1,
                'days' => ['monday', 'wednesday'],
                'end_after' => 10,
            ],
        ]);
    }
}
