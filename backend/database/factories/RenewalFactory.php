<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Renewal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Renewal>
 */
class RenewalFactory extends Factory
{
    protected $model = Renewal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalValue = $this->faker->randomFloat(2, 5000, 100000);
        $status = $this->faker->randomElement(['pending', 'in_progress', 'won', 'lost']);
        $renewalValue = $status === 'lost' ? 0 : $originalValue * $this->faker->randomFloat(2, 0.9, 1.2);
        $upsellValue = $this->faker->boolean(40) ? $this->faker->randomFloat(2, 500, $originalValue * 0.3) : 0;

        return [
            'contract_id' => Contract::factory(),
            'status' => $status,
            'original_value' => $originalValue,
            'renewal_value' => $renewalValue,
            'upsell_value' => $upsellValue,
            'renewal_type' => $this->faker->randomElement(['standard', 'expansion', 'reduction', 'early']),
            'due_date' => $this->faker->dateTimeBetween('-30 days', '+60 days'),
            'closed_date' => in_array($status, ['won', 'lost']) ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'owner_id' => User::factory(),
            'new_contract_id' => $status === 'won' ? Contract::factory() : null,
            'loss_reason' => $status === 'lost' ? $this->faker->randomElement([
                'Price too high',
                'Switched to competitor',
                'Budget cut',
                'Company closed',
                'No longer needed',
            ]) : null,
            'notes' => $this->faker->optional(0.4)->sentence(),
        ];
    }

    /**
     * Pending renewal.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'closed_date' => null,
            'new_contract_id' => null,
            'loss_reason' => null,
        ]);
    }

    /**
     * In progress renewal.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'closed_date' => null,
            'new_contract_id' => null,
            'loss_reason' => null,
        ]);
    }

    /**
     * Won renewal.
     */
    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'won',
            'closed_date' => $this->faker->dateTimeBetween('-14 days', 'now'),
            'new_contract_id' => Contract::factory(),
            'loss_reason' => null,
        ]);
    }

    /**
     * Lost renewal.
     */
    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'lost',
            'renewal_value' => 0,
            'upsell_value' => 0,
            'closed_date' => $this->faker->dateTimeBetween('-14 days', 'now'),
            'new_contract_id' => null,
            'loss_reason' => $this->faker->randomElement([
                'Price too high',
                'Switched to competitor',
                'Budget cut',
            ]),
        ]);
    }

    /**
     * Upcoming renewal.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'due_date' => $this->faker->dateTimeBetween('+7 days', '+30 days'),
        ]);
    }

    /**
     * Overdue renewal.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'due_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Expansion renewal (growth).
     */
    public function expansion(): static
    {
        return $this->state(function (array $attributes) {
            $originalValue = $attributes['original_value'] ?? 10000;
            return [
                'renewal_type' => 'expansion',
                'renewal_value' => $originalValue * $this->faker->randomFloat(2, 1.1, 1.5),
                'upsell_value' => $originalValue * $this->faker->randomFloat(2, 0.1, 0.3),
            ];
        });
    }

    /**
     * High value renewal.
     */
    public function highValue(): static
    {
        $originalValue = $this->faker->randomFloat(2, 100000, 500000);
        return $this->state(fn (array $attributes) => [
            'original_value' => $originalValue,
            'renewal_value' => $originalValue * $this->faker->randomFloat(2, 0.95, 1.15),
        ]);
    }
}
