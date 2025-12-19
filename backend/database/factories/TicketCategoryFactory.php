<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketCategory>
 */
class TicketCategoryFactory extends Factory
{
    protected $model = TicketCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'General Inquiry',
            'Technical Issue',
            'Billing Question',
            'Feature Request',
            'Bug Report',
            'Account Access',
            'Integration Help',
            'Data Export',
            'Security Concern',
            'Training Request',
        ]);

        return [
            'name' => $name . ' ' . $this->faker->unique()->numberBetween(1, 1000),
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 1000),
            'description' => $this->faker->sentence(),
            'color' => $this->faker->hexColor(),
            'default_assignee_id' => $this->faker->optional(0.5)->passthrough(User::factory()),
            'default_priority' => $this->faker->numberBetween(1, 4),
            'sla_response_hours' => $this->faker->randomElement([1, 2, 4, 8, 24]),
            'sla_resolution_hours' => $this->faker->randomElement([24, 48, 72, 96, 168]),
            'is_active' => true,
            'display_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    /**
     * Active category.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive category.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Technical category.
     */
    public function technical(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Technical Issue',
            'slug' => 'technical-issue',
            'color' => '#EF4444',
            'default_priority' => 3,
            'sla_response_hours' => 2,
            'sla_resolution_hours' => 24,
        ]);
    }

    /**
     * Billing category.
     */
    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Billing Question',
            'slug' => 'billing-question',
            'color' => '#10B981',
            'default_priority' => 2,
            'sla_response_hours' => 4,
            'sla_resolution_hours' => 48,
        ]);
    }

    /**
     * Feature request category.
     */
    public function featureRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Feature Request',
            'slug' => 'feature-request',
            'color' => '#8B5CF6',
            'default_priority' => 1,
            'sla_response_hours' => 24,
            'sla_resolution_hours' => 168,
        ]);
    }

    /**
     * Urgent SLA category.
     */
    public function urgentSla(): static
    {
        return $this->state(fn (array $attributes) => [
            'default_priority' => 4,
            'sla_response_hours' => 1,
            'sla_resolution_hours' => 4,
        ]);
    }
}
