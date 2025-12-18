<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketActivity>
 */
class TicketActivityFactory extends Factory
{
    protected $model = TicketActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $action = $this->faker->randomElement([
            'created', 'assigned', 'reassigned', 'status_changed', 'priority_changed',
            'replied', 'internal_note', 'escalated', 'resolved', 'closed', 'reopened',
            'merged', 'tagged', 'category_changed',
        ]);

        return [
            'ticket_id' => SupportTicket::factory(),
            'action' => $action,
            'changes' => $this->getChangesForAction($action),
            'user_id' => User::factory(),
            'portal_user_id' => null,
            'note' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    /**
     * Get changes array based on action type.
     */
    private function getChangesForAction(string $action): array
    {
        return match ($action) {
            'status_changed' => [
                'field' => 'status',
                'old' => $this->faker->randomElement(['open', 'pending']),
                'new' => $this->faker->randomElement(['in_progress', 'resolved']),
            ],
            'priority_changed' => [
                'field' => 'priority',
                'old' => $this->faker->numberBetween(1, 3),
                'new' => $this->faker->numberBetween(2, 4),
            ],
            'assigned', 'reassigned' => [
                'field' => 'assigned_to',
                'old' => $action === 'reassigned' ? $this->faker->numberBetween(1, 10) : null,
                'new' => $this->faker->numberBetween(1, 10),
            ],
            'category_changed' => [
                'field' => 'category_id',
                'old' => $this->faker->numberBetween(1, 5),
                'new' => $this->faker->numberBetween(1, 5),
            ],
            'tagged' => [
                'field' => 'tags',
                'added' => ['urgent'],
                'removed' => [],
            ],
            default => [],
        };
    }

    /**
     * Created activity.
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'created',
            'changes' => [],
        ]);
    }

    /**
     * Assigned activity.
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'assigned',
            'changes' => [
                'field' => 'assigned_to',
                'old' => null,
                'new' => $this->faker->numberBetween(1, 10),
            ],
        ]);
    }

    /**
     * Status changed activity.
     */
    public function statusChanged(string $from = 'open', string $to = 'in_progress'): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'status_changed',
            'changes' => [
                'field' => 'status',
                'old' => $from,
                'new' => $to,
            ],
        ]);
    }

    /**
     * Escalated activity.
     */
    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'escalated',
            'changes' => [
                'escalation_level' => 'first',
                'reason' => 'SLA breach',
            ],
            'note' => 'Ticket escalated due to SLA breach',
        ]);
    }

    /**
     * Resolved activity.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'resolved',
            'changes' => [
                'field' => 'status',
                'old' => 'in_progress',
                'new' => 'resolved',
            ],
        ]);
    }

    /**
     * Activity by customer.
     */
    public function byCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'portal_user_id' => $this->faker->numberBetween(1, 100),
        ]);
    }
}
