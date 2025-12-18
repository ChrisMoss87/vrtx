<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MeetingType;
use App\Models\ScheduledMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduledMeeting>
 */
class ScheduledMeetingFactory extends Factory
{
    protected $model = ScheduledMeeting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('+1 day', '+14 days');
        $duration = $this->faker->randomElement([30, 45, 60]);

        return [
            'meeting_type_id' => MeetingType::factory(),
            'host_user_id' => User::factory(),
            'contact_id' => null,
            'attendee_name' => $this->faker->name(),
            'attendee_email' => $this->faker->email(),
            'attendee_phone' => $this->faker->optional(0.5)->phoneNumber(),
            'start_time' => $startTime,
            'end_time' => (clone $startTime)->modify("+{$duration} minutes"),
            'timezone' => $this->faker->randomElement(['America/New_York', 'America/Los_Angeles', 'Europe/London']),
            'location' => $this->faker->optional(0.7)->url(),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'answers' => [
                'What would you like to discuss?' => $this->faker->sentence(),
            ],
            'status' => ScheduledMeeting::STATUS_SCHEDULED,
            'calendar_event_id' => null,
            'manage_token' => Str::random(64),
            'reminder_sent' => false,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }

    /**
     * Scheduled status.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduledMeeting::STATUS_SCHEDULED,
            'cancelled_at' => null,
        ]);
    }

    /**
     * Completed status.
     */
    public function completed(): static
    {
        $startTime = $this->faker->dateTimeBetween('-7 days', '-1 day');

        return $this->state(fn (array $attributes) => [
            'status' => ScheduledMeeting::STATUS_COMPLETED,
            'start_time' => $startTime,
            'end_time' => (clone $startTime)->modify('+30 minutes'),
        ]);
    }

    /**
     * Cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduledMeeting::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $this->faker->randomElement([
                'Schedule conflict',
                'Emergency came up',
                'Need to reschedule',
            ]),
        ]);
    }

    /**
     * No-show status.
     */
    public function noShow(): static
    {
        $startTime = $this->faker->dateTimeBetween('-3 days', '-1 day');

        return $this->state(fn (array $attributes) => [
            'status' => ScheduledMeeting::STATUS_NO_SHOW,
            'start_time' => $startTime,
            'end_time' => (clone $startTime)->modify('+30 minutes'),
        ]);
    }

    /**
     * Upcoming meeting.
     */
    public function upcoming(): static
    {
        $startTime = $this->faker->dateTimeBetween('+1 hour', '+7 days');

        return $this->state(fn (array $attributes) => [
            'status' => ScheduledMeeting::STATUS_SCHEDULED,
            'start_time' => $startTime,
            'end_time' => (clone $startTime)->modify('+30 minutes'),
        ]);
    }

    /**
     * Today's meeting.
     */
    public function today(): static
    {
        $hour = now()->addHours($this->faker->numberBetween(1, 8));
        $startTime = $hour->setMinutes($this->faker->randomElement([0, 30]));

        return $this->state(fn (array $attributes) => [
            'status' => ScheduledMeeting::STATUS_SCHEDULED,
            'start_time' => $startTime,
            'end_time' => (clone $startTime)->modify('+30 minutes'),
        ]);
    }

    /**
     * With reminder sent.
     */
    public function reminderSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_sent' => true,
        ]);
    }

    /**
     * Linked to contact.
     */
    public function linkedToContact(int $contactId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contactId ?? $this->faker->numberBetween(1, 100),
        ]);
    }
}
