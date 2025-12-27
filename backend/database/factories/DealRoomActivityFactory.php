<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\DealRoom\Entities\DealRoomActivity;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\DealRoom\Entities\DealRoomActivity>
 */
class DealRoomActivityFactory extends Factory
{
    protected $model = DealRoomActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => DealRoom::factory(),
            'member_id' => DealRoomMember::factory(),
            'activity_type' => $this->faker->randomElement([
                'room_viewed',
                'document_viewed',
                'document_downloaded',
                'message_sent',
                'action_item_completed',
                'member_joined',
            ]),
            'activity_data' => [],
        ];
    }

    /**
     * Room viewed activity.
     */
    public function roomViewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'room_viewed',
            'activity_data' => [
                'duration_seconds' => $this->faker->numberBetween(60, 600),
            ],
        ]);
    }

    /**
     * Document viewed activity.
     */
    public function documentViewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'document_viewed',
            'activity_data' => [
                'document_id' => $this->faker->numberBetween(1, 100),
                'document_name' => 'Proposal.pdf',
                'time_spent_seconds' => $this->faker->numberBetween(30, 300),
            ],
        ]);
    }

    /**
     * Message sent activity.
     */
    public function messageSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'message_sent',
            'activity_data' => [
                'message_id' => $this->faker->numberBetween(1, 100),
            ],
        ]);
    }

    /**
     * Member joined activity.
     */
    public function memberJoined(): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'member_joined',
            'activity_data' => [
                'role' => 'stakeholder',
            ],
        ]);
    }
}
