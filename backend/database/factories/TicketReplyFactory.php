<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketReply>
 */
class TicketReplyFactory extends Factory
{
    protected $model = TicketReply::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => SupportTicket::factory(),
            'content' => $this->faker->paragraphs($this->faker->numberBetween(1, 3), true),
            'user_id' => User::factory(),
            'portal_user_id' => null,
            'is_internal' => false,
            'is_system' => false,
            'attachments' => [],
        ];
    }

    /**
     * Reply from agent.
     */
    public function fromAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'portal_user_id' => null,
            'is_internal' => false,
        ]);
    }

    /**
     * Reply from customer (portal user).
     */
    public function fromCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'portal_user_id' => $this->faker->numberBetween(1, 100),
            'is_internal' => false,
        ]);
    }

    /**
     * Internal note.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'portal_user_id' => null,
            'is_internal' => true,
            'content' => 'Internal note: ' . $this->faker->sentence(),
        ]);
    }

    /**
     * System generated reply.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'portal_user_id' => null,
            'is_system' => true,
            'content' => $this->faker->randomElement([
                'This ticket has been automatically assigned.',
                'SLA response time is approaching.',
                'This ticket has been escalated.',
                'Ticket status has been updated.',
            ]),
        ]);
    }

    /**
     * With attachments.
     */
    public function withAttachments(): static
    {
        return $this->state(fn (array $attributes) => [
            'attachments' => [
                [
                    'name' => 'screenshot.png',
                    'path' => 'tickets/attachments/' . $this->faker->uuid() . '.png',
                    'size' => $this->faker->numberBetween(10000, 500000),
                    'mime_type' => 'image/png',
                ],
                [
                    'name' => 'log_file.txt',
                    'path' => 'tickets/attachments/' . $this->faker->uuid() . '.txt',
                    'size' => $this->faker->numberBetween(1000, 50000),
                    'mime_type' => 'text/plain',
                ],
            ],
        ]);
    }
}
