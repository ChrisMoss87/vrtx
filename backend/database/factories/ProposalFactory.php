<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Proposal;
use App\Infrastructure\Persistence\Eloquent\Models\ProposalTemplate;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Infrastructure\Persistence\Eloquent\Models\Proposal>
 */
class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'name' => $this->faker->company() . ' - ' . $this->faker->randomElement([
                'Digital Transformation Proposal',
                'Enterprise Solution Proposal',
                'Partnership Agreement',
                'Implementation Project',
                'Service Agreement',
            ]),
            'proposal_number' => 'PROP-' . $this->faker->unique()->numberBetween(10000, 99999),
            'template_id' => null,
            'deal_id' => $this->faker->numberBetween(1, 100),
            'contact_id' => $this->faker->numberBetween(1, 100),
            'company_id' => $this->faker->numberBetween(1, 100),
            'status' => $this->faker->randomElement(Proposal::STATUSES),
            'cover_page' => [
                'title' => 'Business Proposal',
                'subtitle' => 'Prepared exclusively for ' . $this->faker->company(),
                'image_url' => null,
            ],
            'styling' => [
                'primary_color' => '#2563eb',
                'secondary_color' => '#64748b',
                'font_family' => 'Inter',
            ],
            'total_value' => $this->faker->randomFloat(2, 25000, 500000),
            'currency' => 'USD',
            'valid_until' => $this->faker->dateTimeBetween('now', '+45 days'),
            'sent_at' => null,
            'sent_to_email' => null,
            'first_viewed_at' => null,
            'last_viewed_at' => null,
            'view_count' => 0,
            'total_time_spent' => 0,
            'accepted_at' => null,
            'accepted_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'created_by' => User::factory(),
            'assigned_to' => User::factory(),
            'version' => 1,
        ];
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Proposal::STATUS_DRAFT,
        ]);
    }

    /**
     * Sent status.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Proposal::STATUS_SENT,
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
            'status' => Proposal::STATUS_VIEWED,
            'sent_at' => now()->subDays(3),
            'first_viewed_at' => now()->subDays(2),
            'last_viewed_at' => now()->subHours(4),
            'view_count' => $this->faker->numberBetween(2, 10),
            'total_time_spent' => $this->faker->numberBetween(120, 900),
        ]);
    }

    /**
     * Accepted status.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Proposal::STATUS_ACCEPTED,
            'sent_at' => now()->subDays(7),
            'first_viewed_at' => now()->subDays(5),
            'last_viewed_at' => now()->subDays(1),
            'view_count' => $this->faker->numberBetween(5, 15),
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
            'status' => Proposal::STATUS_REJECTED,
            'sent_at' => now()->subDays(7),
            'rejected_at' => now(),
            'rejected_by' => $this->faker->name(),
            'rejection_reason' => $this->faker->randomElement([
                'Budget constraints',
                'Chose a competitor',
                'Project postponed',
                'Scope mismatch',
            ]),
        ]);
    }

    /**
     * With sections.
     */
    public function withSections(int $count = 4): static
    {
        return $this->has(
            \App\Infrastructure\Persistence\Eloquent\Models\ProposalSection::factory()->count($count),
            'sections'
        );
    }

    /**
     * With pricing items.
     */
    public function withPricingItems(int $count = 3): static
    {
        return $this->has(
            \App\Infrastructure\Persistence\Eloquent\Models\ProposalPricingItem::factory()->count($count),
            'pricingItems'
        );
    }
}
