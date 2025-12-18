<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-6 months', '+1 month');
        $endDate = (clone $startDate)->modify('+' . $this->faker->randomElement([6, 12, 24, 36]) . ' months');
        $renewalDate = (clone $endDate)->modify('-30 days');

        return [
            'name' => $this->faker->company() . ' ' . $this->faker->randomElement([
                'Service Agreement',
                'Subscription Contract',
                'Enterprise License',
                'Annual Agreement',
                'Support Contract',
            ]),
            'contract_number' => 'CON-' . date('Y') . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'related_module' => 'accounts',
            'related_id' => $this->faker->numberBetween(1, 100),
            'type' => $this->faker->randomElement(['subscription', 'service', 'license', 'support', 'consulting']),
            'status' => $this->faker->randomElement(['draft', 'active', 'expired', 'terminated']),
            'value' => $this->faker->randomFloat(2, 5000, 500000),
            'currency' => 'USD',
            'billing_frequency' => $this->faker->randomElement(['monthly', 'quarterly', 'annually', 'one_time']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'renewal_date' => $renewalDate,
            'renewal_notice_days' => $this->faker->randomElement([30, 45, 60, 90]),
            'auto_renew' => $this->faker->boolean(60),
            'renewal_status' => $this->faker->optional(0.3)->randomElement(['pending', 'in_progress', 'renewed', 'cancelled']),
            'owner_id' => User::factory(),
            'terms' => $this->faker->optional(0.5)->paragraphs(2, true),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'custom_fields' => [],
        ];
    }

    /**
     * Draft contract.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Active contract.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'start_date' => now()->subMonths(2),
            'end_date' => now()->addMonths(10),
        ]);
    }

    /**
     * Expired contract.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'start_date' => now()->subMonths(14),
            'end_date' => now()->subMonths(2),
        ]);
    }

    /**
     * Expiring soon.
     */
    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'start_date' => now()->subMonths(10),
            'end_date' => now()->addDays($this->faker->numberBetween(7, 30)),
            'renewal_date' => now()->addDays($this->faker->numberBetween(1, 15)),
        ]);
    }

    /**
     * Subscription contract.
     */
    public function subscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'subscription',
            'billing_frequency' => $this->faker->randomElement(['monthly', 'annually']),
            'auto_renew' => true,
        ]);
    }

    /**
     * Service agreement.
     */
    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'service',
            'billing_frequency' => 'monthly',
        ]);
    }

    /**
     * High value contract.
     */
    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => $this->faker->randomFloat(2, 100000, 1000000),
        ]);
    }

    /**
     * With auto renew.
     */
    public function autoRenew(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_renew' => true,
        ]);
    }

    /**
     * For account.
     */
    public function forAccount(int $accountId): static
    {
        return $this->state(fn (array $attributes) => [
            'related_module' => 'accounts',
            'related_id' => $accountId,
        ]);
    }
}
