<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Approval\Entities\ApprovalDelegation;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Approval\Entities\ApprovalDelegation>
 */
class ApprovalDelegationFactory extends Factory
{
    protected $model = ApprovalDelegation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delegator_id' => User::factory(),
            'delegate_id' => User::factory(),
            'start_date' => now(),
            'end_date' => now()->addDays($this->faker->numberBetween(7, 30)),
            'reason' => $this->faker->randomElement([
                'Out of office - vacation',
                'Business travel',
                'Medical leave',
                'Temporary assignment',
            ]),
            'is_active' => true,
            'notify_delegator' => true,
            'scope' => [],
        ];
    }

    /**
     * Active delegation.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(14),
        ]);
    }

    /**
     * Expired delegation.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->subDays(14),
            'end_date' => now()->subDays(1),
        ]);
    }

    /**
     * Inactive delegation.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Future delegation.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(21),
        ]);
    }

    /**
     * With limited scope.
     */
    public function withScope(array $blueprintIds = []): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => [
                'blueprint_ids' => $blueprintIds ?: [1, 2, 3],
            ],
        ]);
    }

    /**
     * Without notification.
     */
    public function silent(): static
    {
        return $this->state(fn (array $attributes) => [
            'notify_delegator' => false,
        ]);
    }
}
