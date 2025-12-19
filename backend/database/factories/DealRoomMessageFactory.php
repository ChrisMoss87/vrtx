<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DealRoom;
use App\Models\DealRoomMember;
use App\Models\DealRoomMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DealRoomMessage>
 */
class DealRoomMessageFactory extends Factory
{
    protected $model = DealRoomMessage::class;

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
            'message' => $this->faker->randomElement([
                'Thanks for sending over the proposal. We\'ll review it with the team.',
                'Could you clarify the pricing for the enterprise tier?',
                'I\'ve uploaded the updated contract with the requested changes.',
                'Great meeting today! Looking forward to next steps.',
                'Can we schedule a technical deep-dive for next week?',
                'The security team has approved the integration approach.',
                'We need to discuss the implementation timeline.',
            ]),
            'attachments' => [],
            'is_internal' => $this->faker->boolean(20),
        ];
    }

    /**
     * Public message (visible to all).
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => false,
        ]);
    }

    /**
     * Internal message (only visible to internal team).
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
            'message' => $this->faker->randomElement([
                'Internal note: Budget approved by VP.',
                'Heads up - they\'re also talking to Competitor X.',
                'Let\'s discount 15% to close this quarter.',
                'Need to loop in solutions engineering.',
            ]),
        ]);
    }

    /**
     * With attachment.
     */
    public function withAttachment(): static
    {
        return $this->state(fn (array $attributes) => [
            'attachments' => [
                [
                    'name' => $this->faker->words(2, true) . '.pdf',
                    'url' => '/attachments/' . $this->faker->uuid() . '.pdf',
                    'size' => $this->faker->numberBetween(10000, 1000000),
                ],
            ],
        ]);
    }
}
