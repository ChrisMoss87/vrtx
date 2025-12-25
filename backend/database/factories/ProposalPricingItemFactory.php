<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Proposal;
use App\Infrastructure\Persistence\Eloquent\Models\ProposalPricingItem;
use App\Infrastructure\Persistence\Eloquent\Models\ProposalSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\ProposalPricingItem>
 */
class ProposalPricingItemFactory extends Factory
{
    protected $model = ProposalPricingItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $this->faker->randomFloat(2, 1000, 50000);
        $lineTotal = $quantity * $unitPrice;

        return [
            'proposal_id' => Proposal::factory(),
            'section_id' => null,
            'product_id' => null,
            'name' => $this->faker->randomElement([
                'Discovery & Strategy Phase',
                'Design & Development',
                'Implementation Services',
                'Training & Documentation',
                'Ongoing Support (Annual)',
                'Premium Support Package',
                'Custom Integration',
                'Data Migration',
            ]),
            'description' => $this->faker->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percent' => $this->faker->randomFloat(2, 0, 15),
            'line_total' => $lineTotal,
            'is_optional' => $this->faker->boolean(30),
            'is_selected' => true,
            'display_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Optional item.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_optional' => true,
            'is_selected' => false,
        ]);
    }

    /**
     * Selected item.
     */
    public function selected(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_selected' => true,
        ]);
    }

    /**
     * High value item.
     */
    public function highValue(): static
    {
        $unitPrice = $this->faker->randomFloat(2, 50000, 200000);

        return $this->state(fn (array $attributes) => [
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice,
        ]);
    }

    /**
     * Recurring item.
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Annual Subscription',
            'description' => 'Recurring annual subscription for platform access',
        ]);
    }
}
