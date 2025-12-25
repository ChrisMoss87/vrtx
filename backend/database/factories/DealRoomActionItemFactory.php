<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DealRoomActionItem>
 */
class DealRoomActionItemFactory extends Factory
{
    protected $model = DealRoomActionItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => DealRoom::factory(),
            'title' => $this->faker->randomElement([
                'Review proposal document',
                'Schedule demo with technical team',
                'Provide pricing breakdown',
                'Sign NDA',
                'Complete security questionnaire',
                'Review contract terms',
                'Approve budget allocation',
                'Technical integration review',
                'Send reference customer list',
                'Finalize implementation timeline',
            ]),
            'description' => $this->faker->optional(0.7)->sentence(),
            'assigned_to' => null,
            'assigned_party' => $this->faker->randomElement([
                DealRoomActionItem::PARTY_SELLER,
                DealRoomActionItem::PARTY_BUYER,
                DealRoomActionItem::PARTY_BOTH,
            ]),
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'status' => $this->faker->randomElement([
                DealRoomActionItem::STATUS_PENDING,
                DealRoomActionItem::STATUS_IN_PROGRESS,
                DealRoomActionItem::STATUS_COMPLETED,
            ]),
            'display_order' => $this->faker->numberBetween(1, 10),
            'completed_at' => null,
            'completed_by' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DealRoomActionItem::STATUS_PENDING,
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    /**
     * In progress status.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DealRoomActionItem::STATUS_IN_PROGRESS,
            'completed_at' => null,
        ]);
    }

    /**
     * Completed status.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DealRoomActionItem::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => DealRoomMember::factory(),
        ]);
    }

    /**
     * Overdue item.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DealRoomActionItem::STATUS_PENDING,
            'due_date' => $this->faker->dateTimeBetween('-14 days', '-1 day'),
        ]);
    }

    /**
     * Seller party.
     */
    public function sellerParty(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_party' => DealRoomActionItem::PARTY_SELLER,
        ]);
    }

    /**
     * Buyer party.
     */
    public function buyerParty(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_party' => DealRoomActionItem::PARTY_BUYER,
        ]);
    }
}
