<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contract\Entities\RenewalActivity;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Contract\Entities\RenewalActivity>
 */
class RenewalActivityFactory extends Factory
{
    protected $model = RenewalActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['call', 'email', 'meeting', 'note', 'status_change', 'value_change']);

        return [
            'renewal_id' => Renewal::factory(),
            'type' => $type,
            'subject' => $this->getSubjectForType($type),
            'description' => $this->faker->paragraph(),
            'user_id' => User::factory(),
            'metadata' => $this->getMetadataForType($type),
        ];
    }

    /**
     * Get subject based on activity type.
     */
    private function getSubjectForType(string $type): string
    {
        return match ($type) {
            'call' => 'Renewal discussion call',
            'email' => 'Renewal proposal sent',
            'meeting' => 'Renewal review meeting',
            'note' => 'Internal note',
            'status_change' => 'Status updated',
            'value_change' => 'Renewal value adjusted',
            default => $this->faker->sentence(4),
        };
    }

    /**
     * Get metadata based on activity type.
     */
    private function getMetadataForType(string $type): array
    {
        return match ($type) {
            'call' => ['duration' => $this->faker->numberBetween(5, 60), 'outcome' => 'Positive'],
            'email' => ['sent_to' => $this->faker->safeEmail()],
            'meeting' => ['duration' => $this->faker->numberBetween(30, 120), 'attendees' => [$this->faker->name()]],
            'status_change' => ['old_status' => 'pending', 'new_status' => 'in_progress'],
            'value_change' => ['old_value' => 10000, 'new_value' => 12000],
            default => [],
        };
    }

    /**
     * Call activity.
     */
    public function call(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'call',
            'subject' => 'Renewal discussion call',
            'metadata' => [
                'duration' => $this->faker->numberBetween(5, 60),
                'outcome' => $this->faker->randomElement(['Positive', 'Neutral', 'Follow-up needed']),
            ],
        ]);
    }

    /**
     * Email activity.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'email',
            'subject' => 'Renewal proposal sent',
            'metadata' => [
                'sent_to' => $this->faker->safeEmail(),
                'template' => 'renewal_proposal',
            ],
        ]);
    }

    /**
     * Meeting activity.
     */
    public function meeting(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'meeting',
            'subject' => 'Renewal review meeting',
            'metadata' => [
                'duration' => $this->faker->numberBetween(30, 120),
                'attendees' => [$this->faker->name(), $this->faker->name()],
            ],
        ]);
    }

    /**
     * Note activity.
     */
    public function note(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'note',
            'subject' => 'Internal note',
            'metadata' => [],
        ]);
    }

    /**
     * Status change activity.
     */
    public function statusChange(string $from = 'pending', string $to = 'in_progress'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'status_change',
            'subject' => "Status changed from {$from} to {$to}",
            'metadata' => [
                'old_status' => $from,
                'new_status' => $to,
            ],
        ]);
    }
}
