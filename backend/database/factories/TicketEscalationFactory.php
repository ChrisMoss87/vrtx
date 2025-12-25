<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketEscalation>
 */
class TicketEscalationFactory extends Factory
{
    protected $model = TicketEscalation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['response_sla', 'resolution_sla', 'manual']);
        $isAcknowledged = $this->faker->boolean(40);

        return [
            'ticket_id' => SupportTicket::factory(),
            'type' => $type,
            'level' => $this->faker->randomElement(['first', 'second', 'third']),
            'escalated_to' => User::factory(),
            'reason' => $this->getReasonForType($type),
            'escalated_by' => $type === 'manual' ? User::factory() : null,
            'acknowledged_at' => $isAcknowledged ? $this->faker->dateTimeBetween('-1 day', 'now') : null,
            'acknowledged_by' => $isAcknowledged ? User::factory() : null,
        ];
    }

    /**
     * Get reason based on escalation type.
     */
    private function getReasonForType(string $type): string
    {
        return match ($type) {
            'response_sla' => 'Response SLA deadline exceeded',
            'resolution_sla' => 'Resolution SLA deadline exceeded',
            'manual' => $this->faker->randomElement([
                'Customer requested manager',
                'Complex technical issue requiring senior support',
                'Repeated issue not resolved',
                'VIP customer escalation',
            ]),
        };
    }

    /**
     * Response SLA escalation.
     */
    public function responseSla(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'response_sla',
            'reason' => 'Response SLA deadline exceeded',
            'escalated_by' => null,
        ]);
    }

    /**
     * Resolution SLA escalation.
     */
    public function resolutionSla(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'resolution_sla',
            'reason' => 'Resolution SLA deadline exceeded',
            'escalated_by' => null,
        ]);
    }

    /**
     * Manual escalation.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'manual',
            'reason' => 'Customer requested manager',
            'escalated_by' => User::factory(),
        ]);
    }

    /**
     * First level escalation.
     */
    public function firstLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'first',
        ]);
    }

    /**
     * Second level escalation.
     */
    public function secondLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'second',
        ]);
    }

    /**
     * Third level escalation.
     */
    public function thirdLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'third',
        ]);
    }

    /**
     * Acknowledged escalation.
     */
    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledged_at' => now(),
            'acknowledged_by' => User::factory(),
        ]);
    }

    /**
     * Pending (not acknowledged) escalation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledged_at' => null,
            'acknowledged_by' => null,
        ]);
    }
}
