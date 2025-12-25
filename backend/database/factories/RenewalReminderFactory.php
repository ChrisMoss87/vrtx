<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RenewalReminder>
 */
class RenewalReminderFactory extends Factory
{
    protected $model = RenewalReminder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isSent = $this->faker->boolean(30);
        $scheduledAt = $this->faker->dateTimeBetween('-7 days', '+30 days');

        return [
            'contract_id' => Contract::factory(),
            'days_before' => $this->faker->randomElement([7, 14, 30, 45, 60, 90]),
            'reminder_type' => $this->faker->randomElement(['email', 'notification', 'task']),
            'recipients' => [
                $this->faker->safeEmail(),
                $this->faker->optional(0.5)->safeEmail(),
            ],
            'template' => $this->faker->randomElement(['renewal_reminder_30', 'renewal_reminder_14', 'renewal_reminder_7']),
            'is_sent' => $isSent,
            'sent_at' => $isSent ? $this->faker->dateTimeBetween('-7 days', 'now') : null,
            'scheduled_at' => $scheduledAt,
        ];
    }

    /**
     * Pending (not sent) reminder.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sent' => false,
            'sent_at' => null,
        ]);
    }

    /**
     * Sent reminder.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sent' => true,
            'sent_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Due reminder.
     */
    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sent' => false,
            'scheduled_at' => $this->faker->dateTimeBetween('-2 days', 'now'),
        ]);
    }

    /**
     * Future reminder.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sent' => false,
            'scheduled_at' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }

    /**
     * Email reminder.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_type' => 'email',
        ]);
    }

    /**
     * 30 days before reminder.
     */
    public function thirtyDays(): static
    {
        return $this->state(fn (array $attributes) => [
            'days_before' => 30,
            'template' => 'renewal_reminder_30',
        ]);
    }

    /**
     * 7 days before reminder.
     */
    public function sevenDays(): static
    {
        return $this->state(fn (array $attributes) => [
            'days_before' => 7,
            'template' => 'renewal_reminder_7',
        ]);
    }
}
