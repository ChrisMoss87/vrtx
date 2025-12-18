<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 5000, 250000);
        $discountPercent = $this->faker->randomFloat(2, 0, 20);
        $discountAmount = $subtotal * ($discountPercent / 100);
        $taxRate = 8.5;
        $taxAmount = ($subtotal - $discountAmount) * ($taxRate / 100);
        $total = $subtotal - $discountAmount + $taxAmount;

        return [
            'quote_number' => 'QUO-' . $this->faker->unique()->numberBetween(10000, 99999),
            'deal_id' => $this->faker->numberBetween(1, 100),
            'contact_id' => $this->faker->numberBetween(1, 100),
            'company_id' => $this->faker->numberBetween(1, 100),
            'status' => $this->faker->randomElement(Quote::STATUSES),
            'title' => $this->faker->company() . ' - ' . $this->faker->randomElement([
                'Software License',
                'Professional Services',
                'Annual Subscription',
                'Enterprise Package',
            ]),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'discount_type' => 'percent',
            'discount_percent' => $discountPercent,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'currency' => 'USD',
            'valid_until' => $this->faker->dateTimeBetween('now', '+30 days'),
            'terms' => 'Net 30 days. All prices in USD.',
            'notes' => $this->faker->optional(0.5)->paragraph(),
            'internal_notes' => $this->faker->optional(0.3)->sentence(),
            'template_id' => null,
            'version' => 1,
            'view_token' => Str::random(32),
            'accepted_at' => null,
            'accepted_by' => null,
            'viewed_at' => null,
            'sent_at' => null,
            'sent_to_email' => null,
            'created_by' => User::factory(),
            'assigned_to' => User::factory(),
        ];
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Quote::STATUS_DRAFT,
        ]);
    }

    /**
     * Sent status.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Quote::STATUS_SENT,
            'sent_at' => now(),
            'sent_to_email' => $this->faker->email(),
        ]);
    }

    /**
     * Viewed status.
     */
    public function viewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Quote::STATUS_VIEWED,
            'sent_at' => now()->subDays(2),
            'viewed_at' => now()->subDay(),
        ]);
    }

    /**
     * Accepted status.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Quote::STATUS_ACCEPTED,
            'sent_at' => now()->subDays(5),
            'viewed_at' => now()->subDays(3),
            'accepted_at' => now(),
            'accepted_by' => $this->faker->name(),
            'accepted_ip' => $this->faker->ipv4(),
        ]);
    }

    /**
     * Rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Quote::STATUS_REJECTED,
            'sent_at' => now()->subDays(5),
            'rejected_at' => now(),
            'rejected_by' => $this->faker->name(),
            'rejection_reason' => 'Budget constraints',
        ]);
    }

    /**
     * Expired status.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Quote::STATUS_EXPIRED,
            'valid_until' => now()->subDays(7),
        ]);
    }

    /**
     * With line items.
     */
    public function withLineItems(int $count = 3): static
    {
        return $this->has(
            \App\Models\QuoteLineItem::factory()->count($count),
            'lineItems'
        );
    }

    /**
     * High value quote.
     */
    public function highValue(): static
    {
        $subtotal = $this->faker->randomFloat(2, 100000, 500000);
        $discountAmount = $subtotal * 0.1;
        $taxAmount = ($subtotal - $discountAmount) * 0.085;

        return $this->state(fn (array $attributes) => [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'discount_percent' => 10,
            'tax_amount' => $taxAmount,
            'total' => $subtotal - $discountAmount + $taxAmount,
        ]);
    }
}
