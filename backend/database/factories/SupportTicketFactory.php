<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Support\Entities\SupportTicket;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Support\Entities\SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['open', 'pending', 'in_progress', 'resolved', 'closed']);
        $priority = $this->faker->numberBetween(1, 4);
        $channel = $this->faker->randomElement(['portal', 'email', 'phone', 'chat']);

        $createdAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $firstResponseAt = $status !== 'open' ? $this->faker->dateTimeBetween($createdAt, 'now') : null;
        $resolvedAt = in_array($status, ['resolved', 'closed']) ? $this->faker->dateTimeBetween($createdAt, 'now') : null;
        $closedAt = $status === 'closed' ? $this->faker->dateTimeBetween($resolvedAt ?? $createdAt, 'now') : null;

        return [
            'ticket_number' => 'TKT-' . date('Y') . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'subject' => $this->faker->randomElement([
                'Unable to login to my account',
                'Payment not processing',
                'Feature request: Export to PDF',
                'Bug: Dashboard not loading',
                'How do I reset my password?',
                'Integration with Slack not working',
                'Invoice discrepancy',
                'Performance issues with reports',
                'Request for data export',
                'API authentication error',
            ]),
            'description' => $this->faker->paragraphs(2, true),
            'status' => $status,
            'priority' => $priority,
            'category_id' => TicketCategory::factory(),
            'submitter_id' => User::factory(),
            'portal_user_id' => null,
            'contact_id' => $this->faker->optional(0.5)->numberBetween(1, 100),
            'account_id' => $this->faker->optional(0.5)->numberBetween(1, 50),
            'assigned_to' => $status !== 'open' ? User::factory() : null,
            'team_id' => SupportTeam::factory(),
            'channel' => $channel,
            'tags' => $this->faker->randomElements(['billing', 'technical', 'feature-request', 'bug', 'urgent', 'api', 'integration'], $this->faker->numberBetween(0, 3)),
            'first_response_at' => $firstResponseAt,
            'resolved_at' => $resolvedAt,
            'closed_at' => $closedAt,
            'sla_response_due_at' => $this->faker->dateTimeBetween($createdAt, '+24 hours'),
            'sla_resolution_due_at' => $this->faker->dateTimeBetween($createdAt, '+72 hours'),
            'sla_response_breached' => $this->faker->boolean(15),
            'sla_resolution_breached' => $this->faker->boolean(10),
            'satisfaction_rating' => in_array($status, ['resolved', 'closed']) ? $this->faker->optional(0.6)->numberBetween(1, 5) : null,
            'satisfaction_feedback' => in_array($status, ['resolved', 'closed']) ? $this->faker->optional(0.3)->sentence() : null,
            'custom_fields' => [],
            'created_at' => $createdAt,
        ];
    }

    /**
     * Open ticket.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'assigned_to' => null,
            'first_response_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Pending ticket.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'first_response_at' => now()->subHours($this->faker->numberBetween(1, 24)),
        ]);
    }

    /**
     * In progress ticket.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'assigned_to' => User::factory(),
            'first_response_at' => now()->subHours($this->faker->numberBetween(1, 24)),
        ]);
    }

    /**
     * Resolved ticket.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'assigned_to' => User::factory(),
            'first_response_at' => now()->subDays(2),
            'resolved_at' => now()->subDay(),
        ]);
    }

    /**
     * Closed ticket.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'assigned_to' => User::factory(),
            'first_response_at' => now()->subDays(3),
            'resolved_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
        ]);
    }

    /**
     * Unassigned ticket.
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => null,
        ]);
    }

    /**
     * Urgent priority.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 4,
            'tags' => array_merge($attributes['tags'] ?? [], ['urgent']),
        ]);
    }

    /**
     * High priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 3,
        ]);
    }

    /**
     * SLA breached.
     */
    public function slaBreached(): static
    {
        return $this->state(fn (array $attributes) => [
            'sla_response_breached' => true,
            'sla_resolution_breached' => $this->faker->boolean(50),
        ]);
    }

    /**
     * From email channel.
     */
    public function fromEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'email',
        ]);
    }

    /**
     * From portal channel.
     */
    public function fromPortal(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'portal',
        ]);
    }

    /**
     * With satisfaction rating.
     */
    public function withRating(int $rating = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'satisfaction_rating' => $rating ?? $this->faker->numberBetween(1, 5),
            'satisfaction_feedback' => $this->faker->sentence(),
        ]);
    }
}
